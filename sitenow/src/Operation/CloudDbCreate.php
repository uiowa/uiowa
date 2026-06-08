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
   * @throws \RuntimeException
   *   If the API call fails or the database is not created.
   */
  public function run(): void {
    $databases = new Databases($this->client);

    try {
      $response = $databases->create($this->appUuid, $this->dbName);
    }
    catch (\Exception $e) {
      throw new \RuntimeException('Acquia API error: ' . $e->getMessage(), 0, $e);
    }

    if (stripos($response->message, 'created') === FALSE) {
      throw new \RuntimeException("Failed to create database {$this->dbName} on {$this->appName}: {$response->message}");
    }
  }

}
