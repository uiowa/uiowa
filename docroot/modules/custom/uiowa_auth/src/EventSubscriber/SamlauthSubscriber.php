<?php

namespace Drupal\uiowa_auth\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\externalauth\Authmap;
use Drupal\externalauth\Exception\ExternalAuthRegisterException;
use Drupal\samlauth\Event\SamlauthEvents;
use Drupal\samlauth\Event\SamlauthUserSyncEvent;
use Drupal\samlauth\Event\SamlauthUserLinkEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_auth\RoleMappings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * The uiowa event subscriber.
 */
class SamlauthSubscriber implements EventSubscriberInterface {

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The extnernalauth authmap service.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authmap;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\externalauth\Authmap $authmap
   *   Authmap service.
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, Authmap $authmap, EntityTypeManager $entityTypeManager) {
    $this->config = $config_factory;
    $this->logger = $logger;
    $this->authmap = $authmap;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SamlauthEvents::USER_SYNC][] = ['onUserSync'];
    $events[SamlauthEvents::USER_LINK][] = ['onUserLink'];
    return $events;
  }

  /**
   * User synchronization logic.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserSyncEvent $event
   *   The SamlauthUserSyncEvent.
   */
  public function onUserSync(SamlauthUserSyncEvent $event) {
    $account = $event->getAccount();
    $attributes = $event->getAttributes();

    // Revoke all previously-mapped roles for existing users.
    if ($account->isNew() === FALSE) {
      $row = $this->authmap->getAuthData($account->id(), 'samlauth');
      $data = unserialize($row['data'], ['allowed_classes' => FALSE]);

      if (!empty($data['uiowa_auth_mappings'])) {
        foreach ($data['uiowa_auth_mappings'] as $rid) {
          $account->removeRole($rid);
          $this->logger->notice('Revoked previously-mapped role @role for user @user so mapping is re-evaluated.', [
            '@role' => $rid,
            '@user' => $account->getAccountName(),
          ]);
        }
      }
    }

    $mappings = $this->config->get('uiowa_auth.settings')->get('role_mappings');

    foreach (RoleMappings::generate($mappings) as $mapping) {
      if (!$account->hasRole($mapping['rid']) && in_array($mapping['value'], $attributes[$mapping['attr']])) {
        $account->addRole($mapping['rid']);

        $this->logger->notice('Assigned role @role for user @user based on mapping @attr => @value.', [
          '@role' => $mapping['rid'],
          '@user' => $account->getAccountName(),
          '@attr' => $mapping['attr'],
          '@value' => $mapping['value'],
        ]);
      }
    }

    // Mark the account as changed so it is saved.
    $event->markAccountChanged();
  }

  /**
   * User link logic.
   *
   * @param \Drupal\samlauth\Event\SamlauthUserLinkEvent $event
   *   The SamlauthUserLinkEvent.
   *
   * @throws \Drupal\externalauth\Exception\ExternalAuthRegisterException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function onUserLink(SamlauthUserLinkEvent $event) {
    $sync = FALSE;
    $attributes = $event->getAttributes();

    /*
     * Prevent account creation for unlinked accounts that:
     * - do not already exist
     * - do not have a valid role mapping
     *
     * This prevents any HawkID user from creating an account with no role.
     */
    if (!$event->getLinkedAccount()) {
      $name = $this->config->get('samlauth.authentication')->get('user_name_attribute');
      $authname = $attributes[$name][0];

      /** @var \Drupal\Core\Entity\EntityTypeInterface[] $search */
      $search = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => $authname]);

      if (!empty($search)) {
        // Link account if HawkID user already exists.
        $account = reset($search);
        $event->setLinkedAccount($account);
        $sync = TRUE;
      }
      else {
        // Allow account creation if at least one role mapping is valid.
        $mappings = $this->config->get('uiowa_auth.settings')->get('role_mappings');

        foreach (RoleMappings::generate($mappings) as $mapping) {
          if (in_array($mapping['value'], $attributes[$mapping['attr']])) {
            $sync = TRUE;

            $this->logger->notice('User @user has valid mapping @attr => @value for role @rid. Allowing account creation.', [
              '@user' => $authname,
              '@attr' => $mapping['attr'],
              '@value' => $mapping['value'],
              '@rid' => $mapping['rid'],
            ]);
          }
        }
      }

      if ($sync === FALSE) {
        $this->logger->error('HawkID @hawkid has no existing account or valid role mappings.', [
          '@hawkid' => $authname,
        ]);

        throw new ExternalAuthRegisterException();
      }
    }
  }

}
