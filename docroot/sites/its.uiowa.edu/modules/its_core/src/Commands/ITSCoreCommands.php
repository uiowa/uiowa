<?php

namespace Drupal\its_core\Commands;

use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
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
    $alerts = [];

    if (!empty($views)) {
      foreach ($views as $key => $view) {
        if (!empty($view['view'])) {
          foreach ($view['view'] as $row) {
            $entity = $row->_entity;
            $alerts[$key] = [
              '#type' => 'container',
            ];
            $alerts[$key]['title'] = [
              '#type' => 'html_tag',
              '#tag' => 'h1',
              '#value' => $view['title'],
            ];

            $alert = its_core_alert_email_build($entity);
            $alerts[$key]['alerts'][] = $alert;
          }
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

      $alerts['related']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h1',
        '#value' => 'Related links',
      ];
      $alerts['related']['list'] = [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $links,
      ];

      $email = $this->emailFactory->sendTypedEmail('its_core', 'its_alerts_digest', $alerts);

      if ($email->getError()) {
        $message = t('Alerts Digest no sent');
      }
      else {
        $message = t('Alerts Digest sent');
      }

      $this->getLogger('its_core')->notice($message);
      $this->logger->notice($message);
    }
    else {
      $message = t('Alerts Digest - No items to send');
      $this->getLogger('its_core')->notice($message);
      $this->logger->notice($message);
      return;
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
