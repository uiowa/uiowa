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
   * Asks which content tables exist, without failing when they do not.
   *
   * Counting a table that does not exist is an error, and that error cannot be
   * told apart from a database that went away mid-check — so existence is
   * established first, against information_schema, which answers whenever the
   * database is reachable at all. The table names are fixed literals here, not
   * input.
   */
  const CONTENT_TABLES_QUERY = "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('node_field_data', 'users_field_data')";

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

    // Ask the completion question first. Every site that is fine answers it in
    // one drush call, and a scan covers a whole application, so probing the
    // config table first would double the cost of the common case to learn
    // something the state read already implies.
    $task = $this->drush(['sql:query', self::INSTALL_TASK_QUERY], uri: $site);

    if ($task->isSuccessful()) {
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

    // The state read failed, which is ambiguous: an empty or unreachable
    // database reads the same as one whose install stopped before key_value
    // existed. The config table is among the first things an install writes, so
    // it separates the two.
    $config = $this->drush(['sql:query', "SHOW TABLES LIKE 'config'"], uri: $site);
    if ($config->isSuccessful() && trim($config->getOutput()) === 'config') {
      return $this->partialState($site, 'the key_value table is missing or unreadable');
    }

    // Empty or unreachable. Both read as "not installed", and both end in an
    // install attempt that either works or fails loudly, which is what the BLT
    // command did.
    return new InstallState(InstallStatus::Absent);
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
    $present = $this->contentTables($site);

    // The database could not be asked what it holds, so nothing has been ruled
    // out. Refusing here is the whole point of the check.
    if ($present === NULL) {
      return new InstallState(InstallStatus::Partial, $detail, contentUnknown: TRUE);
    }

    // A table the install never got far enough to create holds nothing to lose,
    // so an absent table counts as zero rather than as an unanswered question.
    //
    // Both counts exclude uid 0 and 1 deliberately. The profile installs six
    // nodes of its own, all authored by the installer's account, so counting
    // every node would flag a site that has only ever been installed as one
    // holding content — and every incomplete install would need --force, which
    // is the opposite of healing itself.
    $nodes = in_array('node_field_data', $present, TRUE)
      ? $this->countRows($site, 'SELECT COUNT(*) FROM node_field_data WHERE uid > 1')
      : 0;
    $users = in_array('users_field_data', $present, TRUE)
      ? $this->countRows($site, 'SELECT COUNT(*) FROM users_field_data WHERE uid > 1')
      : 0;

    if ($nodes === NULL || $users === NULL) {
      return new InstallState(InstallStatus::Partial, $detail, contentUnknown: TRUE);
    }

    return new InstallState(InstallStatus::Partial, $detail, $nodes, $users);
  }

  /**
   * Ask which of the content tables exist.
   *
   * @param string $site
   *   The site host / canonical domain.
   *
   * @return string[]|null
   *   The table names present, empty when none are; NULL when the question
   *   could not be answered, which must not be read as "none".
   */
  private function contentTables(string $site): ?array {
    $result = $this->drush(['sql:query', self::CONTENT_TABLES_QUERY], uri: $site);
    if (!$result->isSuccessful()) {
      return NULL;
    }

    $lines = array_map('trim', preg_split('/\R/', $result->getOutput()) ?: []);

    return array_values(array_filter($lines, fn ($line) => $line !== ''));
  }

  /**
   * Count rows for a content check.
   *
   * Only called for a table already known to exist, so a failure here means the
   * check itself broke down rather than that the table is missing.
   *
   * @param string $site
   *   The site host / canonical domain.
   * @param string $query
   *   A query returning a single count.
   *
   * @return int|null
   *   The count, or NULL when the query could not run.
   */
  private function countRows(string $site, string $query): ?int {
    $result = $this->drush(['sql:query', $query], uri: $site);

    return $result->isSuccessful() ? (int) trim($result->getOutput()) : NULL;
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
