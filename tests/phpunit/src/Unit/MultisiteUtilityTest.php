<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Utility\Multisite;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the shared drush-alias environment lookup.
 *
 * Covers Multisite::getAliasFile(), getAliasEnv(), and getCloudEnvName(): the
 * single source of truth for an application or site's drush alias, used both
 * to read a full environment definition (FleetRunner's transport/--uri
 * decision) and to resolve Acquia Cloud's own name for an environment, which
 * is not uniform across applications — uiowa01-06 call the middle
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
   * The alias directory Multisite is called with: $this->dir/drush/sites.
   *
   * @var string
   */
  private string $aliasDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-multisite-util-' . uniqid();
    $this->aliasDir = "{$this->dir}/drush/sites";
    mkdir($this->aliasDir, 0777, TRUE);
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
    file_put_contents("{$this->aliasDir}/{$app}.site.yml", Yaml::dump([
      'dev' => ['uri' => "{$app}.dev.drupal.uiowa.edu", 'user' => "{$app}.dev"],
      'test' => ['uri' => "{$app}.stage.drupal.uiowa.edu", 'user' => $test_user],
      'prod' => ['uri' => "{$app}.prod.drupal.uiowa.edu", 'user' => "{$app}.prod"],
    ], 4, 2));
  }

  /**
   * An application whose alias's own name for 'test' really is 'test'.
   */
  public function testCloudEnvNameWhenUniform(): void {
    $this->writeAlias('uiowa04', 'uiowa04.test');

    $this->assertSame('test', Multisite::getCloudEnvName($this->aliasDir, 'uiowa04', 'test'));
    $this->assertSame('dev', Multisite::getCloudEnvName($this->aliasDir, 'uiowa04', 'dev'));
    $this->assertSame('prod', Multisite::getCloudEnvName($this->aliasDir, 'uiowa04', 'prod'));
  }

  /**
   * An application that calls the 'test' environment 'stage'.
   */
  public function testCloudEnvNameWhenDivergent(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $this->assertSame('stage', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'test'));
    $this->assertSame('dev', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'dev'));
    $this->assertSame('prod', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'prod'));
  }

  /**
   * A missing alias file falls back to the requested environment.
   */
  public function testCloudEnvNameWithoutAliasFile(): void {
    $this->assertSame('test', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'test'));
  }

  /**
   * An alias environment with no user field falls back to the requested one.
   */
  public function testCloudEnvNameWithoutUserField(): void {
    file_put_contents("{$this->aliasDir}/uiowa09.site.yml", Yaml::dump([
      'test' => ['uri' => 'foo.stage.drupal.uiowa.edu'],
    ]));

    $this->assertSame('test', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'test'));
  }

  /**
   * A user field without an environment suffix falls back to the requested one.
   */
  public function testCloudEnvNameWithoutUserSuffix(): void {
    $this->writeAlias('uiowa09', 'uiowa09');

    $this->assertSame('test', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'test'));
  }

  /**
   * A requested environment absent from the alias falls back to itself.
   */
  public function testCloudEnvNameForUnknownEnvironment(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $this->assertSame('staging', Multisite::getCloudEnvName($this->aliasDir, 'uiowa09', 'staging'));
  }

  /**
   * A missing alias file yields the empty array, not an error.
   */
  public function testGetAliasFileMissing(): void {
    $this->assertSame([], Multisite::getAliasFile($this->aliasDir, 'nope'));
  }

  /**
   * Reads the alias's parsed environment definitions.
   */
  public function testGetAliasFileParsesTheAlias(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $alias = Multisite::getAliasFile($this->aliasDir, 'uiowa09');

    $this->assertSame('uiowa09.stage', $alias['test']['user']);
    $this->assertSame('uiowa09.dev', $alias['dev']['user']);
    $this->assertSame('uiowa09.prod', $alias['prod']['user']);
  }

  /**
   * A malformed alias file yields the empty array rather than crashing.
   *
   * FleetRunner's transport decision reads this for up to four figures of
   * sites; one broken file must not take the whole run down.
   */
  public function testGetAliasFileToleratesMalformedYaml(): void {
    file_put_contents("{$this->aliasDir}/broken.site.yml", "test:\n  user: 'unclosed\n");

    $this->assertSame([], Multisite::getAliasFile($this->aliasDir, 'broken'));
  }

  /**
   * The whole environment definition is returned, not just a name.
   *
   * FleetRunner needs 'uri' alongside 'user': a local job's --uri must be
   * that environment's own hostname, which a name-only lookup can't supply.
   */
  public function testGetAliasEnvReturnsTheFullDefinition(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $env = Multisite::getAliasEnv($this->aliasDir, 'uiowa09', 'test');

    $this->assertSame('uiowa09.stage', $env['user']);
    $this->assertSame('uiowa09.stage.drupal.uiowa.edu', $env['uri']);
  }

  /**
   * A requested environment absent from the alias resolves to NULL.
   */
  public function testGetAliasEnvForUnknownEnvironment(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $this->assertNull(Multisite::getAliasEnv($this->aliasDir, 'uiowa09', 'staging'));
  }

  /**
   * A missing alias file resolves to NULL.
   */
  public function testGetAliasEnvWithoutAliasFile(): void {
    $this->assertNull(Multisite::getAliasEnv($this->aliasDir, 'nope', 'test'));
  }

  /**
   * A parsed alias file is cached per (directory, name), not re-read.
   *
   * A fleet run can ask for the same file hundreds of times; the second read
   * here would return nothing (or throw) if it hit the filesystem again,
   * since the file is gone by then.
   */
  public function testGetAliasFileIsCachedPerDirectoryAndName(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $first = Multisite::getAliasFile($this->aliasDir, 'uiowa09');
    unlink("{$this->aliasDir}/uiowa09.site.yml");
    $second = Multisite::getAliasFile($this->aliasDir, 'uiowa09');

    $this->assertSame($first, $second);
    $this->assertNotSame([], $second, 'The cached read must still return the alias, not fall through to "missing".');
  }

  /**
   * The cache is keyed by directory as well as name.
   *
   * Two different directories holding a same-named alias file (e.g. a test's
   * scratch directory versus another) must not collide.
   */
  public function testGetAliasFileCacheKeyIncludesTheDirectory(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $other = "{$this->dir}/other-drush-sites";
    mkdir($other, 0777, TRUE);
    file_put_contents("{$other}/uiowa09.site.yml", Yaml::dump([
      'test' => ['user' => 'uiowa09.test'],
    ]));

    $this->assertSame('uiowa09.stage', Multisite::getAliasEnv($this->aliasDir, 'uiowa09', 'test')['user']);
    $this->assertSame('uiowa09.test', Multisite::getAliasEnv($other, 'uiowa09', 'test')['user']);
  }

}
