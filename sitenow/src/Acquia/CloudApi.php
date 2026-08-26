<?php

namespace SiteNow\Acquia;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Domains;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Response\OperationResponse;

/**
 * Reads and writes the Acquia Cloud resources a multisite owns.
 */
class CloudApi {

  /**
   * Status reported while an operation is still running.
   */
  const STATUS_IN_PROGRESS = 'in-progress';

  /**
   * Status reported once an operation finished successfully.
   */
  const STATUS_COMPLETED = 'completed';

  /**
   * Constructs the client wrapper.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param int $timeout
   *   Seconds to wait for a write to be confirmed.
   * @param int $interval
   *   Seconds between polls.
   */
  public function __construct(
    private Client $client,
    private int $timeout = 300,
    private int $interval = 3,
  ) {}

  /**
   * Whether an application lists a database.
   *
   * @param string $appUuid
   *   The application UUID.
   * @param string $dbName
   *   The database name (e.g. "foo_sites_uiowa_edu").
   *
   * @return bool
   *   TRUE if the application still reports the database.
   */
  public function databaseExists(string $appUuid, string $dbName): bool {
    foreach ((new Databases($this->client))->getAll($appUuid) as $database) {
      if ($database->name === $dbName) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Create a database on an application.
   *
   * @param string $appUuid
   *   The application UUID.
   * @param string $appName
   *   The application name, used in messages (e.g. "uiowa09").
   * @param string $dbName
   *   The database to create.
   *
   * @throws \RuntimeException
   *   If the API rejects the request.
   */
  public function createDatabase(string $appUuid, string $appName, string $dbName): void {
    try {
      (new Databases($this->client))->create($appUuid, $dbName);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to create database {$dbName} on {$appName}: {$e->getMessage()}", 0, $e);
    }
  }

  /**
   * Delete a database from an application, then confirm it is gone.
   *
   * The delete is application-scoped: one call removes the database from every
   * environment.
   *
   * Idempotent.
   *
   * @param string $appUuid
   *   The application UUID.
   * @param string $appName
   *   The application name, used in messages (e.g. "uiowa09").
   * @param string $dbName
   *   The database to delete.
   *
   * @throws \RuntimeException
   *   If the API rejects the request, the operation reports failure, or the
   *   database is still listed once the timeout expires.
   */
  public function deleteDatabase(string $appUuid, string $appName, string $dbName): void {
    if (!$this->databaseExists($appUuid, $dbName)) {
      return;
    }

    $label = "database {$dbName} on {$appName}";

    try {
      $operation = (new Databases($this->client))->delete($appUuid, $dbName);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to delete {$label}: {$e->getMessage()}", 0, $e);
    }

    $this->awaitOperation($operation, "Delete of {$label}");
    $this->confirmAbsent(fn() => $this->databaseExists($appUuid, $dbName), $label);
  }

  /**
   * Whether an environment reports a domain.
   *
   * @param string $envUuid
   *   The environment UUID.
   * @param string $domain
   *   The domain hostname.
   *
   * @return bool
   *   TRUE if the environment still reports the domain.
   */
  public function domainExists(string $envUuid, string $domain): bool {
    foreach ((new Domains($this->client))->getAll($envUuid) as $found) {
      if ($found->hostname === $domain) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Delete a domain from an environment, then confirm it is gone.
   *
   * Idempotent.
   *
   * @param string $envUuid
   *   The environment UUID the domain is attached to.
   * @param string $envLabel
   *   Application and environment, used in messages (e.g. "uiowa09.prod").
   * @param string $domain
   *   The domain to delete.
   *
   * @throws \RuntimeException
   *   If the API rejects the request, the operation reports failure, or the
   *   domain is still attached once the timeout expires.
   */
  public function deleteDomain(string $envUuid, string $envLabel, string $domain): void {
    if (!$this->domainExists($envUuid, $domain)) {
      return;
    }

    $label = "domain {$domain} on {$envLabel}";

    try {
      $operation = (new Domains($this->client))->delete($envUuid, $domain);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to delete {$label}: {$e->getMessage()}", 0, $e);
    }

    $this->awaitOperation($operation, "Delete of {$label}");
    $this->confirmAbsent(fn() => $this->domainExists($envUuid, $domain), $label);
  }

  /**
   * Extract the notification UUID from an operation's links.
   *
   * @param object|null $links
   *   The operation's _links object, if any.
   *
   * @return string|null
   *   The notification UUID, or NULL when the links carry no usable one.
   */
  public static function notificationUuid(?object $links): ?string {
    $href = $links->notification->href ?? NULL;

    if (!is_string($href)) {
      return NULL;
    }

    $path = parse_url($href, PHP_URL_PATH);

    if (!is_string($path)) {
      return NULL;
    }

    $candidate = basename($path);

    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $candidate)
      ? $candidate
      : NULL;
  }

  /**
   * Poll an operation's notification until it leaves the in-progress state.
   *
   * A write returns once the request is accepted (HTTP 202), not once the work
   * is done, and carries a notification link to poll. Without the poll a
   * completed operation cannot be told from a failed one.
   *
   * @param \AcquiaCloudApi\Response\OperationResponse $operation
   *   The response returned by the write that started the operation.
   * @param string $label
   *   What is being waited on, used in error messages.
   *
   * @throws \RuntimeException
   *   If the API cannot be reached, the wait times out, or the operation
   *   reached any terminal status other than completed.
   */
  protected function awaitOperation(OperationResponse $operation, string $label): void {
    $uuid = self::notificationUuid($operation->links ?? NULL);

    if ($uuid === NULL) {
      return;
    }

    $deadline = time() + $this->timeout;

    while (TRUE) {
      try {
        $status = $this->notificationStatus($uuid);
      }
      catch (\Exception $e) {
        throw new \RuntimeException("Cannot confirm {$label}: {$e->getMessage()}", 0, $e);
      }

      if ($status !== self::STATUS_IN_PROGRESS) {
        break;
      }

      if (time() >= $deadline) {
        throw new \RuntimeException("Timed out after {$this->timeout}s waiting for {$label}. The operation may still be running on Acquia.");
      }

      sleep($this->interval);
    }

    if ($status !== self::STATUS_COMPLETED) {
      throw new \RuntimeException("{$label} did not succeed: Acquia reported status '{$status}'.");
    }
  }

  /**
   * Poll until a resource stops being reported, or fail.
   *
   * The notification link that would let a caller poll for completion is not
   * always present, so the authority on whether a delete finished is the
   * resource no longer being listed.
   *
   * @param callable $present
   *   Returns TRUE while the resource is still listed.
   * @param string $label
   *   What is being confirmed, used in error messages.
   *
   * @throws \RuntimeException
   *   If the resource is still listed when the timeout expires, or if the
   *   listing cannot be read.
   */
  protected function confirmAbsent(callable $present, string $label): void {
    $deadline = time() + $this->timeout;

    while (TRUE) {
      try {
        $still_there = (bool) $present();
      }
      catch (\Exception $e) {
        throw new \RuntimeException("Cannot confirm {$label} was deleted: {$e->getMessage()}", 0, $e);
      }

      if (!$still_there) {
        return;
      }

      if (time() >= $deadline) {
        throw new \RuntimeException("Timed out after {$this->timeout}s confirming {$label} was deleted; Acquia still reports it. Nothing further was deleted.");
      }

      sleep($this->interval);
    }
  }

  /**
   * The status the API reports for one notification.
   *
   * @param string $uuid
   *   The notification UUID.
   *
   * @return string
   *   The reported status.
   */
  protected function notificationStatus(string $uuid): string {
    return (string) (new Notifications($this->client))->get($uuid)->status;
  }

}
