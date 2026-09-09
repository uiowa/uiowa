<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteDeleteCommand;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\Plan;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the multisite delete command.
 *
 * Covers the manifest flattening that drives site selection, the mount and
 * database facts the command derives from an application, the order its steps
 * run in, and the guards on the shared directory and the branch a commit would
 * land on. The operations the steps call have their own tests.
 *
 * No Acquia API access; git runs against scratch repositories only.
 *
 * @group unit
 */
class MultisiteDeleteTest extends UnitTestCase {

  /**
   * Scratch directory for the file-touching operations.
   *
   * @var string
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-delete-' . uniqid();
    mkdir($this->dir);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    // Recursive: the mount fixtures write a drush/sites tree, not just files.
    (new Filesystem())->remove($this->dir);
    parent::tearDown();
  }

  /**
   * A command instance exposing the protected selection helpers.
   */
  private function command(): MultisiteDeleteCommand {
    return new class('') extends MultisiteDeleteCommand {

      /**
       * Exposes sitesByHost().
       */
      public function pubSitesByHost(array $manifest): array {
        return $this->sitesByHost($manifest);
      }

      /**
       * Exposes addCloudSteps().
       */
      public function pubAddCloudSteps(Plan $plan, array $input, array $cloud, Client $client): void {
        $this->addCloudSteps($plan, $input, $cloud, $client);
      }

      /**
       * Exposes candidateDomains().
       */
      public function pubCandidateDomains(string $host, string $id): array {
        return $this->candidateDomains($host, $id);
      }

    };
  }

  /**
   * A command instance rooted at a scratch directory holding a drush alias.
   */
  private function commandInDir(): MultisiteDeleteCommand {
    return new class($this->dir) extends MultisiteDeleteCommand {

      /**
       * Exposes mountsByEnv().
       */
      public function pubMountsByEnv(string $app): array {
        return $this->mountsByEnv($app);
      }

      /**
       * Exposes cloudChecks().
       */
      public function pubCloudChecks(array $cloud, array $input): array {
        return $this->cloudChecks($cloud, $input);
      }

      /**
       * Exposes buildSteps().
       */
      public function pubBuildSteps(Plan $plan, array $input, array $options, array $cloud, Client $client): void {
        $this->buildSteps($plan, $input, $options, $cloud, $client);
      }

      /**
       * Exposes decide(), which is private but reachable through execute().
       */
      public function pubDecide(string $host, array $sites, array $options): Plan {
        $method = new \ReflectionMethod(MultisiteDeleteCommand::class, 'decide');

        return $method->invoke($this, $host, $sites, $options);
      }

      /**
       * Exposes currentBranch(), from SiteNowCommandsTrait.
       */
      public function pubCurrentBranch(): string {
        return $this->currentBranch();
      }

      /**
       * Exposes gitChecks(), from CommonChecks.
       */
      public function pubGitChecks(string $branch): array {
        return $this->gitChecks($branch, TRUE);
      }

      /**
       * Exposes pushGuidance(), from SiteNowCommandsTrait.
       */
      public function pubPushGuidance(): string {
        return $this->pushGuidance();
      }

    };
  }

  /**
   * Turn the scratch directory into a git repository with one commit.
   *
   * @param string|null $upstream
   *   Remote-tracking branch to configure, or NULL to leave the branch with no
   *   upstream.
   */
  private function initGitRepo(?string $upstream = NULL): void {
    $git = function (array $args) {
      $process = new Process(array_merge(['git'], $args), $this->dir);
      $process->run();

      return $process;
    };

    $git(['init', '--initial-branch=feature-branch', '-q']);
    $git(['config', 'user.email', 'test@example.com']);
    $git(['config', 'user.name', 'Test']);
    file_put_contents($this->dir . '/README', "test\n");
    $git(['add', 'README']);
    $git(['commit', '-q', '-m', 'initial']);

    if ($upstream !== NULL) {
      // A bare repository standing in for origin, so push is purely local.
      $remote = $this->dir . '/remote.git';
      (new Process(['git', 'init', '--bare', '-q', $remote]))->run();
      $git(['remote', 'add', 'origin', $remote]);
      $git(['push', '-q', '--set-upstream', 'origin', $upstream]);
    }
  }

  /**
   * An unauthenticated client; the steps under test are never applied.
   */
  private function client(): Client {
    // Constructing a Connector emits a deprecation on PHP 8.4, which PHPUnit
    // reports as unexpected output and marks the test risky.
    $reporting = error_reporting(error_reporting() & ~E_DEPRECATED);

    try {
      return Client::factory(new Connector(['key' => 'test', 'secret' => 'test']));
    }
    finally {
      error_reporting($reporting);
    }
  }

  /**
   * Cloud facts as gatherCloud() assembles them, with everything present.
   */
  private function cloud(): array {
    return [
      'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
      'mounts' => ['dev' => 'uiowa02.dev', 'test' => 'uiowa02.test', 'prod' => 'uiowa02.prod'],
      'files' => ['dev' => TRUE, 'test' => TRUE, 'prod' => TRUE],
      'database' => TRUE,
      'domains' => [
        ['domain' => 'doomed.prod.drupal.uiowa.edu', 'env' => 'prod', 'env_uuid' => 'env-prod'],
        ['domain' => 'www.doomed.uiowa.edu', 'env' => 'prod', 'env_uuid' => 'env-prod'],
      ],
    ];
  }

  /**
   * Command input as decide() assembles it.
   */
  private function input(): array {
    return [
      'host' => 'doomed.uiowa.edu',
      'dir' => 'doomed.uiowa.edu',
      'id' => 'doomed',
      'db' => 'doomed_uiowa_edu',
      'app' => 'uiowa02',
      'flags' => [],
    ];
  }

  // --- Site selection ---------------------------------------------------------

  /**
   * The manifest flattens to host => app across every application.
   */
  public function testSitesByHostMapsEveryHostToItsApplication() {
    $sites = $this->command()->pubSitesByHost([
      'uiowa02' => ['b.uiowa.edu', 'a.uiowa.edu'],
      'uiowa07' => ['c.uiowa.edu'],
    ]);

    $this->assertSame('uiowa02', $sites['a.uiowa.edu']);
    $this->assertSame('uiowa02', $sites['b.uiowa.edu']);
    $this->assertSame('uiowa07', $sites['c.uiowa.edu']);
  }

  /**
   * Hosts are sorted so the autocomplete list is stable across applications.
   */
  public function testSitesByHostSortsAcrossApplications() {
    $sites = $this->command()->pubSitesByHost([
      'uiowa07' => ['c.uiowa.edu'],
      'uiowa02' => ['b.uiowa.edu', 'a.uiowa.edu'],
    ]);

    $this->assertSame(['a.uiowa.edu', 'b.uiowa.edu', 'c.uiowa.edu'], array_keys($sites));
  }

  /**
   * An empty manifest yields no selectable sites rather than an error.
   */
  public function testSitesByHostHandlesEmptyManifest() {
    $this->assertSame([], $this->command()->pubSitesByHost([]));
  }

  // --- Cloud teardown ---------------------------------------------------------

  /**
   * The cloud steps cover files per environment, the database, and each domain.
   */
  public function testCloudStepsCoverEveryResource() {
    $plan = new Plan('t', [], []);
    $this->command()->pubAddCloudSteps($plan, $this->input(), $this->cloud(), $this->client());

    $labels = array_column($plan->steps(), 'label');

    $this->assertCount(6, $labels);
    $this->assertStringContainsString('/mnt/gfs/uiowa02.dev/sites/doomed.uiowa.edu', $labels[0]);
    $this->assertStringContainsString('/mnt/gfs/uiowa02.test/sites/doomed.uiowa.edu', $labels[1]);
    $this->assertStringContainsString('/mnt/gfs/uiowa02.prod/sites/doomed.uiowa.edu', $labels[2]);
    $this->assertStringContainsString('doomed_uiowa_edu', $labels[3]);
    $this->assertStringContainsString('doomed.prod.drupal.uiowa.edu', $labels[4]);
    $this->assertStringContainsString('www.doomed.uiowa.edu', $labels[5]);
  }

  /**
   * A resource that is already gone produces no step.
   *
   * This is the retry case: a run that died partway leaves some resources
   * deleted, and the plan for the next attempt must show only what is left.
   */
  public function testCloudStepsSkipWhatIsAlreadyGone() {
    $cloud = $this->cloud();
    $cloud['files'] = ['dev' => FALSE, 'test' => FALSE, 'prod' => TRUE];
    $cloud['database'] = FALSE;
    $cloud['domains'] = [];

    $plan = new Plan('t', [], []);
    $this->command()->pubAddCloudSteps($plan, $this->input(), $cloud, $this->client());

    $labels = array_column($plan->steps(), 'label');

    $this->assertCount(1, $labels);
    $this->assertStringContainsString('/mnt/gfs/uiowa02.prod/sites/doomed.uiowa.edu', $labels[0]);
  }

  /**
   * The candidate domains are the site's own, including the www variant.
   */
  public function testCandidateDomainsCoverTheSitesOwnDomains() {
    $candidates = $this->command()->pubCandidateDomains('doomed.uiowa.edu', 'doomed');

    $this->assertSame([
      'doomed.dev.drupal.uiowa.edu',
      'doomed.stage.drupal.uiowa.edu',
      'doomed.prod.drupal.uiowa.edu',
      'doomed.uiowa.edu',
      'www.doomed.uiowa.edu',
    ], $candidates);
  }

  /**
   * The files mount comes from the alias user, not the alias environment name.
   *
   * Applications uiowa07-09 name their middle environment 'stage' while the
   * drush alias still calls it 'test'; the mount path uses the real name.
   */
  public function testMountsResolveTheRealEnvironmentName() {
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
    file_put_contents("{$this->dir}/drush/sites/uiowa09.site.yml", Yaml::dump([
      'local' => ['uri' => 'uiowa.ddev.site'],
      'dev' => ['user' => 'uiowa09.dev'],
      'test' => ['user' => 'uiowa09.stage'],
      'prod' => ['user' => 'uiowa09.prod'],
    ], 4, 2));

    $this->assertSame([
      'dev' => 'uiowa09.dev',
      'test' => 'uiowa09.stage',
      'prod' => 'uiowa09.prod',
    ], $this->commandInDir()->pubMountsByEnv('uiowa09'));
  }

  /**
   * Mounts come from the application's alias, not the site's.
   */
  public function testMountsIgnoreTheSiteAlias() {
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
    file_put_contents("{$this->dir}/drush/sites/doomed.site.yml", Yaml::dump([
      'dev' => ['user' => 'uiowa09.dev'],
      'test' => ['user' => 'uiowa09.stage'],
      'prod' => ['user' => 'uiowa09.prod'],
    ], 4, 2));

    $this->assertSame([], $this->commandInDir()->pubMountsByEnv('uiowa09'));
  }

  /**
   * A missing alias file yields no mounts, which decide() reports as a failure.
   */
  public function testMountsAreEmptyWithoutAnAliasFile() {
    $this->assertSame([], $this->commandInDir()->pubMountsByEnv('uiowa09'));
  }

  /**
   * Evaluate the database-name check against one cloud state and site.
   *
   * @param bool $database
   *   Whether the application still has the site's database.
   * @param string|null $configured
   *   The database drs/config.yml names, or NULL to leave the site directory
   *   absent entirely — the state a run interrupted mid-dismantle leaves
   *   behind.
   *
   * @return \SiteNow\Plan\CheckResult
   *   The check's result.
   */
  private function databaseNameCheck(bool $database, ?string $configured): CheckResult {
    $dir = 'doomed.uiowa.edu';

    if ($configured !== NULL) {
      mkdir("{$this->dir}/docroot/sites/{$dir}/drs", 0777, TRUE);
      file_put_contents(
        "{$this->dir}/docroot/sites/{$dir}/drs/config.yml",
        Yaml::dump(['drupal' => ['db' => ['database' => $configured]]])
      );
    }

    $cloud = [
      'uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
      'mounts' => [],
      'files' => [],
      'database' => $database,
      'domains' => [],
    ];
    $input = [
      'host' => $dir,
      'dir' => $dir,
      'id' => 'doomed',
      'db' => 'doomed_uiowa_edu',
      'app' => 'uiowa09',
    ];

    foreach ($this->commandInDir()->pubCloudChecks($cloud, $input) as $check) {
      if ($check->name === MultisiteDeleteCommand::CHECK_DATABASE_NAME_MATCHES) {
        return $check->evaluate();
      }
    }

    $this->fail('The database-name check was not among the cloud checks.');
  }

  /**
   * A drs/config.yml naming the derived database confirms it.
   */
  public function testDatabaseNameIsConfirmedByDrsConfig() {
    $result = $this->databaseNameCheck(TRUE, 'doomed_uiowa_edu');

    $this->assertSame(CheckStatus::Pass, $result->status);
  }

  /**
   * A drs/config.yml naming a different database refuses the delete.
   *
   * The derived name belongs to a database this site never used, so deleting it
   * would take out one that is still in service.
   */
  public function testDatabaseNameMismatchIsRefused() {
    $result = $this->databaseNameCheck(TRUE, 'someone_elses_database');

    $this->assertSame(CheckStatus::Fail, $result->status);
    $this->assertStringContainsString('someone_elses_database', $result->message);
  }

  /**
   * An unconfirmable name warns when the database is already gone.
   *
   * The drs/config.yml goes with the site directory, so an interrupted run
   * leaves the name unconfirmable. There is no database left to delete, so
   * nothing is at risk and the rerun may finish the repository cleanup.
   */
  public function testUnconfirmableDatabaseAlreadyGoneOnlyWarns() {
    $result = $this->databaseNameCheck(FALSE, NULL);

    $this->assertSame(CheckStatus::Warn, $result->status);
  }

  /**
   * An unconfirmable name refuses while the database is still there.
   */
  public function testUnconfirmableDatabaseStillPresentIsRefused() {
    $result = $this->databaseNameCheck(TRUE, NULL);

    $this->assertSame(CheckStatus::Fail, $result->status);
    $this->assertStringContainsString('git checkout', $result->message);
  }

  /**
   * A run interrupted during the repository removals can be run again.
   *
   * The state left behind: the site directory and the site's drush alias are
   * gone, sites.php no longer aliases the host, but the manifest entry that
   * goes last still names the site. None of that may FAIL, or the cloud
   * resources the interrupted run had not reached become unreachable.
   */
  public function testInterruptedRepositoryRemovalsAllowRerun() {
    mkdir("{$this->dir}/docroot/sites", 0777, TRUE);

    // The application's alias survives a delete; the site's does not.
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
    file_put_contents("{$this->dir}/drush/sites/uiowa09.site.yml", Yaml::dump([
      'dev' => ['user' => 'uiowa09.dev'],
      'test' => ['user' => 'uiowa09.stage'],
      'prod' => ['user' => 'uiowa09.prod'],
    ], 4, 2));

    mkdir("{$this->dir}/sitenow", 0777, TRUE);
    file_put_contents(
      "{$this->dir}/sitenow/applications.yml",
      Yaml::dump(['applications' => ['uiowa' => ['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']]])
    );

    $plan = $this->commandInDir()->pubDecide(
      'doomed.uiowa.edu',
      ['doomed.uiowa.edu' => 'uiowa09'],
      ['no-commit' => TRUE, 'dry-run' => TRUE]
    );

    $checks = $plan->validation['checks'];

    $this->assertSame(
      CheckStatus::Pass,
      $checks[MultisiteDeleteCommand::CHECK_SITE_IN_MANIFEST]['status'],
      'The manifest entry is the anchor and still names the site.'
    );
    $this->assertSame(
      CheckStatus::Warn,
      $checks[MultisiteDeleteCommand::CHECK_SITE_DIR_EXISTS]['status'],
      'An already-removed site directory must not stop a rerun.'
    );
    $this->assertSame(
      CheckStatus::Pass,
      $checks[MultisiteDeleteCommand::CHECK_DRUSH_ALIAS_MOUNTS]['status'],
      'Mounts come from the application alias, which is still present.'
    );
    $this->assertArrayNotHasKey(
      MultisiteDeleteCommand::CHECK_DATABASE_NAME_MATCHES,
      $checks,
      'The database name is confirmed against cloud state, not locally.'
    );
  }

  // --- Step order -------------------------------------------------------------

  /**
   * Every cloud step precedes every repository step, and the commit is last.
   */
  public function testCloudStepsAllPrecedeRepositorySteps() {
    // A site with committed configuration, so the optional step is present.
    mkdir("{$this->dir}/config/sites/doomed.uiowa.edu", 0777, TRUE);

    $plan = new Plan('t', [], []);
    $this->commandInDir()->pubBuildSteps(
      $plan,
      $this->input(),
      ['no-commit' => FALSE],
      $this->cloud(),
      $this->client()
    );

    $labels = array_column($plan->steps(), 'label');

    // Six cloud steps: three mounts, the database, and two domains.
    $cloud_steps = array_slice($labels, 0, 6);
    foreach ($cloud_steps as $label) {
      $this->assertMatchesRegularExpression('/^Delete /', $label, "Expected a cloud delete step, got: {$label}");
    }

    // Nothing after the cloud steps may touch the cloud, and nothing before
    // them may touch the repository.
    $repo_steps = array_slice($labels, 6);
    foreach ($repo_steps as $label) {
      $this->assertStringStartsNotWith('Delete files', $label);
      $this->assertStringNotContainsString('cloud database', $label);
      $this->assertStringNotContainsString('Delete domain', $label);
    }

    $this->assertSame([
      'Remove <info>config/sites/doomed.uiowa.edu</info>',
      'Remove <info>docroot/sites/doomed.uiowa.edu</info>',
      'Remove <info>drush/sites/doomed.site.yml</info>',
      'Remove <info>sites.php</info> directory aliases for <info>doomed.uiowa.edu</info>',
      'Remove <info>doomed.uiowa.edu</info> from <info>sitenow/manifest.yml</info> (app: <info>uiowa02</info>)',
      'Commit "Delete doomed.uiowa.edu multisite on uiowa02"',
    ], $repo_steps);
  }

  /**
   * The manifest removal is the last step that can fail before the commit.
   */
  public function testManifestRemovalIsTheLastRepositoryStep() {
    $plan = new Plan('t', [], []);
    $this->commandInDir()->pubBuildSteps(
      $plan,
      $this->input(),
      ['no-commit' => TRUE],
      $this->cloud(),
      $this->client()
    );

    $labels = array_column($plan->steps(), 'label');

    $this->assertStringContainsString('sitenow/manifest.yml', end($labels));
  }

  /**
   * A site with no committed configuration gets no config removal step.
   */
  public function testNoConfigStepWithoutCommittedConfiguration() {
    $plan = new Plan('t', [], []);
    $this->commandInDir()->pubBuildSteps(
      $plan,
      $this->input(),
      ['no-commit' => TRUE],
      $this->cloud(),
      $this->client()
    );

    $labels = array_column($plan->steps(), 'label');

    foreach ($labels as $label) {
      $this->assertStringNotContainsString('config/sites/', $label);
    }
  }

  // --- The shared default directory -------------------------------------------

  /**
   * A host that resolves to 'default' through sites.php is refused.
   *
   * The demo.sitenow.uiowa.edu host is one: it is in the manifest, and
   * sites.php maps it to the shared directory. Every other check passes for
   * it — the directory exists and its derived database is the application's
   * own — so without this check the plan would delete docroot/sites/default,
   * the application database, and the aliases the application is served on.
   */
  public function testDefaultDirectoryHostIsRefused() {
    mkdir("{$this->dir}/docroot/sites/default", 0777, TRUE);
    file_put_contents(
      "{$this->dir}/docroot/sites/sites.php",
      "<?php\n\$sites['demo.sitenow.uiowa.edu'] = 'default';\n"
    );
    mkdir("{$this->dir}/docroot/sites/default/drs", 0777, TRUE);
    file_put_contents(
      "{$this->dir}/docroot/sites/default/drs/config.yml",
      Yaml::dump(['drupal' => ['db' => ['database' => 'uiowa']]])
    );

    // decide() loads the application registry before it runs the checks.
    mkdir("{$this->dir}/sitenow", 0777, TRUE);
    file_put_contents(
      "{$this->dir}/sitenow/applications.yml",
      Yaml::dump(['applications' => ['uiowa' => ['uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']]])
    );

    $plan = $this->commandInDir()->pubDecide(
      'demo.sitenow.uiowa.edu',
      ['demo.sitenow.uiowa.edu' => 'uiowa'],
      ['no-commit' => TRUE, 'dry-run' => TRUE]
    );

    $check = $plan->validation['checks'][MultisiteDeleteCommand::CHECK_NOT_DEFAULT_SITE] ?? NULL;

    $this->assertNotNull($check, 'The default-directory check did not run.');
    $this->assertSame(CheckStatus::Fail, $check['status']);
    $this->assertTrue($plan->failed());
    $this->assertSame([], $plan->steps(), 'A refused plan must carry no steps.');
  }

  // --- Push guidance ----------------------------------------------------------

  /**
   * The branch is read from the repository root, not the working directory.
   */
  public function testCurrentBranchReadsTheRepository() {
    $this->initGitRepo();

    $this->assertSame('feature-branch', $this->commandInDir()->pubCurrentBranch());
  }

  /**
   * A branch with no upstream is told to set one.
   */
  public function testPushGuidanceSetsUpstreamWhenThereIsNone() {
    $this->initGitRepo();

    $this->assertStringContainsString(
      'git push --set-upstream origin feature-branch',
      $this->commandInDir()->pubPushGuidance()
    );
  }

  /**
   * A branch that already tracks a remote is told to push plainly.
   */
  public function testPushGuidanceOmitsUpstreamWhenAlreadyTracking() {
    $this->initGitRepo('feature-branch');

    $guidance = $this->commandInDir()->pubPushGuidance();

    $this->assertStringContainsString('git push', $guidance);
    $this->assertStringNotContainsString('--set-upstream', $guidance);
  }

  /**
   * Outside a repository the branch is empty rather than a git error string.
   */
  public function testCurrentBranchIsEmptyOutsideGitRepository() {
    $this->assertSame('', $this->commandInDir()->pubCurrentBranch());
  }

  /**
   * A detached HEAD reports no branch rather than the string "HEAD".
   */
  public function testCurrentBranchIsEmptyOnDetachedHead() {
    $this->initGitRepo();
    (new Process(['git', 'checkout', '--detach', '--quiet'], $this->dir))->run();

    $this->assertSame('', $this->commandInDir()->pubCurrentBranch());
  }

  /**
   * The feature-branch check's result for one branch name.
   *
   * @param string $branch
   *   The branch name, or '' for a HEAD that is not on a branch.
   *
   * @return \SiteNow\Plan\CheckResult
   *   The check's result.
   */
  private function featureBranchCheck(string $branch): CheckResult {
    foreach ($this->commandInDir()->pubGitChecks($branch) as $check) {
      if ($check->name === MultisiteDeleteCommand::CHECK_ON_FEATURE_BRANCH) {
        return $check->evaluate();
      }
    }

    $this->fail('The feature-branch check was not among the git checks.');
  }

  /**
   * A HEAD that is not on a branch is refused.
   */
  public function testDetachedHeadFailsTheFeatureBranchCheck() {
    $result = $this->featureBranchCheck('');

    $this->assertSame(CheckStatus::Fail, $result->status);
    $this->assertStringContainsString('not on a branch', $result->message);
  }

  /**
   * A protected branch is still refused by name.
   */
  public function testProtectedBranchFailsTheFeatureBranchCheck() {
    $this->assertSame(CheckStatus::Fail, $this->featureBranchCheck('main')->status);
    $this->assertSame(CheckStatus::Fail, $this->featureBranchCheck('master')->status);
    $this->assertSame(CheckStatus::Fail, $this->featureBranchCheck('develop')->status);
  }

  /**
   * An ordinary feature branch passes, and carries its name forward.
   */
  public function testFeatureBranchPassesTheCheck() {
    $result = $this->featureBranchCheck('delete-foo-site');

    $this->assertSame(CheckStatus::Pass, $result->status);
    $this->assertSame('delete-foo-site', $result->context['branch']);
  }

}
