<?php

namespace SiteNow\Operation;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Domains;

/**
 * Deletes one domain from one Acquia Cloud environment.
 *
 * The domain is named by the caller. An environment carries the domains of
 * every site on it, so which ones belong to the site being deleted is not a
 * question this class can answer.
 */
class CloudDomainDelete {

  use ConfirmsAbsence;

  /**
   * Constructs the operation.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param string $envUuid
   *   The environment UUID the domain is attached to.
   * @param string $envLabel
   *   Application and environment, used in messages (e.g. "uiowa09.prod").
   * @param string $domain
   *   The domain to delete.
   * @param int $timeout
   *   Seconds to wait for the delete to be confirmed.
   * @param int $interval
   *   Seconds between polls.
   */
  public function __construct(
    private Client $client,
    private string $envUuid,
    private string $envLabel,
    private string $domain,
    private int $timeout = 300,
    private int $interval = 3,
  ) {}

  /**
   * Whether the domain is attached to the environment.
   *
   * @return bool
   *   TRUE if the environment still reports the domain.
   */
  public function exists(): bool {
    $domains = new Domains($this->client);

    foreach ($domains->getAll($this->envUuid) as $domain) {
      if ($domain->hostname === $this->domain) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Delete the domain, then confirm it is gone.
   *
   * Idempotent: a domain that is already absent is a success, so a retry after
   * a partial run does not fail here.
   *
   * @throws \RuntimeException
   *   If the API rejects the request, the operation reports failure, or the
   *   domain is still attached once the timeout expires.
   */
  public function run(): void {
    if (!$this->exists()) {
      return;
    }

    $domains = new Domains($this->client);
    $label = "domain {$this->domain} on {$this->envLabel}";

    try {
      $operation = $domains->delete($this->envUuid, $this->domain);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to delete {$label}: {$e->getMessage()}", 0, $e);
    }

    if (CloudOperationWait::notificationUuid($operation->links ?? NULL) !== NULL) {
      (new CloudOperationWait($this->client, $operation, "Delete of {$label}", $this->timeout, $this->interval))->run();
    }

    $this->confirmAbsent(fn() => $this->exists(), $label, $this->timeout, $this->interval);
  }

}
