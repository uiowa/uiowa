<?php

namespace SiteNow\Robo\Task\Acquia;

use AcquiaCloudApi\Connector\Client;

trait Tasks {

  protected function taskCloudDbCreate(
    Client $client,
    string $appUuid,
    string $appName,
    string $dbName,
  ): CloudDbCreate {
    return new CloudDbCreate($client, $appUuid, $appName, $dbName);
  }

}
