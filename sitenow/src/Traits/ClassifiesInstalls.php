<?php

namespace SiteNow\Traits;

use SiteNow\Install\InstallState;
use SiteNow\Install\InstallStatus;

/**
 * Decides how far along a multisite's Drupal installation is.
 *
 * Shared by site:install, which acts on one site, and multisite:install, which
 * scans an application to find the sites needing attention. Both have to agree
 * on what "installed" means, so the test lives here rather than in either
 * command.
 *
 * Requires the using class to also use SiteNowCommandsTrait (for drush(),
 * siteDirectory() and databaseName()) and to declare a $repoRoot property.
 */
trait ClassifiesInstalls {

  /**
   * Reads Drupal's install progress marker straight out of the database.
   *
   * State lives in the key_value table under the 'state' collection. Read via
   * SQL rather than `drush state:get` because a site whose install died partway
   * may not bootstrap at all — which is the exact case this has to classify.
   */
  const INSTALL_TASK_QUERY = "SELECT value FROM key_value WHERE collection = 'state' AND name = 'install_task'";

  /**
   * Classify one site's installation.
   *
   * @param string $site
   *   The site host / canonical domain.
   * @param string $app
   *   The application (AH_SITE_GROUP), used to locate the settings include.
   * @param bool $isAcquia
   *   Whether this is running on an Acquia environment.
   *
   * @return \SiteNow\Install\InstallState
   *   The classification.
   */
  protected function classifyInstall(string $site, string $app, bool $isAcquia): InstallState {
    $dir = $this->siteDirectory($site);

    // Without this, an unresolved --uri falls back to Drupal's default site, so
    // a stale manifest entry would classify (and install) default instead.
    if (!is_dir("{$this->repoRoot}/docroot/sites/{$dir}")) {
      return new InstallState(InstallStatus::Unavailable, 'no site directory');
    }

    // On Acquia a site whose database is not on this application belongs to
    // another one; Acquia names the settings include after the database.
    if ($isAcquia) {
      $db = $this->databaseName($site, $app);
      if (!is_file("/var/www/site-php/{$app}/{$db}-settings.inc")) {
        return new InstallState(InstallStatus::Unavailable, "database not present on {$app}");
      }
    }

    // A failed query means the database is empty or unreachable. Both read as
    // "not installed" and both end in an install attempt that either works or
    // fails loudly, which is what the BLT command did.
    $config = $this->drush(['sql:query', "SHOW TABLES LIKE 'config'"], uri: $site);
    if (!$config->isSuccessful() || trim($config->getOutput()) !== 'config') {
      return new InstallState(InstallStatus::Absent);
    }

    $task = $this->drush(['sql:query', self::INSTALL_TASK_QUERY], uri: $site);

    // The config table exists but key_value does not, so the install stopped
    // between creating the two.
    if (!$task->isSuccessful()) {
      return $this->partialState($site, 'the key_value table is missing or unreadable');
    }

    $raw = trim($task->getOutput());
    if ($raw === '') {
      return $this->partialState($site, 'Drupal never recorded an install task');
    }

    $value = $this->stateValue($raw);
    if ($value === 'done') {
      return new InstallState(InstallStatus::Installed);
    }

    return $this->partialState($site, $value !== NULL
      ? "install stopped at task '{$value}'"
      : 'the install_task state value is unreadable');
  }

  /**
   * Build a partial-install state, counting what the site would lose.
   *
   * @param string $site
   *   The site host / canonical domain.
   * @param string $detail
   *   Why the install is considered partial.
   *
   * @return \SiteNow\Install\InstallState
   *   The partial state, with content counts attached.
   */
  private function partialState(string $site, string $detail): InstallState {
    // Both counts exclude uid 0 and 1 deliberately. The profile installs six
    // nodes of its own, all authored by the installer's account, so counting
    // every node would flag a site that has only ever been installed as one
    // holding content — and every incomplete install would need --force, which
    // is the opposite of healing itself.
    return new InstallState(
      InstallStatus::Partial,
      $detail,
      $this->countRows($site, 'SELECT COUNT(*) FROM node_field_data WHERE uid > 1'),
      $this->countRows($site, 'SELECT COUNT(*) FROM users_field_data WHERE uid > 1'),
    );
  }

  /**
   * Count rows for a content check, treating an unusable table as empty.
   *
   * @param string $site
   *   The site host / canonical domain.
   * @param string $query
   *   A query returning a single count.
   *
   * @return int
   *   The count, or 0 when the query could not run — a table the install never
   *   got far enough to create holds nothing to lose.
   */
  private function countRows(string $site, string $query): int {
    $result = $this->drush(['sql:query', $query], uri: $site);

    return $result->isSuccessful() ? (int) trim($result->getOutput()) : 0;
  }

  /**
   * Extract a state value from its serialized database representation.
   *
   * Only the string form is recognized, which is all install_task is ever
   * stored as. Parsed rather than unserialize()d so untrusted or truncated
   * bytes in a half-written row cannot raise a warning mid-scan.
   *
   * @param string $raw
   *   The raw column value.
   *
   * @return string|null
   *   The string value, or NULL when it is not a serialized string.
   */
  protected function stateValue(string $raw): ?string {
    return preg_match('/^s:\d+:"(.*)";$/s', $raw, $matches) === 1 ? $matches[1] : NULL;
  }

}
