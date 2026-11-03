<?php

namespace SiteNow\Acquia;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Exception\ApiErrorException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * An Acquia Cloud API client that retries once on an expired access token.
 *
 * The SDK caches the token on disk and can hand back one that has expired.
 * On that error, retry with a new connector and an empty token cache.
 */
class RetryingClient extends Client {

  /**
   * Builds a new connector for the retry.
   *
   * @var callable|null
   */
  private $connectorFactory = NULL;

  /**
   * Set the callable that builds a new connector for the retry.
   *
   * @param callable $factory
   *   Returns a new \AcquiaCloudApi\Connector\ConnectorInterface.
   *
   * @return $this
   */
  public function setConnectorFactory(callable $factory): static {
    $this->connectorFactory = $factory;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function request(string $verb, string $path, array $options = []): mixed {
    try {
      return parent::request($verb, $path, $options);
    }
    catch (ApiErrorException $e) {
      if (!$this->connectorFactory || !static::isExpiredToken($e)) {
        throw $e;
      }
    }

    fwrite(STDERR, "Acquia access token expired on {$verb} {$path}; retrying with a new token.\n");

    return $this->withEmptyTokenCache(function () use ($verb, $path, $options) {
      $this->connector = ($this->connectorFactory)();
      return parent::request($verb, $path, $options);
    });
  }

  /**
   * Run a callback with the SDK pointed at an empty token cache.
   *
   * @param callable $callback
   *   The work to run.
   *
   * @return mixed
   *   The callback's return value.
   */
  protected function withEmptyTokenCache(callable $callback): mixed {
    $previous = getenv('XDG_CACHE_HOME');
    $directory = Path::join(sys_get_temp_dir(), 'sn-acquia-token-' . uniqid());
    putenv("XDG_CACHE_HOME={$directory}");

    try {
      return $callback();
    }
    finally {
      putenv($previous === FALSE ? 'XDG_CACHE_HOME' : "XDG_CACHE_HOME={$previous}");
      (new Filesystem())->remove($directory);
    }
  }

  /**
   * Whether an API error means the access token expired.
   */
  protected static function isExpiredToken(ApiErrorException $e): bool {
    return stripos($e->getMessage(), 'access token has expired') !== FALSE;
  }

}
