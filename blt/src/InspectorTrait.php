<?php

namespace Uiowa;

/**
 * Temporary trait to roll our own inspector methods.
 */
trait InspectorTrait {

  /**
   * Determine if Drupal is installed via a SQL query.
   *
   * We were relying on BLT Inspector::isDrupalInstalled() but a change in
   * that method started relying on Drush status to determine this. This
   * is problematic because errors can prevent Drush status from completing.
   *
   * @see https://github.com/acquia/blt/pull/4049
   *
   * @return bool
   *   Whether drupal is installed.
   */
  protected function isDrupalInstalled($uri): bool {
    $result = $this->getContainer()->get('executor')->drush("sqlq --uri=$uri \"SHOW TABLES LIKE 'config'\"")->run();
    $output = trim($result->getMessage());
    return $result->wasSuccessful() && $output == 'config';
  }

}
