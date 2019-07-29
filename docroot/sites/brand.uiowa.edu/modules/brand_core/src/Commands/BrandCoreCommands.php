<?php

namespace Drupal\brand_core\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Url;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 */
class BrandCoreCommands extends DrushCommands {

  /**
   * Triggers the Lockup Digest.
   *
   * @command brand_core:lockup-digest
   * @aliases brand-ld
   * @options arr An option that takes multiple values.
   * @options msg Whether or not an extra message should be displayed to the user.
   * @usage brand_core:lockup-digest --msg
   *  Ideally this is done as a crontab that is only sent once a day.
   */
  public function lockupDigest($options = ['msg' => FALSE]) {
    $view = views_get_view_result('lockup_moderation', 'block_review');
    if (!empty($view)) {
      $results = count($view);
      $params['lockups'] = [];
      // Access field data from the view results.
      foreach ($view as $row) {
        $entity = $row->_entity;
        $params['lockups'][] = $entity->getTitle();
      }
      $label = $results > 1 ? 'lockups' : 'lockup';
      // Prepare params for digest email.
      $mailManager = \Drupal::service('plugin.manager.mail');
      $params['label'] = $label;
      $params['results'] = (string) $results;
      global $base_url;
      $url_options = [
        'query' => ['destination' => '/admin/content/lockups'],
      ];
      $params['login'] = Url::fromUri($base_url . '/saml/login', $url_options)->toString();
      $result = $mailManager->mail('brand_core', 'lockup-review-digest', 'joe-whitsitt@uiowa.edu', 'en', $params, NULL, TRUE);
      if ($result['result'] !== TRUE) {
        \Drupal::logger('brand_core')->error(t('Lockup Review Digest Not Sent'));
        $this->output()->writeln('Lockup Review Digest Not Sent');
      }
      else {
        \Drupal::logger('brand_core')->notice(t('Lockup Review Digest Sent'));
        $this->output()->writeln('Lockup Review Digest Sent');
      }
    }
    else {
      \Drupal::logger('brand_core')->notice(t('Lockup Review Digest - No items to review'));
      $this->output()->writeln('Lockup Review Digest - No items to review');
      return;
    }
    if ($options['msg']) {
      $this->output()->writeln('Hey! Way to go above and beyond!');
    }
  }

}
