<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use Drupal\Tests\UnitTestCase;
use SiteNow\Acquia\CloudApi;
use SiteNow\Acquia\Mounts;
use SiteNow\Command\MultisiteDeleteCommand;
use SiteNow\Config\SitesPhp;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\Plan;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the multisite delete command and its operations.
 *
 * Covers the manifest flattening that drives site selection, the two repo-side
 * removal operations, and the notification-link parsing the cloud waiter uses.
 * No Acquia API or git access.
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
      public function pubMountsByEnv(string $id): array {
        return $this->mountsByEnv($id);
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
   *   upstream. The remote is a bare repository in the scratch tree, so nothing
   *   reaches the network.
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
   *
   * Constructing a Connector emits a deprecation from the Acquia SDK on PHP
   * 8.4, which PHPUnit reports as unexpected output; mask it around the one
   * call rather than letting it mark every test risky.
   */
  private function client(): Client {
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

  /**
   * Build a links object shaped like an OperationResponse's.
   */
  private function links(string $href): object {
    return (object) ['notification' => (object) ['href' => $href]];
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

  // --- sites.php removal ------------------------------------------------------

  /**
   * Write a sites.php fixture holding alias blocks for two sites.
   */
  private function sitesPhp(): string {
    $path = $this->dir . '/sites.php';
    file_put_contents($path, <<<'EOD'
<?php

// Directory aliases for doomed.uiowa.edu.
$sites['doomed.uiowa.ddev.site'] = 'doomed.uiowa.edu';
$sites['doomed.dev.drupal.uiowa.edu'] = 'doomed.uiowa.edu';
$sites['doomed.stage.drupal.uiowa.edu'] = 'doomed.uiowa.edu';
$sites['doomed.prod.drupal.uiowa.edu'] = 'doomed.uiowa.edu';

// Directory aliases for keeper.uiowa.edu.
$sites['keeper.uiowa.ddev.site'] = 'keeper.uiowa.edu';
$sites['keeper.prod.drupal.uiowa.edu'] = 'keeper.uiowa.edu';

EOD);
    return $path;
  }

  /**
   * Every alias for the site goes, along with its marker comment.
   */
  public function testRemoveAliasesStripsTheSiteBlock() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');

    $contents = file_get_contents($path);
    $this->assertStringNotContainsString('doomed', $contents);
  }

  /**
   * Other sites' aliases survive untouched.
   */
  public function testRemoveAliasesLeavesOtherSites() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');

    $contents = file_get_contents($path);
    $this->assertStringContainsString("// Directory aliases for keeper.uiowa.edu.", $contents);
    $this->assertStringContainsString("\$sites['keeper.uiowa.ddev.site'] = 'keeper.uiowa.edu';", $contents);
    $this->assertStringContainsString("\$sites['keeper.prod.drupal.uiowa.edu'] = 'keeper.uiowa.edu';", $contents);
  }

  /**
   * An alias added by hand is removed too, unlike a literal block replacement.
   */
  public function testRemoveAliasesStripsAliasesOutsideTheBlock() {
    $path = $this->sitesPhp();
    file_put_contents($path, "\n\$sites['vanity.uiowa.edu'] = 'doomed.uiowa.edu';\n", FILE_APPEND);

    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');

    $this->assertStringNotContainsString('vanity.uiowa.edu', file_get_contents($path));
  }

  /**
   * Running twice leaves the same file, so a retry is safe.
   */
  public function testRemoveAliasesIsIdempotent() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');
    $once = file_get_contents($path);
    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');

    $this->assertSame($once, file_get_contents($path));
  }

  /**
   * Removal does not leave a widening run of blank lines behind.
   */
  public function testRemoveAliasesCollapsesBlankLines() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->removeAliases('doomed.uiowa.edu');

    $this->assertDoesNotMatchRegularExpression("/\n{3,}/", file_get_contents($path));
  }

  // --- Cloud teardown ---------------------------------------------------------

  /**
   * The cloud steps cover files per environment, the database, and each domain.
   *
   * Files first: they are the only resource that cannot be recreated from the
   * repository, so they come off before anything the run could still retry.
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
   *
   * The www form is registered for many sites and is not derivable from the
   * identifier, so it has to be listed explicitly. The local ddev domain is
   * excluded: it exists only in sites.php.
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
    file_put_contents("{$this->dir}/drush/sites/doomed.site.yml", Yaml::dump([
      'local' => ['uri' => 'doomed.uiowa.ddev.site'],
      'dev' => ['user' => 'uiowa09.dev'],
      'test' => ['user' => 'uiowa09.stage'],
      'prod' => ['user' => 'uiowa09.prod'],
    ], 4, 2));

    $this->assertSame([
      'dev' => 'uiowa09.dev',
      'test' => 'uiowa09.stage',
      'prod' => 'uiowa09.prod',
    ], $this->commandInDir()->pubMountsByEnv('doomed'));
  }

  /**
   * A missing alias file yields no mounts, which decide() reports as a failure.
   */
  public function testMountsAreEmptyWithoutAnAliasFile() {
    $this->assertSame([], $this->commandInDir()->pubMountsByEnv('doomed'));
  }

  // --- Cloud files deletion ---------------------------------------------------

  /**
   * The deleted path is the site's own directory on the environment's mount.
   */
  public function testFilesDeleteTargetsTheWholeSiteDirectory() {
    $mounts = new Mounts('/repo');

    $this->assertSame(
      '/mnt/gfs/uiowa09.stage/sites/doomed.uiowa.edu',
      $mounts->siteDirectory('uiowa09.stage', 'doomed.uiowa.edu')
    );
  }

  /**
   * A directory value that would widen the rm is refused before any command.
   *
   * The path is refused where it is built, so an unsafe value cannot reach a
   * remote command by any route.
   *
   * @dataProvider unsafeDirectories
   */
  public function testFilesDeleteRefusesUnsafeDirectories(string $directory) {
    $this->expectException(\InvalidArgumentException::class);
    (new Mounts('/repo'))->siteDirectory('uiowa09.prod', $directory);
  }

  /**
   * Directory values that must never be interpolated into an rm -rf.
   */
  public static function unsafeDirectories(): array {
    return [
      'empty' => [''],
      'current directory' => ['.'],
      'parent traversal' => ['../doomed.uiowa.edu'],
      'wildcard' => ['*'],
      'nested path' => ['doomed.uiowa.edu/files'],
      'trailing slash' => ['doomed.uiowa.edu/'],
      'command chain' => ['doomed.uiowa.edu; rm -rf /'],
    ];
  }

  /**
   * A malformed mount is refused for the same reason.
   */
  public function testFilesDeleteRefusesUnsafeMounts() {
    $this->expectException(\InvalidArgumentException::class);
    (new Mounts('/repo'))->siteDirectory('/mnt/gfs', 'doomed.uiowa.edu');
  }

  // --- Cloud operation notification links -------------------------------------

  /**
   * The notification UUID is read from the operation's link.
   */
  public function testNotificationUuidParsesTheLink() {
    $uuid = '3d87eca7-89d1-47e2-84db-bc7ad52a9363';
    $links = $this->links("https://cloud.acquia.com/api/notifications/{$uuid}");

    $this->assertSame($uuid, CloudApi::notificationUuid($links));
  }

  /**
   * An operation with no links cannot be confirmed.
   */
  public function testNotificationUuidRejectsMissingLinks() {
    $this->assertNull(CloudApi::notificationUuid(NULL));
    $this->assertNull(CloudApi::notificationUuid((object) []));
  }

  /**
   * A link that does not end in a UUID is refused rather than requested.
   */
  public function testNotificationUuidRejectsNonUuidPath() {
    $this->assertNull(CloudApi::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/')
    ));
    $this->assertNull(CloudApi::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/not-a-uuid')
    ));
  }

  // --- Step order -------------------------------------------------------------

  /**
   * Every cloud step precedes every repository step, and the commit is last.
   *
   * This is the property the whole command rests on: the repository is only
   * touched once the cloud teardown has been confirmed, so a cloud failure
   * leaves the working tree recoverable. The steps run in list order, which
   * makes their order the guarantee.
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
      'Remove <info>doomed.uiowa.edu</info> from <info>blt/manifest.yml</info> (app: <info>uiowa02</info>)',
      'Commit "Delete doomed.uiowa.edu multisite on uiowa02"',
    ], $repo_steps);
  }

  /**
   * The manifest removal is the last step that can fail before the commit.
   *
   * Until it runs the site is still selectable, which is what makes a failed
   * run retryable against the same site.
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

    $this->assertStringContainsString('blt/manifest.yml', end($labels));
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
    file_put_contents(
      "{$this->dir}/docroot/sites/default/blt.yml",
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

  /**
   * The mount operations refuse the shared directory on their own.
   *
   * A second layer: the command checks for this, but this is what issues the
   * remote rm, so it does not rely on a caller having checked.
   */
  public function testFilesDeleteRefusesTheSharedDirectory() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("shared 'default' site directory");

    (new Mounts('/repo'))->siteDirectory('uiowa.prod', 'default');
  }

  /**
   * The refusal is not case-sensitive.
   */
  public function testFilesDeleteRefusesTheSharedDirectoryInAnyCase() {
    $this->expectException(\InvalidArgumentException::class);

    (new Mounts('/repo'))->siteDirectory('uiowa.prod', 'Default');
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

}
