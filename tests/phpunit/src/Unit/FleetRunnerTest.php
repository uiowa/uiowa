<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Process\FleetRunner;

/**
 * Unit tests for FleetRunner's manifest selection and job building.
 *
 * Covers select() filtering/exclusion against a fixture manifest, the drush
 * argv structure buildJobs() produces (including the per-invocation SSH
 * multiplexing option), the local-vs-SSH transport rule as the fixture
 * aliases define it, and the defaultConcurrency() scaling rule. No drush or
 * SSH.
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
   * Path to the fixture drush alias directory written for each test.
   *
   * The transport rule reads each site's alias for the Acquia site user and
   * the environment's own hostname, so these files carry the shapes that
   * matter: uiowa02, where alias key 'test' is environment 'test', and
   * uiowa03, where it is 'stage'.
   *
   * @var string
   */
  protected string $aliasDir;

  /**
   * Stand-in repository root.
   *
   * Only the drush binary path is derived from it, and that path is never
   * stat'd, so the directory does not need to exist. Named to avoid colliding
   * with UnitTestCase::$root.
   *
   * @var string
   */
  protected string $repoRoot = '/repo';

  /**
   * The argv prefix the runner is expected to invoke drush through.
   *
   * @var array<int, string>
   */
  protected array $drush = [PHP_BINARY, '-d', 'display_errors=stderr', '/repo/vendor/bin/drush'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    putenv('AH_SITE_GROUP');
    putenv('AH_SITE_ENVIRONMENT');
    $this->manifest = tempnam(sys_get_temp_dir(), 'manifest');
    file_put_contents($this->manifest, <<<YAML
uiowa02:
  - vote.uiowa.edu
  - tippie.uiowa.edu
uiowa03:
  - accessibility.uiowa.edu
YAML);

    $this->aliasDir = tempnam(sys_get_temp_dir(), 'aliases');
    unlink($this->aliasDir);
    mkdir($this->aliasDir);

    foreach ([
      'vote' => ['app' => 'uiowa02', 'test_env' => 'test'],
      'tippie' => ['app' => 'uiowa02', 'test_env' => 'test'],
      'accessibility' => ['app' => 'uiowa03', 'test_env' => 'stage'],
    ] as $id => $site) {
      ['app' => $app, 'test_env' => $test_env] = $site;
      file_put_contents("{$this->aliasDir}/{$id}.site.yml", <<<YAML
dev:
  uri: {$id}.dev.drupal.uiowa.edu
  user: {$app}.dev
prod:
  uri: {$id}.uiowa.edu
  user: {$app}.prod
test:
  uri: {$id}.stage.drupal.uiowa.edu
  user: {$app}.{$test_env}
YAML);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    @unlink($this->manifest);
    foreach (glob("{$this->aliasDir}/*.site.yml") ?: [] as $alias) {
      @unlink($alias);
    }
    @rmdir($this->aliasDir);
    putenv('AH_SITE_GROUP');
    putenv('AH_SITE_ENVIRONMENT');
    parent::tearDown();
  }

  /**
   * An empty app filter selects every app in the manifest.
   */
  public function testSelectAll(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

    $this->assertSame([
      'uiowa02' => ['vote.uiowa.edu', 'tippie.uiowa.edu'],
      'uiowa03' => ['accessibility.uiowa.edu'],
    ], $runner->select());
  }

  /**
   * Filtering by app returns only that app's sites.
   */
  public function testSelectByApp(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

    $this->assertSame(
      ['uiowa03' => ['accessibility.uiowa.edu']],
      $runner->select(['uiowa03'])
    );
  }

  /**
   * An unknown app name throws with the name in the message.
   */
  public function testSelectUnknownAppThrows(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('nope');
    $runner->select(['uiowa03', 'nope']);
  }

  /**
   * Excluded domains are removed; a fully excluded app drops out entirely.
   */
  public function testSelectExclude(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

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
    $runner = new FleetRunner($this->repoRoot, '/nonexistent/manifest.yml');

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
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

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
    $runner = new FleetRunner($this->repoRoot, $this->manifest);
    ['jobs' => $jobs, 'groups' => $groups] = $runner->buildJobs($runner->select(), ['cr']);

    $this->assertSame(
      array_merge($this->drush, ['@vote.prod', '--ssh-options=-o PasswordAuthentication=no ' . FleetRunner::MUX_OPTIONS, 'cr']),
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
    $runner = new FleetRunner($this->repoRoot, $this->manifest);
    $selection = $runner->select(['uiowa03']);
    ['jobs' => $jobs] = $runner->buildJobs($selection, ['sql:query', 'SELECT COUNT(*) FROM node'], 'dev');

    $this->assertSame(array_merge($this->drush, [
      '@accessibility.dev',
      '--ssh-options=-o PasswordAuthentication=no ' . FleetRunner::MUX_OPTIONS,
      'sql:query',
      'SELECT COUNT(*) FROM node',
    ]), $jobs['accessibility.uiowa.edu']);
  }

  /**
   * A site on this application and environment is addressed locally.
   *
   * The local job carries no alias and no ssh options.
   */
  public function testBuildJobsLocalOnMatchingAppAndEnv(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'prod', $this->aliasDir);
    ['jobs' => $jobs] = $runner->buildJobs($runner->select(), ['cr'], 'prod');

    $this->assertSame(
      array_merge($this->drush, ['--root=/repo/docroot', '--uri=vote.uiowa.edu', 'cr']),
      $jobs['vote.uiowa.edu']
    );

    // A different application is still a remote target.
    $this->assertContains('@accessibility.prod', $jobs['accessibility.uiowa.edu']);
  }

  /**
   * A different environment on the running application stays remote.
   */
  public function testBuildJobsRemoteOnOtherEnv(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'prod', $this->aliasDir);
    ['jobs' => $jobs] = $runner->buildJobs($runner->select(['uiowa02']), ['cr'], 'dev');

    $this->assertContains('@vote.dev', $jobs['vote.uiowa.edu']);
  }

  /**
   * Off Acquia, every job is remote.
   */
  public function testRunsLocallyFalseOffAcquia(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, NULL, NULL, $this->aliasDir);

    $this->assertFalse($runner->runsLocally('uiowa02', 'vote.uiowa.edu', 'prod'));
    $this->assertTrue($runner->hasRemoteJobs($runner->select(), 'prod'));
  }

  /**
   * A selection entirely on the running app and env needs no SSH.
   */
  public function testHasRemoteJobs(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'prod', $this->aliasDir);

    $this->assertFalse($runner->hasRemoteJobs($runner->select(['uiowa02']), 'prod'));
    $this->assertTrue($runner->hasRemoteJobs($runner->select(['uiowa02']), 'dev'));
    $this->assertTrue($runner->hasRemoteJobs($runner->select(), 'prod'));
    $this->assertFalse($runner->hasRemoteJobs([], 'prod'));
  }

  /**
   * The local app and env default to the Acquia Cloud environment variables.
   */
  public function testLocalTargetDefaultsToAcquiaEnvironment(): void {
    putenv('AH_SITE_GROUP=uiowa02');
    putenv('AH_SITE_ENVIRONMENT=test');
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, NULL, NULL, $this->aliasDir);

    $this->assertTrue($runner->runsLocally('uiowa02', 'vote.uiowa.edu', 'test'));
    $this->assertFalse($runner->runsLocally('uiowa03', 'accessibility.uiowa.edu', 'test'));
  }

  /**
   * The alias key and the Acquia environment name don't have to match.
   *
   * Alias key 'test' is environment 'stage' on uiowa07 through uiowa09
   * (uiowa03 in the fixture). Comparing the key to AH_SITE_ENVIRONMENT sends
   * the running environment's own sites over SSH on exactly those
   * applications, which is where the site-count job had no keys to use.
   */
  public function testRunsLocallyWhenAliasKeyAndEnvironmentNameDiffer(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa03', 'stage', $this->aliasDir);

    $this->assertTrue($runner->runsLocally('uiowa03', 'accessibility.uiowa.edu', 'test'));
    $this->assertFalse($runner->hasRemoteJobs($runner->select(['uiowa03']), 'test'));

    // The same alias key on an application that does name it 'test'.
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'test', $this->aliasDir);

    $this->assertTrue($runner->runsLocally('uiowa02', 'vote.uiowa.edu', 'test'));
  }

  /**
   * A local job's --uri is the alias hostname, not the manifest domain.
   *
   * The manifest holds production domains; dev and test have hostnames of
   * their own. A job carrying the production domain would give the site a
   * production base_url while reading a non-production database.
   */
  public function testBuildJobsLocalUriComesFromAlias(): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'dev', $this->aliasDir);
    ['jobs' => $jobs] = $runner->buildJobs($runner->select(['uiowa02']), ['cr'], 'dev');

    $this->assertSame(
      array_merge($this->drush, ['--root=/repo/docroot', '--uri=vote.dev.drupal.uiowa.edu', 'cr']),
      $jobs['vote.uiowa.edu']
    );
  }

  /**
   * A site with no readable alias falls back to the SSH transport.
   *
   * Nothing then claims the job is local, and drush reports the missing alias
   * itself.
   */
  public function testBuildJobsRemoteWhenAliasUnreadable(): void {
    unlink("{$this->aliasDir}/vote.site.yml");
    $runner = new FleetRunner($this->repoRoot, $this->manifest, NULL, 'uiowa02', 'prod', $this->aliasDir);
    ['jobs' => $jobs] = $runner->buildJobs($runner->select(['uiowa02']), ['cr'], 'prod');

    $this->assertContains('@vote.prod', $jobs['vote.uiowa.edu']);
    $this->assertTrue($runner->hasRemoteJobs($runner->select(['uiowa02']), 'prod'));
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
    $runner = new FleetRunner($this->repoRoot, $this->manifest, $drush_config);

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
    $runner = new FleetRunner($this->repoRoot, $this->manifest, $drush_config);

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
   * Build a runner whose canary drush call is faked.
   *
   * @param array $result
   *   The canned {exit, output, error} result for the canary.
   * @param array|null $captured
   *   By-ref out-param receiving [site, argv] the canary was invoked with.
   *
   * @return \SiteNow\Process\FleetRunner
   *   The runner.
   */
  protected function runnerWithCanary(array $result, ?array &$captured = NULL): FleetRunner {
    return new class($this->repoRoot, $this->manifest, $result, $captured) extends FleetRunner {

      public function __construct(
        string $root,
        string $manifest,
        private array $result,
        private ?array &$captured,
      ) {
        parent::__construct($root, $manifest);
      }

      /**
       * {@inheritdoc}
       */
      protected function runCanary(string $site, array $argv): array {
        $this->captured = [$site, $argv];

        return $this->result;
      }

    };
  }

  /**
   * The preflight checks the first site via `drush help` and passes on 0.
   */
  public function testPreflightPasses(): void {
    $runner = $this->runnerWithCanary(['exit' => 0, 'output' => '', 'error' => ''], $captured);

    $this->assertNull($runner->preflight($runner->select(), 'cr'));
    [$site, $argv] = $captured;
    $this->assertSame('vote.uiowa.edu', $site);
    $this->assertSame($this->drush, array_slice($argv, 0, count($this->drush)));
    $this->assertSame('@vote.prod', $argv[count($this->drush)]);
    $this->assertSame(['help', 'cr'], array_slice($argv, -2));
  }

  /**
   * A failing canary reports the site and the drush result.
   */
  public function testPreflightFails(): void {
    $runner = $this->runnerWithCanary(['exit' => 1, 'output' => '', 'error' => 'Command cr was not found.']);

    $result = $runner->preflight($runner->select(), 'cr');
    $this->assertSame('vote.uiowa.edu', $result['site']);
    $this->assertSame(1, $result['exit']);
  }

  /**
   * An empty selection has no canary to check and passes.
   */
  public function testPreflightEmptySelectionPasses(): void {
    $runner = $this->runnerWithCanary(['exit' => 1, 'output' => '', 'error' => '']);

    $this->assertNull($runner->preflight([], 'cr'));
  }

  /**
   * Default concurrency scales per app and caps at the ceiling.
   *
   * @dataProvider concurrencyProvider
   */
  public function testDefaultConcurrency(int $app_count, int $expected): void {
    $runner = new FleetRunner($this->repoRoot, $this->manifest);

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
