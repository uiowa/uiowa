<?php

namespace Uiowa\Blt;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;

/**
 * Provides AC API functionality.
 */
trait AcquiaCloudApiTrait {

  /**
   * Return new Client for interacting with Acquia Cloud API.
   *
   * @return \AcquiaCloudApi\Connector\Client
   *   ConnectorInterface client.
   */
  protected function getAcquiaCloudApiClient(string $key, string $secret): Client {
    $connector = new Connector([
      'key' => $key,
      'secret' => $secret,
    ]);

    /** @var \AcquiaCloudApi\Connector\Client $client */
    $client = Client::factory($connector);

    return $client;
  }

}
