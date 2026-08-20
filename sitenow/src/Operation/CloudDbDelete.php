<?php

namespace SiteNow\Operation;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;

/**
 * Deletes a multisite's database from an Acquia Cloud application.
 *
 * The delete is application-scoped: one call removes the database from every
 * environment, so this runs once per site rather than once per environment.
 */
class CloudDbDelete {

  use ConfirmsAbsence;

  /**
   * Constructs the operation.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param string $appUuid
   *   The application UUID.
   * @param string $appName
   *   The application name, used in messages (e.g. "uiowa09").
   * @param string $dbName
   *   The database to delete (e.g. "foo_sites_uiowa_edu").
   * @param int $timeout
   *   Seconds to wait for the delete to be confirmed.
   * @param int $interval
   *   Seconds between polls.
   */
  public function __construct(
    private Client $client,
    private string $appUuid,
    private string $appName,
    private string $dbName,
    private int $timeout = 300,
    private int $interval = 3,
  ) {}

  /**
   * Whether the database is listed on the application.
   *
   * @return bool
   *   TRUE if the application still reports the database.
   */
  public function exists(): bool {
    $databases = new Databases($this->client);

    foreach ($databases->getAll($this->appUuid) as $database) {
      if ($database->name === $this->dbName) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Delete the database, then confirm it is gone.
   *
   * Idempotent: a database that is already absent is a success, so a retry
   * after a partial run does not fail here.
   *
   * @throws \RuntimeException
   *   If the API rejects the request, the operation reports failure, or the
   *   database is still listed once the timeout expires.
   */
  public function run(): void {
    if (!$this->exists()) {
      return;
    }

    $databases = new Databases($this->client);
    $label = "database {$this->dbName} on {$this->appName}";

    try {
      $operation = $databases->delete($this->appUuid, $this->dbName);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to delete {$label}: {$e->getMessage()}", 0, $e);
    }

    // Wait on the notification when Acquia provides one, since it reports a
    // failed operation that polling alone would only show as a timeout.
    if (CloudOperationWait::notificationUuid($operation->links ?? NULL) !== NULL) {
      (new CloudOperationWait($this->client, $operation, "Delete of {$label}", $this->timeout, $this->interval))->run();
    }

    $this->confirmAbsent(fn() => $this->exists(), $label, $this->timeout, $this->interval);
  }

}
