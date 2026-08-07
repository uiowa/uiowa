<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteDeleteCommand;
use SiteNow\Operation\CloudOperationWait;
use SiteNow\Operation\ManifestRemove;
use SiteNow\Operation\SitesPhpRemove;
use SiteNow\Plan\Plan;
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
    foreach (glob($this->dir . '/*') ?: [] as $file) {
      unlink($file);
    }
    if (is_dir($this->dir)) {
      rmdir($this->dir);
    }
    parent::tearDown();
  }

  /**
   * A command instance exposing the protected selection helpers.
   */
  private function command(): MultisiteDeleteCommand {
    return new class('') extends MultisiteDeleteCommand {

      public function pubSitesByHost(array $manifest): array {
        return $this->sitesByHost($manifest);
      }

      public function pubAddCloudSteps(Plan $plan, array $input): void {
        $this->addCloudSteps($plan, $input);
      }

    };
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
   * Write a manifest fixture and return its path.
   */
  private function manifest(array $manifest): string {
    $path = $this->dir . '/manifest.yml';
    file_put_contents($path, Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP));
    return $path;
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

  // --- Manifest removal -------------------------------------------------------

  /**
   * The host is removed and its application's other sites are left alone.
   */
  public function testManifestRemoveDropsOnlyTheHost() {
    $path = $this->manifest([
      'uiowa02' => ['a.uiowa.edu', 'b.uiowa.edu'],
    ]);

    (new ManifestRemove($path, 'uiowa02', 'a.uiowa.edu'))->run();

    $this->assertSame(['uiowa02' => ['b.uiowa.edu']], Yaml::parseFile($path));
  }

  /**
   * An application left with no sites is dropped entirely.
   */
  public function testManifestRemoveDropsEmptyApplication() {
    $path = $this->manifest([
      'uiowa02' => ['a.uiowa.edu'],
      'uiowa07' => ['c.uiowa.edu'],
    ]);

    (new ManifestRemove($path, 'uiowa02', 'a.uiowa.edu'))->run();

    $this->assertSame(['uiowa07' => ['c.uiowa.edu']], Yaml::parseFile($path));
  }

  /**
   * Removing a site that is already gone is a no-op, so a retry is safe.
   */
  public function testManifestRemoveIsIdempotent() {
    $path = $this->manifest([
      'uiowa02' => ['b.uiowa.edu'],
    ]);

    (new ManifestRemove($path, 'uiowa02', 'a.uiowa.edu'))->run();
    (new ManifestRemove($path, 'uiowa02', 'a.uiowa.edu'))->run();

    $this->assertSame(['uiowa02' => ['b.uiowa.edu']], Yaml::parseFile($path));
  }

  /**
   * The whole manifest is re-sorted, matching what ManifestUpdate writes.
   */
  public function testManifestRemoveSortsWhatItWrites() {
    $path = $this->manifest([
      'uiowa07' => ['z.uiowa.edu', 'y.uiowa.edu'],
      'uiowa02' => ['b.uiowa.edu', 'a.uiowa.edu'],
    ]);

    (new ManifestRemove($path, 'uiowa02', 'a.uiowa.edu'))->run();

    $parsed = Yaml::parseFile($path);
    $this->assertSame(['uiowa02', 'uiowa07'], array_keys($parsed));
    $this->assertSame(['y.uiowa.edu', 'z.uiowa.edu'], $parsed['uiowa07']);
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
  public function testSitesPhpRemoveStripsTheSiteBlock() {
    $path = $this->sitesPhp();

    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();

    $contents = file_get_contents($path);
    $this->assertStringNotContainsString('doomed', $contents);
  }

  /**
   * Other sites' aliases survive untouched.
   */
  public function testSitesPhpRemoveLeavesOtherSites() {
    $path = $this->sitesPhp();

    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();

    $contents = file_get_contents($path);
    $this->assertStringContainsString("// Directory aliases for keeper.uiowa.edu.", $contents);
    $this->assertStringContainsString("\$sites['keeper.uiowa.ddev.site'] = 'keeper.uiowa.edu';", $contents);
    $this->assertStringContainsString("\$sites['keeper.prod.drupal.uiowa.edu'] = 'keeper.uiowa.edu';", $contents);
  }

  /**
   * An alias added by hand is removed too, unlike a literal block replacement.
   */
  public function testSitesPhpRemoveStripsAliasesOutsideTheBlock() {
    $path = $this->sitesPhp();
    file_put_contents($path, "\n\$sites['vanity.uiowa.edu'] = 'doomed.uiowa.edu';\n", FILE_APPEND);

    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();

    $this->assertStringNotContainsString('vanity.uiowa.edu', file_get_contents($path));
  }

  /**
   * Running twice leaves the same file, so a retry is safe.
   */
  public function testSitesPhpRemoveIsIdempotent() {
    $path = $this->sitesPhp();

    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();
    $once = file_get_contents($path);
    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();

    $this->assertSame($once, file_get_contents($path));
  }

  /**
   * Removal does not leave a widening run of blank lines behind.
   */
  public function testSitesPhpRemoveCollapsesBlankLines() {
    $path = $this->sitesPhp();

    (new SitesPhpRemove($path, 'doomed.uiowa.edu'))->run();

    $this->assertDoesNotMatchRegularExpression("/\n{3,}/", file_get_contents($path));
  }

  // --- Cloud teardown ---------------------------------------------------------

  /**
   * The cloud steps cover files per environment, the database, and domains.
   */
  public function testCloudStepsCoverEveryResource() {
    $plan = new Plan('t', [], []);
    $this->command()->pubAddCloudSteps($plan, $this->input());

    $labels = array_column($plan->steps(), 'label');

    $this->assertCount(5, $labels);
    $this->assertStringContainsString('uiowa02.dev', $labels[0]);
    $this->assertStringContainsString('uiowa02.test', $labels[1]);
    $this->assertStringContainsString('uiowa02.prod', $labels[2]);
    $this->assertStringContainsString('doomed_uiowa_edu', $labels[3]);
    $this->assertStringContainsString('doomed.uiowa.edu', $labels[4]);
  }

  /**
   * Every cloud step refuses to run while the teardown is unimplemented.
   *
   * The repository steps come after these, so an apply cannot reach them and
   * strand a site whose cloud resources are still live — the failure mode of
   * BLT's umd --simulate.
   */
  public function testCloudStepsRefuseToRun() {
    $plan = new Plan('t', [], []);
    $this->command()->pubAddCloudSteps($plan, $this->input());

    foreach ($plan->steps() as $step) {
      try {
        ($step['run'])();
        $this->fail("Step '{$step['label']}' ran instead of refusing.");
      }
      catch (\RuntimeException $e) {
        $this->assertStringContainsString('not implemented', $e->getMessage());
      }
    }
  }

  // --- Cloud operation notification links -------------------------------------

  /**
   * The notification UUID is read from the operation's link.
   */
  public function testNotificationUuidParsesTheLink() {
    $uuid = '3d87eca7-89d1-47e2-84db-bc7ad52a9363';
    $links = $this->links("https://cloud.acquia.com/api/notifications/{$uuid}");

    $this->assertSame($uuid, CloudOperationWait::notificationUuid($links));
  }

  /**
   * An operation with no links cannot be confirmed.
   */
  public function testNotificationUuidRejectsMissingLinks() {
    $this->assertNull(CloudOperationWait::notificationUuid(NULL));
    $this->assertNull(CloudOperationWait::notificationUuid((object) []));
  }

  /**
   * A link that does not end in a UUID is refused rather than requested.
   */
  public function testNotificationUuidRejectsNonUuidPath() {
    $this->assertNull(CloudOperationWait::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/')
    ));
    $this->assertNull(CloudOperationWait::notificationUuid(
      $this->links('https://cloud.acquia.com/api/notifications/not-a-uuid')
    ));
  }

}
