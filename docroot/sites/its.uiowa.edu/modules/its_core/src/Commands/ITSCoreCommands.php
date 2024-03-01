<?php

namespace Drupal\its_core\Commands;

use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
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
    $this->accountSwitcher = $accountSwitcher;
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
    $views['outages_degradations'] = views_get_view_result('alerts_list_block', 'outages_degradations');
    $views['planned_maintenance'] = views_get_view_result('alerts_list_block', 'planned_maintenance');
    $views['service_announcements'] = views_get_view_result('alerts_list_block', 'service_announcements');
    // $views['ongoing'] = '';
    $alerts = [];

    if (!empty($views)) {
      foreach ($views as $key => $view) {
        if (!empty($view)) {
          foreach ($view as $row) {
            $entity = $row->_entity;
            $alerts[$key][] = $entity;
          }
        }
      }

      $email = $this->emailFactory->sendTypedEmail('its_core', 'its_alerts_digest', $alerts);

      if ($email->getError()) {
        $this->output()->writeln('Alerts Digest not sent.');
      }
      else {
        $this->output()->writeln('Alerts Digest sent.');
      }
    }
    else {
      $this->output()->writeln('Alerts Digest - No items to send.');
      return;
    }

    // Switch user back.
    $this->accountSwitcher->switchBack();
  }

}
