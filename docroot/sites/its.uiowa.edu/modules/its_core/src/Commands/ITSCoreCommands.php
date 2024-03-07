<?php

namespace Drupal\its_core\Commands;

use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class ITSCoreCommands extends DrushCommands {

  use LoggerChannelTrait;
  use StringTranslationTrait;

  /**
   * Drush command constructor.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account_switcher service.
   * @param \Drupal\symfony_mailer\EmailFactoryInterface $emailFactory
   *   The plugin.manager.mail service.
   */
  public function __construct(protected AccountSwitcherInterface $accountSwitcher, protected EmailFactoryInterface $emailFactory) {
    parent::__construct();
  }

  /**
   * Triggers the Alerts Digest.
   *
   * @command its_core:alerts-digest
   * @aliases its-digest
   * @usage its_core:alerts-digest
   *  Ideally this is done as a crontab that is only sent once a day.
   */
  public function alertsDigest() {
    // Switch to the admin user to get hidden view result.
    $this->accountSwitcher->switchTo(new UserSession(['uid' => 1]));

    $views = [];
    $views['outages_degradations'] = [
      'title' => 'Alerts',
      'view' => views_get_view_result('alerts_list_block', 'outages_degradations'),
    ];
    $views['planned_maintenance'] = [
      'title' => 'Planned Maintenance',
      'view' => views_get_view_result('alerts_list_block', 'planned_maintenance'),
    ];
    $views['service_announcements'] = [
      'title' => 'Service Announcements',
      'view' => views_get_view_result('alerts_list_block', 'service_announcements'),
    ];
    $views['ongoing'] = [
      'title' => 'Ongoing Maintenance',
      'view' => views_get_view_result('alerts_list_block', 'ongoing'),
    ];
    $content = [];

    if (!empty($views)) {
      foreach ($views as $group => $view) {
        if (!empty($view['view'])) {
          $alerts = [];
          foreach ($view['view'] as $row) {
            $entity = $row->_entity;
            $content[$group] = [
              '#type' => 'container',
            ];
            $content[$group]['title'] = [
              '#type' => 'html_tag',
              '#tag' => 'h1',
              '#value' => $view['title'],
            ];

            $alerts[] = its_core_alert_email_build($entity);

          }
          $content[$group]['alerts'] = $alerts;
        }
      }

      // Include related links.
      $links = [
        Link::fromTextAndUrl('Why am I receiving this email?',
          Url::fromUri('https://its.uiowa.edu/support/article/127441')),
        Link::fromTextAndUrl('IT Service Alerts page',
          Url::fromUri('https://its.uiowa.edu/alerts')),
        Link::fromTextAndUrl('Calendar view of alerts',
          Url::fromUri('https://its.uiowa.edu/alerts/calendar')),
      ];

      $content['related']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => 'Related links',
      ];
      $content['related']['list'] = [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $links,
      ];

      $email = $this->emailFactory->sendTypedEmail('its_core', 'its_alerts_digest', $content);

      if ($email->getError()) {
        $message = $this->t('Alerts Digest no sent');
      }
      else {
        $message = $this->t('Alerts Digest sent');
      }

      $this->getLogger('its_core')->notice($message);
      $this->logger->notice($message);
    }
    else {
      $message = $this->t('Alerts Digest - No items to send');
      $this->getLogger('its_core')->notice($message);
      $this->logger->notice($message);
      return;
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
