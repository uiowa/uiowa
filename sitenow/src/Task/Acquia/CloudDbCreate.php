<?php

namespace SiteNow\Task\Acquia;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Databases;
use Robo\Contract\RollbackInterface;
use Robo\Contract\SimulatedInterface;
use Robo\Result;

/**
 * Creates a database on an Acquia Cloud application.
 */
class CloudDbCreate extends AcquiaApiTask implements SimulatedInterface, RollbackInterface {

  public function __construct(
    Client $client,
    private string $appUuid,
    private string $appName,
    private string $dbName,
  ) {
    parent::__construct($client);
  }

  /**
   * {@inheritdoc}
   */
  public function run(): Result {
    $databases = new Databases($this->client);
    try {
      $response = $databases->create($this->appUuid, $this->dbName);
    }
    catch (\Exception $e) {
      return Result::error($this, 'Acquia API error: ' . $e->getMessage());
    }

    if (stripos($response->message, 'created') !== FALSE) {
      $this->printTaskInfo("Database <info>{$this->dbName}</info> is being created on <info>{$this->appName}</info>.");
      return Result::success($this);
    }

    return Result::error($this, "Failed to create database {$this->dbName}: {$response->message}");
  }

  /**
   * {@inheritdoc}
   */
  public function simulate($context): void {
    $this->printTaskInfo("Would create database <info>{$this->dbName}</info> on <info>{$this->appName}</info>.");
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(): void {
    try {
      $databases = new Databases($this->client);
      $databases->delete($this->appUuid, $this->dbName);
      $this->printTaskInfo("Rolled back: deleted database {$this->dbName} from {$this->appName}.");
    }
    catch (\Exception $e) {
      $this->printTaskWarning("Rollback best-effort failed for {$this->dbName}: {$e->getMessage()}");
    }
  }

}
