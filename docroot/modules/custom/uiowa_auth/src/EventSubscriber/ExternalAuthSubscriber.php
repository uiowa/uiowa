<?php

namespace Drupal\uiowa_auth\EventSubscriber;

use Drupal\externalauth\Authmap;
use Drupal\externalauth\Event\ExternalAuthEvents;
use Drupal\externalauth\Event\ExternalAuthLoginEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\uiowa_auth\RoleMappings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use Drupal\samlauth\SamlService;

/**
 * The uiowa event subscriber.
 */
class ExternalAuthSubscriber implements EventSubscriberInterface {

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
   * The externalauth authmap service.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authmap;

  /**
   * The samlauth service.
   *
   * @var \Drupal\samlauth\SamlService
   */
  protected $saml;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration factory.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\externalauth\Authmap $authmap
   *   Authmap service.
   * @param \Drupal\samlauth\SamlService $saml
   *   Samlauth service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerInterface $logger, Authmap $authmap, SamlService $saml) {
    $this->config = $config_factory->get('uiowa_auth.settings');
    $this->logger = $logger;
    $this->authmap = $authmap;
    $this->saml = $saml;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ExternalAuthEvents::LOGIN][] = ['onUserLogin'];
    return $events;
  }

  /**
   * Authmap alter logic.
   *
   * @param \Drupal\externalauth\Event\ExternalAuthLoginEvent $event
   *   The ExternalAuthLoginEvent.
   */
  public function onUserLogin(ExternalAuthLoginEvent $event) {
    $provider = $event->getProvider();

    if ($provider == 'samlauth') {
      $account = $event->getAccount();
      $authname = $event->getAuthname();

      // This will return the attributes for the current SAML response.
      // @see: SamlService::acs().
      $attributes = $this->saml->getAttributes();
      $name = $this->saml->getAttributeByConfig('user_name_attribute');

      if ($name == $account->getAccountName()) {
        $mappings = $this->config->get('role_mappings');

        $data = [
          'uiowa_auth_mappings' => [],
        ];

        foreach (RoleMappings::generate($mappings) as $mapping) {
          if (in_array($mapping['value'], $attributes[$mapping['attr']])
          && !in_array($mapping['rid'], $data['uiowa_auth_mappings'])) {
            $data['uiowa_auth_mappings'][] = $mapping['rid'];
          }
        }

        $this->authmap->save($account, $provider, $authname, $data);
        $this->logger->notice('Saved mapped roles for @user to authmap table.', ['@user' => $authname]);
      }
      else {
        $this->logger->error('Account @account name does not match SAML response attribute @name. Cannot save mapped roles.', [
          '@account' => $account->getAccountName(),
          '@name' => $name,
        ]);
      }
    }
  }

}
