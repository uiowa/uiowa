<?php

namespace SiteNow\Operation;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Notifications;
use AcquiaCloudApi\Response\OperationResponse;

/**
 * Blocks until an Acquia Cloud operation finishes, and fails if it did not.
 *
 * Cloud API writes return once the request is *accepted* (HTTP 202), not once
 * the work is done; the response carries a notification link to poll. A caller
 * that skips the poll cannot tell a completed operation from a failed one, so
 * anything that must be confirmed before later work depends on it waits here
 * first.
 *
 * The only class under Operation that makes no change of its own: it confirms
 * a change another operation requested.
 */
class CloudOperationWait {

  /**
   * Status reported while the operation is still running.
   */
  const STATUS_IN_PROGRESS = 'in-progress';

  /**
   * Status reported once the operation finished successfully.
   */
  const STATUS_COMPLETED = 'completed';

  /**
   * Constructs the waiter.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param \AcquiaCloudApi\Response\OperationResponse $operation
   *   The response returned by the write that started the operation.
   * @param string $label
   *   What is being waited on, used in error messages (e.g. "Delete database
   *   foo_uiowa_edu").
   * @param int $timeout
   *   Seconds to wait before giving up.
   * @param int $interval
   *   Seconds between polls.
   */
  public function __construct(
    private Client $client,
    private OperationResponse $operation,
    private string $label,
    private int $timeout = 300,
    private int $interval = 3,
  ) {}

  /**
   * Poll until the operation leaves the in-progress state.
   *
   * @return string
   *   The terminal status, always self::STATUS_COMPLETED on return.
   *
   * @throws \RuntimeException
   *   If the operation carries no notification link, the API cannot be
   *   reached, the wait times out, or the operation reached any terminal
   *   status other than completed.
   */
  public function run(): string {
    $uuid = self::notificationUuid($this->operation->links);

    if ($uuid === NULL) {
      throw new \RuntimeException("Cannot confirm {$this->label}: Acquia returned no notification link to poll.");
    }

    $notifications = new Notifications($this->client);
    $deadline = time() + $this->timeout;

    while (TRUE) {
      try {
        $status = (string) $notifications->get($uuid)->status;
      }
      catch (\Exception $e) {
        throw new \RuntimeException("Cannot confirm {$this->label}: {$e->getMessage()}", 0, $e);
      }

      if ($status !== self::STATUS_IN_PROGRESS) {
        break;
      }

      if (time() >= $deadline) {
        throw new \RuntimeException("Timed out after {$this->timeout}s waiting for {$this->label}. The operation may still be running on Acquia.");
      }

      sleep($this->interval);
    }

    if ($status !== self::STATUS_COMPLETED) {
      throw new \RuntimeException("{$this->label} did not succeed: Acquia reported status '{$status}'.");
    }

    return $status;
  }

  /**
   * Extract the notification UUID from an operation's links.
   *
   * The href is validated down to a UUID rather than trusted as a path, so a
   * malformed link fails here instead of producing a request against a
   * nonsense endpoint.
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

}
