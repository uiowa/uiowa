<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\SiteInstallCommand;
use SiteNow\Install\InstallState;
use SiteNow\Install\InstallStatus;

/**
 * Unit tests for the site:install command's decisions about one site.
 *
 * Covers the parts that read the repository or classify state without touching
 * a database: the install_task state parser that separates a finished install
 * from an abandoned one, the per-site and profile configuration readers, and
 * the InstallState value object the fleet command reports from.
 *
 * @group unit
 */
class SiteInstallTest extends UnitTestCase {

  /**
   * Fixture repo roots to remove after each test.
   *
   * @var string[]
   */
  private array $cleanup = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->cleanup as $root) {
      $this->remove($root);
    }
    parent::tearDown();
  }

  /**
   * Remove a fixture directory tree.
   */
  private function remove(string $path): void {
    if (is_dir($path)) {
      foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
        $this->remove("{$path}/{$entry}");
      }
      @rmdir($path);
      return;
    }
    @unlink($path);
  }

  /**
   * A command instance exposing the protected readers under test.
   */
  private function command(string $repoRoot): SiteInstallCommand {
    return new class($repoRoot) extends SiteInstallCommand {

      public function pubStateValue(string $raw): ?string {
        return $this->stateValue($raw);
      }

      public function pubSiteConfig(string $dir): array {
        return $this->siteConfig($dir);
      }

      public function pubProfile(): string {
        return $this->profile();
      }

      public function pubExportedSiteUuid(): ?string {
        return $this->exportedSiteUuid();
      }

      public function pubAccountName(): string {
        return $this->accountName();
      }

    };
  }

  /**
   * Build a fixture repo root.
   *
   * @param array<string, string> $files
   *   File contents keyed by path relative to the repo root. Parent directories
   *   are created as needed.
   */
  private function fixtureRepo(array $files = []): string {
    $root = sys_get_temp_dir() . '/sn_install_' . uniqid();
    mkdir($root, 0777, TRUE);
    $this->cleanup[] = $root;

    foreach ($files as $path => $contents) {
      $full = "{$root}/{$path}";
      if (!is_dir(dirname($full))) {
        mkdir(dirname($full), 0777, TRUE);
      }
      file_put_contents($full, $contents);
    }

    return $root;
  }

  /**
   * A finished install is recognized from its serialized state value.
   */
  public function testFinishedInstallStateValue() {
    $command = $this->command($this->fixtureRepo());

    $this->assertSame('done', $command->pubStateValue('s:4:"done";'));
  }

  /**
   * An abandoned install reports the task it stopped at.
   */
  public function testAbandonedInstallStateValue() {
    $command = $this->command($this->fixtureRepo());

    $this->assertSame('install_profile_modules', $command->pubStateValue('s:23:"install_profile_modules";'));
  }

  /**
   * A value that is not a serialized string reads as unknown, not as done.
   */
  public function testUnreadableStateValue() {
    $command = $this->command($this->fixtureRepo());

    $this->assertNull($command->pubStateValue('done'));
    $this->assertNull($command->pubStateValue(''));
    $this->assertNull($command->pubStateValue('b:1;'));
    $this->assertNull($command->pubStateValue('s:4:"done'));
  }

  /**
   * The per-site drs/config.yml supplies the post-install values.
   */
  public function testSiteConfigIsReadFromTheSiteDirectory() {
    $repo = $this->fixtureRepo([
      'docroot/sites/brand.uiowa.edu/drs/config.yml' => <<<'YAML'
project:
  human_name: brand.uiowa.edu
uiowa:
  requester: someone
  site-name: 'Brand Manual'
  config:
    split: event
YAML,
    ]);

    $config = $this->command($repo)->pubSiteConfig('brand.uiowa.edu');

    $this->assertSame('Brand Manual', $config['uiowa']['site-name']);
    $this->assertSame('someone', $config['uiowa']['requester']);
    $this->assertSame('event', $config['uiowa']['config']['split']);
  }

  /**
   * A site with no drs/config.yml yields an empty config rather than an error.
   */
  public function testMissingSiteConfigIsEmpty() {
    $repo = $this->fixtureRepo();

    $this->assertSame([], $this->command($repo)->pubSiteConfig('nothing.uiowa.edu'));
  }

  /**
   * The install profile comes from the repository's drs/config.yml.
   */
  public function testProfileIsReadFromDrsConfig() {
    $repo = $this->fixtureRepo([
      'drs/config.yml' => "project:\n  profile:\n    name: someprofile\n",
    ]);

    $this->assertSame('someprofile', $this->command($repo)->pubProfile());
  }

  /**
   * With no readable drs/config.yml the profile falls back to the default.
   */
  public function testProfileFallsBackToDefault() {
    $repo = $this->fixtureRepo();

    $this->assertSame('sitenow', $this->command($repo)->pubProfile());
  }

  /**
   * The exported site UUID is read from the exported system.site.
   */
  public function testExportedSiteUuidIsRead() {
    $repo = $this->fixtureRepo([
      'config/default/system.site.yml' => "uuid: 11111111-2222-3333-4444-555555555555\nname: Example\n",
    ]);

    $this->assertSame('11111111-2222-3333-4444-555555555555', $this->command($repo)->pubExportedSiteUuid());
  }

  /**
   * With no exported system.site there is no UUID to align to.
   */
  public function testMissingExportedSiteUuidIsNull() {
    $repo = $this->fixtureRepo();

    $this->assertNull($this->command($repo)->pubExportedSiteUuid());
  }

  /**
   * The generated user 1 name is a valid, non-repeating username.
   */
  public function testAccountNameIsRandomAndValid() {
    $command = $this->command($this->fixtureRepo());

    $first = $command->pubAccountName();
    $this->assertSame(10, strlen($first));
    $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{10}$/', $first);
    $this->assertNotSame($first, $command->pubAccountName());
  }

  /**
   * A partial install holding nothing of anyone's is safe to reinstall.
   *
   * Zero counts are what the profile's own default content produces: it is
   * authored by the installer's account, which the counts exclude.
   */
  public function testPartialInstallWithoutContentHasNoContent() {
    $state = new InstallState(InstallStatus::Partial, 'install stopped at task \'install_profile_modules\'', 0, 0);

    $this->assertFalse($state->hasContent());
    $this->assertSame("partial: install stopped at task 'install_profile_modules'", $state->describe());
  }

  /**
   * A partial install holding content says so, in the line that reports it.
   */
  public function testPartialInstallWithContentDescribesWhatItHolds() {
    $state = new InstallState(InstallStatus::Partial, 'Drupal never recorded an install task', 43, 12);

    $this->assertTrue($state->hasContent());
    $this->assertSame('43 nodes, 12 users', $state->contentSummary());
    $this->assertStringContainsString('but holds 43 nodes, 12 users', $state->describe());
  }

  /**
   * A user past uid 1 counts as content even with no nodes.
   */
  public function testUsersAloneCountAsContent() {
    $this->assertTrue((new InstallState(InstallStatus::Partial, '', 0, 3))->hasContent());
  }

  /**
   * A content check that could not run counts as content, and says so.
   *
   * Fails closed on purpose: "we could not find out" must not clear the way for
   * a reinstall the way a genuine zero does.
   */
  public function testUncheckedContentBlocksLikeContent() {
    $state = new InstallState(
      InstallStatus::Partial,
      'install stopped at task \'install_profile_modules\'',
      contentUnknown: TRUE,
    );

    $this->assertTrue($state->hasContent());
    $this->assertStringContainsString('content could not be checked', $state->describe());
    $this->assertStringNotContainsString('0 nodes', $state->describe());
  }

  /**
   * A state with nothing counted holds nothing, not an unknown amount.
   */
  public function testUncountedStateHasNoContent() {
    $this->assertFalse((new InstallState(InstallStatus::Installed))->hasContent());
    $this->assertSame('installed', (new InstallState(InstallStatus::Installed))->describe());
  }

}
