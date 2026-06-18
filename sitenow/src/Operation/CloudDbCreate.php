<?php

namespace SiteNow\Operation;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;

/**
 * Creates a database on an Acquia Cloud application.
 *
 * The only non-git side effect in a multisite create. There is no rollback: a
 * failed run leaves the database in place on Acquia.
 */
class CloudDbCreate {

  public function __construct(
    private Client $client,
    private string $appUuid,
    private string $appName,
    private string $dbName,
  ) {}

  /**
   * Create the database.
   *
   * The Acquia SDK throws ApiErrorException (carrying the API's error message)
   * when a request fails; a successful return means the create was accepted
   * (HTTP 202), so the response body needs no further inspection.
   *
   * @throws \RuntimeException
   *   If the API rejects the request.
   */
  public function run(): void {
    $databases = new Databases($this->client);

    try {
      $databases->create($this->appUuid, $this->dbName);
    }
    catch (\Exception $e) {
      throw new \RuntimeException("Failed to create database {$this->dbName} on {$this->appName}: {$e->getMessage()}", 0, $e);
    }
  }

}
