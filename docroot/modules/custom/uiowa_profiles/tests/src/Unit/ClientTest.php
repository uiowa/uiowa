<?php

namespace Drupal\Tests\uiowa_profiles\Unit;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_profiles\Client;
use GuzzleHttp\Client as HttpClient;
use Psr\Log\LoggerInterface;

/**
 * Tests how the Profiles client picks its environment and endpoint.
 *
 * @group uiowa_profiles
 */
class ClientTest extends UnitTestCase {

  /**
   * The Acquia environment before the test, restored afterward.
   *
   * @var string|false
   */
  protected $acquiaEnvironment;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->acquiaEnvironment = getenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->setAcquiaEnvironment($this->acquiaEnvironment);
    parent::tearDown();
  }

  /**
   * Sets or clears the Acquia environment variable.
   *
   * @param string|false|null $value
   *   The environment name, or FALSE or NULL for none.
   */
  protected function setAcquiaEnvironment($value): void {
    putenv($value ? "AH_SITE_ENVIRONMENT=$value" : 'AH_SITE_ENVIRONMENT');
  }

  /**
   * Builds a client for a configured override and an Acquia environment.
   *
   * @param string|null $override
   *   The uiowa_profiles.settings environment value.
   * @param string|null $acquia
   *   The Acquia environment name.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   A logger to use in place of a stub.
   *
   * @return \Drupal\uiowa_profiles\Client
   *   The client.
   */
  protected function client(?string $override, ?string $acquia, ?LoggerInterface $logger = NULL): Client {
    $this->setAcquiaEnvironment($acquia);

    $config = $this->createStub(ImmutableConfig::class);
    $config->method('get')->willReturnMap([['environment', $override]]);
    $factory = $this->createStub(ConfigFactoryInterface::class);
    $factory->method('get')->willReturn($config);

    return new Client($factory, $logger ?? $this->createStub(LoggerInterface::class), $this->createStub(HttpClient::class));
  }

  /**
   * Tests the environment and endpoint for each override and Acquia host.
   *
   * @dataProvider environmentProvider
   */
  public function testEnvironmentAndEndpoint(?string $override, ?string $acquia, string $environment, string $endpoint): void {
    $client = $this->client($override, $acquia);

    $this->assertSame($environment, $client->environment);
    $this->assertSame($endpoint, $client->endpoint);
  }

  /**
   * Data provider for testEnvironmentAndEndpoint().
   */
  public static function environmentProvider(): array {
    $feature = 'https://api-feature.profiles.uiowa.edu/api';
    $test = 'https://api-test.profiles.uiowa.edu/api';
    $prod = 'https://api.profiles.uiowa.edu/api';

    return [
      'Acquia prod' => [NULL, 'prod', 'prod', $prod],
      'Acquia test (stage) uses Profiles prod' => [NULL, 'test', 'prod', $prod],
      'Acquia dev' => [NULL, 'dev', 'test', $test],
      'no Acquia environment' => [NULL, NULL, 'test', $test],
      'override to feature' => ['feature', NULL, 'feature', $feature],
      'override to test on Acquia prod' => ['test', 'prod', 'test', $test],
      'override to prod on Acquia dev' => ['prod', 'dev', 'prod', $prod],
    ];
  }

  /**
   * Tests that an unknown override is logged and leaves the endpoint empty.
   */
  public function testUnknownEnvironmentIsLogged(): void {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
      ->method('error')
      ->with($this->stringContains('Invalid environment'), ['@env' => 'feature-test']);

    $client = $this->client('feature-test', NULL, $logger);

    $this->assertSame('', $client->endpoint);
  }

  /**
   * Tests that each environment has a client library on its own hosts.
   *
   * @dataProvider libraryProvider
   */
  public function testEnvironmentHasClientLibrary(string $environment, string $host): void {
    $libraries = Yaml::decode(file_get_contents(dirname(__DIR__, 3) . '/uiowa_profiles.libraries.yml'));

    $this->assertArrayHasKey("client.$environment", $libraries);
    $library = $libraries["client.$environment"];
    $this->assertArrayHasKey("https://$host/api/lib/profiles-client.umd.js", $library['js']);
    $this->assertArrayHasKey("https://$host/api/lib/profiles-client.css", $library['css']['theme']);
  }

  /**
   * Data provider for testEnvironmentHasClientLibrary().
   */
  public static function libraryProvider(): array {
    return [
      'feature' => ['feature', 'feature-test.profiles.uiowa.edu'],
      'test' => ['test', 'test.profiles.uiowa.edu'],
      'prod' => ['prod', 'profiles.uiowa.edu'],
    ];
  }

}
