<?php

namespace SiteNow\Task\Acquia;

use AcquiaCloudApi\Connector\Client;
use Robo\Task\BaseTask;

/**
 * Abstract base for Acquia Cloud API tasks.
 */
abstract class AcquiaApiTask extends BaseTask {

  public function __construct(protected Client $client) {}

}
