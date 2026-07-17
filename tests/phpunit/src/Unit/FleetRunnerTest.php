<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Process\FleetRunner;

/**
 * Unit tests for FleetRunner's manifest selection and job building.
 *
 * Covers select() filtering/exclusion against a fixture manifest, the drush
 * argv structure buildJobs() produces (including the per-invocation SSH
 * multiplexing option), and the defaultConcurrency() scaling rule. No drush
 * or SSH.
 *
 * @group unit
 */
class FleetRunnerTest extends UnitTestCase {

  /**
   * Path to the fixture manifest written for each test.
   *
   * @var string
   */
  protected string $manifest;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->manifest = tempnam(sys_get_temp_dir(), 'manifest');
    file_put_contents($this->manifest, <<<YAML
uiowa02:
  - vote.uiowa.edu
  - tippie.uiowa.edu
uiowa03:
  - accessibility.uiowa.edu
YAML);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->manifest);
    parent::tearDown();
  }

  /**
   * An empty app filter selects every app in the manifest.
   */
  public function testSelectAll(): void {
    $runner = new FleetRunner($this->manifest);

    $this->assertSame([
      'uiowa02' => ['vote.uiowa.edu', 'tippie.uiowa.edu'],
      'uiowa03' => ['accessibility.uiowa.edu'],
    ], $runner->select());
  }

  /**
   * Filtering by app returns only that app's sites.
   */
  public function testSelectByApp(): void {
    $runner = new FleetRunner($this->manifest);

    $this->assertSame(
      ['uiowa03' => ['accessibility.uiowa.edu']],
      $runner->select(['uiowa03'])
    );
  }

  /**
   * An unknown app name throws with the name in the message.
   */
  public function testSelectUnknownAppThrows(): void {
    $runner = new FleetRunner($this->manifest);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('nope');
    $runner->select(['uiowa03', 'nope']);
  }

  /**
   * Excluded domains are removed; a fully excluded app drops out entirely.
   */
  public function testSelectExclude(): void {
    $runner = new FleetRunner($this->manifest);

    $this->assertSame(
      ['uiowa02' => ['tippie.uiowa.edu'], 'uiowa03' => ['accessibility.uiowa.edu']],
      $runner->select([], ['vote.uiowa.edu'])
    );
    $this->assertSame(
      ['uiowa03' => ['accessibility.uiowa.edu']],
      $runner->select([], ['vote.uiowa.edu', 'tippie.uiowa.edu'])
    );
  }

  /**
   * A missing manifest file throws instead of selecting nothing.
   */
  public function testSelectMissingManifestThrows(): void {
    $runner = new FleetRunner('/nonexistent/manifest.yml');

    $this->expectException(\RuntimeException::class);
    $runner->select();
  }

  /**
   * A manifest that parses to the wrong shape throws a clean error.
   *
   * Malformed YAML already throws (ParseException is a \RuntimeException);
   * these are the valid-YAML-wrong-shape cases that would otherwise fatal
   * with a TypeError.
   *
   * @dataProvider malformedManifestProvider
   */
  public function testSelectMalformedManifestThrows(string $content): void {
    file_put_contents($this->manifest, $content);
    $runner = new FleetRunner($this->manifest);

    $this->expectException(\RuntimeException::class);
    $runner->select();
  }

  /**
   * Valid-YAML manifests with the wrong shape.
   */
  public static function malformedManifestProvider(): array {
    return [
      'scalar' => ['just a string'],
      'list instead of map' => ["- vote.uiowa.edu\n- tippie.uiowa.edu"],
      'app with scalar value' => ["uiowa02: vote.uiowa.edu"],
    ];
  }

  /**
   * Jobs are argv arrays: drush, alias, ssh options, then the command.
   *
   * The --ssh-options element scopes SSH multiplexing to fleet invocations
   * only, and must ride every job as a single argv element.
   */
  public function testBuildJobs(): void {
    $runner = new FleetRunner($this->manifest);
    ['jobs' => $jobs, 'groups' => $groups] = $runner->buildJobs($runner->select(), ['cr']);

    $this->assertSame(
      ['drush', '@vote.prod', '--ssh-options=-o PasswordAuthentication=no ' . FleetRunner::MUX_OPTIONS, 'cr'],
      $jobs['vote.uiowa.edu']
    );
    $this->assertSame([
      'vote.uiowa.edu' => 'uiowa02',
      'tippie.uiowa.edu' => 'uiowa02',
      'accessibility.uiowa.edu' => 'uiowa03',
    ], $groups);
  }

  /**
   * The env suffix and multi-element drush args pass through intact.
   *
   * The env lands in the alias suffix, and each drush arg stays its own
   * argv element (no shell-style joining).
   */
  public function testBuildJobsEnvAndArgPassthrough(): void {
    $runner = new FleetRunner($this->manifest);
    $selection = $runner->select(['uiowa03']);
    ['jobs' => $jobs] = $runner->buildJobs($selection, ['sql:query', 'SELECT COUNT(*) FROM node'], 'dev');

    $this->assertSame([
      'drush',
      '@accessibility.dev',
      '--ssh-options=-o PasswordAuthentication=no ' . FleetRunner::MUX_OPTIONS,
      'sql:query',
      'SELECT COUNT(*) FROM node',
    ], $jobs['accessibility.uiowa.edu']);
  }

  /**
   * Fleet ssh options inherit the repo-wide drush.yml ssh.options base.
   *
   * Drush's --ssh-options replaces the configured value rather than
   * appending, so the base (agent forwarding etc.) must be restated or
   * fleet jobs silently lose it.
   */
  public function testSshOptionsInheritDrushConfig(): void {
    $drush_config = tempnam(sys_get_temp_dir(), 'drush');
    file_put_contents($drush_config, "ssh:\n  options: '-A -o PasswordAuthentication=no -p 22'\n");
    $runner = new FleetRunner($this->manifest, $drush_config);

    $this->assertSame(
      '-A -o PasswordAuthentication=no -p 22 ' . FleetRunner::MUX_OPTIONS,
      $runner->sshOptions()
    );
    unlink($drush_config);
  }

  /**
   * With no usable drush config, ssh options fall back to drush's default.
   *
   * @dataProvider drushConfigFallbackProvider
   */
  public function testSshOptionsFallback(?string $content): void {
    $drush_config = NULL;
    if ($content !== NULL) {
      $drush_config = tempnam(sys_get_temp_dir(), 'drush');
      file_put_contents($drush_config, $content);
    }
    $runner = new FleetRunner($this->manifest, $drush_config);

    $this->assertSame(
      '-o PasswordAuthentication=no ' . FleetRunner::MUX_OPTIONS,
      $runner->sshOptions()
    );
    if ($drush_config !== NULL) {
      unlink($drush_config);
    }
  }

  /**
   * Drush configs that provide no usable ssh.options.
   */
  public static function drushConfigFallbackProvider(): array {
    return [
      'no config path' => [NULL],
      'no ssh.options key' => ["drush:\n  paths:\n    backup-dir: /tmp\n"],
      'malformed yaml' => ["ssh:\n  options: 'unclosed\n"],
    ];
  }

  /**
   * Default concurrency scales per app and caps at the ceiling.
   *
   * @dataProvider concurrencyProvider
   */
  public function testDefaultConcurrency(int $app_count, int $expected): void {
    $runner = new FleetRunner($this->manifest);

    $this->assertSame($expected, $runner->defaultConcurrency($app_count));
  }

  /**
   * Cases for concurrency scaling.
   */
  public static function concurrencyProvider(): array {
    return [
      'one app' => [1, FleetRunner::PER_APP_CAP],
      'two apps' => [2, 2 * FleetRunner::PER_APP_CAP],
      'four apps hits ceiling' => [4, FleetRunner::MAX_CONCURRENCY],
      'ten apps stays at ceiling' => [10, FleetRunner::MAX_CONCURRENCY],
      'zero apps treated as one' => [0, FleetRunner::PER_APP_CAP],
    ];
  }

}
