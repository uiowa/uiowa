<?php

namespace SiteNow\Task\Acquia;

use AcquiaCloudApi\Connector\Client;

/**
 * Task factory methods for the Acquia API tasks.
 */
trait Tasks {

  /**
   * Creates a CloudDbCreate task.
   *
   * Built through the collection builder (not `new`) so Robo wraps it under
   * --simulate and it never runs for real in simulated mode.
   *
   * @return \SiteNow\Task\Acquia\CloudDbCreate
   *   The task, proxied through a collection builder.
   */
  protected function taskCloudDbCreate(
    Client $client,
    string $appUuid,
    string $appName,
    string $dbName,
  ) {
    return $this->task(CloudDbCreate::class, $client, $appUuid, $appName, $dbName);
  }

}
