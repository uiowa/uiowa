<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\Credentials;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the developer credentials reader.
 *
 * @group unit
 */
class CredentialsTest extends UnitTestCase {

  /**
   * Scratch directory holding the credentials fixture.
   *
   * @var string
   */
  private string $dir;

  /**
   * Path to the credentials fixture.
   *
   * @var string
   */
  private string $path;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-credentials-' . uniqid();
    mkdir($this->dir);
    $this->path = $this->dir . '/credentials.yml';
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach (glob($this->dir . '/*') ?: [] as $file) {
      unlink($file);
    }
    if (is_dir($this->dir)) {
      rmdir($this->dir);
    }
    parent::tearDown();
  }

  /**
   * Write a credentials fixture and return a reader for it.
   */
  private function credentials(array $credentials): Credentials {
    file_put_contents($this->path, Yaml::dump($credentials, 8, 2, Yaml::DUMP_OBJECT_AS_MAP));
    return new Credentials($this->path);
  }

  /**
   * Acquia key and secret are read from the file.
   */
  public function testReadsAcquiaKeyAndSecret(): void {
    $credentials = $this->credentials([
      'acquia' => ['key' => 'the-key', 'secret' => 'the-secret'],
    ]);

    $this->assertSame(['key' => 'the-key', 'secret' => 'the-secret'], $credentials->acquia());
    $this->assertTrue($credentials->hasAcquia());
  }

  /**
   * An absent file reads as unconfigured rather than throwing.
   */
  public function testAbsentFileIsUnconfigured(): void {
    $credentials = new Credentials($this->dir . '/does-not-exist.yml');

    $this->assertSame(['key' => NULL, 'secret' => NULL], $credentials->acquia());
    $this->assertFalse($credentials->hasAcquia());
  }

  /**
   * A file with no acquia section reads as unconfigured.
   */
  public function testMissingAcquiaSectionIsUnconfigured(): void {
    $credentials = $this->credentials(['something' => ['else' => 'value']]);

    $this->assertSame(['key' => NULL, 'secret' => NULL], $credentials->acquia());
    $this->assertFalse($credentials->hasAcquia());
  }

  /**
   * Half-configured credentials do not pass as configured.
   */
  public function testPartialCredentialsAreNotConfigured(): void {
    $this->assertFalse($this->credentials([
      'acquia' => ['key' => 'the-key'],
    ])->hasAcquia());

    $this->assertFalse($this->credentials([
      'acquia' => ['key' => 'the-key', 'secret' => ''],
    ])->hasAcquia());
  }

  /**
   * The default path is the credentials file in the user's home directory.
   */
  public function testDefaultPathIsUnderHome(): void {
    $this->assertSame(getenv('HOME') . '/.sitenow/credentials.yml', Credentials::defaultPath());
  }

}
