<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Utility\Multisite;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the shared drush-alias environment lookup.
 *
 * Covers Multisite::getAliasFile() and getCloudEnvName(): the single source
 * of truth for Acquia Cloud's own name for an application's environment,
 * which is not uniform across applications — uiowa01-06 call the middle
 * environment 'test', uiowa07-09 call it 'stage'.
 *
 * @group unit
 */
class MultisiteUtilityTest extends UnitTestCase {

  /**
   * Scratch directory holding fixture drush alias files.
   *
   * @var string
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-multisite-util-' . uniqid();
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new Filesystem())->remove($this->dir);
    parent::tearDown();
  }

  /**
   * Write a fixture drush alias file for an application.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $test_user
   *   The 'test' environment's user field, e.g. 'uiowa09.stage'.
   */
  private function writeAlias(string $app, string $test_user): void {
    file_put_contents("{$this->dir}/drush/sites/{$app}.site.yml", Yaml::dump([
      'dev' => ['user' => "{$app}.dev"],
      'test' => ['user' => $test_user],
      'prod' => ['user' => "{$app}.prod"],
    ], 4, 2));
  }

  /**
   * An application whose alias's own name for 'test' really is 'test'.
   */
  public function testCloudEnvNameWhenUniform(): void {
    $this->writeAlias('uiowa04', 'uiowa04.test');

    $this->assertSame('test', Multisite::getCloudEnvName($this->dir, 'uiowa04', 'test'));
    $this->assertSame('dev', Multisite::getCloudEnvName($this->dir, 'uiowa04', 'dev'));
    $this->assertSame('prod', Multisite::getCloudEnvName($this->dir, 'uiowa04', 'prod'));
  }

  /**
   * An application that calls the 'test' environment 'stage'.
   */
  public function testCloudEnvNameWhenDivergent(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $this->assertSame('stage', Multisite::getCloudEnvName($this->dir, 'uiowa09', 'test'));
    $this->assertSame('dev', Multisite::getCloudEnvName($this->dir, 'uiowa09', 'dev'));
    $this->assertSame('prod', Multisite::getCloudEnvName($this->dir, 'uiowa09', 'prod'));
  }

  /**
   * A missing alias file resolves to NULL rather than an error.
   */
  public function testCloudEnvNameWithoutAliasFile(): void {
    $this->assertNull(Multisite::getCloudEnvName($this->dir, 'uiowa09', 'test'));
  }

  /**
   * An alias environment with no user field resolves to NULL.
   */
  public function testCloudEnvNameWithoutUserField(): void {
    file_put_contents("{$this->dir}/drush/sites/uiowa09.site.yml", Yaml::dump([
      'test' => ['uri' => 'foo.stage.drupal.uiowa.edu'],
    ]));

    $this->assertNull(Multisite::getCloudEnvName($this->dir, 'uiowa09', 'test'));
  }

  /**
   * A requested environment absent from the alias resolves to NULL.
   */
  public function testCloudEnvNameForUnknownEnvironment(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $this->assertNull(Multisite::getCloudEnvName($this->dir, 'uiowa09', 'staging'));
  }

  /**
   * A missing alias file yields the empty array, not an error.
   */
  public function testGetAliasFileMissing(): void {
    $this->assertSame([], Multisite::getAliasFile($this->dir, 'nope'));
  }

  /**
   * Reads the alias's parsed environment definitions.
   */
  public function testGetAliasFileParsesTheAlias(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $alias = Multisite::getAliasFile($this->dir, 'uiowa09');

    $this->assertSame('uiowa09.stage', $alias['test']['user']);
    $this->assertSame('uiowa09.dev', $alias['dev']['user']);
    $this->assertSame('uiowa09.prod', $alias['prod']['user']);
  }

}
