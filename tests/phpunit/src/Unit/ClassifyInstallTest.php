<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\SiteInstallCommand;
use SiteNow\Install\InstallState;
use SiteNow\Install\InstallStatus;
use Symfony\Component\Process\Process;

/**
 * Unit tests for the install classification that drives both install commands.
 *
 * This is the decision the migration exists for: telling a site that was never
 * installed from one whose install died partway, which the BLT command could not
 * do. Drush is stubbed, so each test states what the database would answer and
 * asserts the classification — including the answers that are awkward to produce
 * against a real site, like a query that fails mid-check.
 *
 * @group unit
 */
class ClassifyInstallTest extends UnitTestCase {

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
      @unlink("{$root}/docroot/sites/site.uiowa.edu/settings.php");
      @rmdir("{$root}/docroot/sites/site.uiowa.edu");
      @rmdir("{$root}/docroot/sites");
      @rmdir("{$root}/docroot");
      @rmdir($root);
    }
    parent::tearDown();
  }

  /**
   * Build a repo root containing one site directory.
   *
   * The directory has to exist and hold settings.php, or classification stops at
   * Unavailable before it reaches a query.
   *
   * @param bool $settings
   *   Whether to write a settings.php, making the directory a real site.
   */
  private function fixtureRepo(bool $settings = TRUE): string {
    $root = sys_get_temp_dir() . '/sn_classify_' . uniqid();
    mkdir("{$root}/docroot/sites/site.uiowa.edu", 0777, TRUE);
    $this->cleanup[] = $root;

    if ($settings) {
      file_put_contents("{$root}/docroot/sites/site.uiowa.edu/settings.php", "<?php\n");
    }

    return $root;
  }

  /**
   * A finished process standing in for one drush call.
   *
   * @param string $output
   *   What the call writes to stdout.
   * @param bool $ok
   *   Whether the call exits zero.
   */
  private function reply(string $output, bool $ok = TRUE): Process {
    // Producing a real finished Process keeps the stub honest: isSuccessful()
    // and getOutput() behave exactly as they do in the code under test.
    $process = $ok
      ? new Process(['printf', '%s', $output])
      : new Process(['false']);
    $process->run();

    return $process;
  }

  /**
   * A command whose drush calls are answered from a scripted queue.
   *
   * @param string $repoRoot
   *   The fixture repo root.
   * @param array<int, \Symfony\Component\Process\Process> $replies
   *   Replies in the order the calls are expected.
   */
  private function command(string $repoRoot, array $replies): SiteInstallCommand {
    return new class($repoRoot, $replies) extends SiteInstallCommand {

      public array $queries = [];

      public function __construct(string $repoRoot, private array $replies) {
        parent::__construct($repoRoot);
      }

      protected function drush(array $args, bool $stream = FALSE, ?string $uri = NULL): Process {
        $this->queries[] = implode(' ', $args);
        $reply = array_shift($this->replies);

        if ($reply === NULL) {
          throw new \RuntimeException('Unexpected drush call: ' . end($this->queries));
        }

        return $reply;
      }

      public function pubClassify(string $site): InstallState {
        return $this->classifyInstall($site, 'uiowa09', FALSE);
      }

    };
  }

  /**
   * A site with no directory is unavailable, and costs no queries.
   */
  public function testMissingDirectoryIsUnavailable() {
    $command = $this->command($this->fixtureRepo(), []);
    $state = $command->pubClassify('absent-dir.uiowa.edu');

    $this->assertSame(InstallStatus::Unavailable, $state->status);
    $this->assertSame('no site directory', $state->detail);
    $this->assertSame([], $command->queries);
  }

  /**
   * A directory with no settings.php is not a site, and costs no queries.
   *
   * The tree carries many such leftovers from deleted sites. Drupal would walk
   * up the domain and resolve one of these to a different site's database, so
   * classifying it at all would mean reporting on the wrong site.
   */
  public function testDirectoryWithoutSettingsIsUnavailable() {
    $command = $this->command($this->fixtureRepo(settings: FALSE), []);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Unavailable, $state->status);
    $this->assertSame('no settings.php, so not a site', $state->detail);
    $this->assertSame([], $command->queries);
  }

  /**
   * An install_task of 'done' is installed, and settles in one query.
   *
   * The one-query cost is the point: a scan covers a whole application, so the
   * healthy case must not pay for the diagnosis of the unhealthy one.
   */
  public function testFinishedInstallIsInstalledInOneQuery() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply('s:4:"done";'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Installed, $state->status);
    $this->assertCount(1, $command->queries);
    $this->assertStringContainsString('install_task', $command->queries[0]);
  }

  /**
   * A mid-install task name is partial, and names where it stopped.
   */
  public function testUnfinishedInstallTaskIsPartial() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply('s:23:"install_profile_modules";'),
      // The content check: table probe, then a count per table present.
      $this->reply("node_field_data\nusers_field_data"),
      $this->reply('0'),
      $this->reply('0'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Partial, $state->status);
    $this->assertSame("install stopped at task 'install_profile_modules'", $state->detail);
    $this->assertFalse($state->hasContent());
  }

  /**
   * No install_task row at all is partial.
   */
  public function testMissingInstallTaskRowIsPartial() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply(''),
      $this->reply("node_field_data\nusers_field_data"),
      $this->reply('0'),
      $this->reply('0'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Partial, $state->status);
    $this->assertSame('Drupal never recorded an install task', $state->detail);
  }

  /**
   * A state read that fails, with a config table present, is partial.
   */
  public function testFailedStateReadWithConfigTableIsPartial() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply('', FALSE),
      $this->reply('config'),
      $this->reply("node_field_data\nusers_field_data"),
      $this->reply('0'),
      $this->reply('0'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Partial, $state->status);
    $this->assertSame('the key_value table is missing or unreadable', $state->detail);
  }

  /**
   * A state read that fails with no config table is absent.
   */
  public function testFailedStateReadWithoutConfigTableIsAbsent() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply('', FALSE),
      $this->reply('', FALSE),
    ]);

    $this->assertSame(InstallStatus::Absent, $command->pubClassify('site.uiowa.edu')->status);
  }

  /**
   * An empty database — reachable, nothing in it — is absent.
   */
  public function testEmptyDatabaseIsAbsent() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply('', FALSE),
      $this->reply(''),
    ]);

    $this->assertSame(InstallStatus::Absent, $command->pubClassify('site.uiowa.edu')->status);
  }

  /**
   * Content a real person created is counted and reported.
   */
  public function testPartialInstallCountsRealContent() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply(''),
      $this->reply("node_field_data\nusers_field_data"),
      $this->reply('43'),
      $this->reply('12'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertTrue($state->hasContent());
    $this->assertSame(43, $state->nodes);
    $this->assertSame(12, $state->users);
  }

  /**
   * Tables the install never created count as zero, not as unknown.
   *
   * An install that stopped before creating node_field_data genuinely holds
   * nothing, so it must stay eligible to be healed.
   */
  public function testAbsentContentTablesCountAsEmpty() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply(''),
      // The probe answers, and reports neither table.
      $this->reply(''),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertSame(InstallStatus::Partial, $state->status);
    $this->assertFalse($state->hasContent());
    $this->assertFalse($state->contentUnknown);
  }

  /**
   * A table probe that fails leaves content unknown, which blocks.
   */
  public function testFailedTableProbeLeavesContentUnknown() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply(''),
      $this->reply('', FALSE),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertTrue($state->contentUnknown);
    $this->assertTrue($state->hasContent());
  }

  /**
   * A count that fails on a table known to exist also leaves content unknown.
   *
   * The case that made this distinction necessary: the table is listed, so it is
   * not absent, and the count failing means the check broke down rather than
   * that the site is empty.
   */
  public function testFailedCountLeavesContentUnknown() {
    $command = $this->command($this->fixtureRepo(), [
      $this->reply(''),
      $this->reply("node_field_data\nusers_field_data"),
      $this->reply('', FALSE),
      $this->reply('0'),
    ]);
    $state = $command->pubClassify('site.uiowa.edu');

    $this->assertTrue($state->contentUnknown);
    $this->assertTrue($state->hasContent());
    $this->assertStringContainsString('content could not be checked', $state->describe());
  }

}
