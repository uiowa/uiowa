<?php

namespace SiteNow\Task\Acquia;

use AcquiaCloudApi\Connector\Client;

/**
 * Task factory methods for the Acquia API tasks.
 */
trait Tasks {

  /**
   * Creates a CloudDbCreate task.
   */
  protected function taskCloudDbCreate(
    Client $client,
    string $appUuid,
    string $appName,
    string $dbName,
  ): CloudDbCreate {
    return new CloudDbCreate($client, $appUuid, $appName, $dbName);
  }

}
