<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\SiteUpdateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Unit tests for the site:update command.
 *
 * Covers siteDirectory(), which mirrors Drupal's own sites.php aliasing:
 * an aliased host resolves to its mapped directory (notably an application's
 * own default site, addressed by a real domain but living in default), and an
 * unaliased host resolves to a same-named directory. Also covers the deploy
 * retry and its maintenance-mode handling, with drush calls scripted. No drush
 * or Acquia access.
 *
 * @group unit
 */
class SiteUpdateTest extends UnitTestCase {

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
      @unlink("{$root}/docroot/sites/sites.php");
      @rmdir("{$root}/docroot/sites");
      @rmdir("{$root}/docroot");
      @rmdir($root);
    }
    parent::tearDown();
  }

  /**
   * A command instance exposing the protected directory resolver.
   */
  private function command(string $repoRoot): SiteUpdateCommand {
    return new class($repoRoot) extends SiteUpdateCommand {

      public function pubSiteDirectory(string $host): string {
        return $this->siteDirectory($host);
      }

    };
  }

  /**
   * Build a fixture repo root, optionally writing a sites.php with aliases.
   *
   * @param array<string, string>|null $aliases
   *   Host => directory aliases to write into sites.php, or NULL to omit the
   *   file entirely.
   */
  private function fixtureRepo(?array $aliases): string {
    $root = sys_get_temp_dir() . '/sn_repo_' . uniqid();
    mkdir("{$root}/docroot/sites", 0777, TRUE);
    $this->cleanup[] = $root;

    if ($aliases !== NULL) {
      $lines = ["<?php\n"];
      foreach ($aliases as $host => $dir) {
        $lines[] = "\$sites['{$host}'] = '{$dir}';\n";
      }
      file_put_contents("{$root}/docroot/sites/sites.php", implode('', $lines));
    }
    return $root;
  }

  /**
   * The default site, addressed by its real host, resolves to default.
   */
  public function testDefaultSiteHostResolvesToDefaultDirectory() {
    $repo = $this->fixtureRepo([
      'demo.example.uiowa.edu' => 'default',
      'alias.uiowa.edu' => 'realdir.uiowa.edu',
    ]);

    $this->assertSame('default', $this->command($repo)->pubSiteDirectory('demo.example.uiowa.edu'));
  }

  /**
   * A non-default alias resolves to its mapped directory.
   */
  public function testAliasedHostResolvesToMappedDirectory() {
    $repo = $this->fixtureRepo([
      'alias.uiowa.edu' => 'realdir.uiowa.edu',
    ]);

    $this->assertSame('realdir.uiowa.edu', $this->command($repo)->pubSiteDirectory('alias.uiowa.edu'));
  }

  /**
   * A host with no alias resolves to a same-named directory.
   */
  public function testUnaliasedHostResolvesToItself() {
    $repo = $this->fixtureRepo([
      'alias.uiowa.edu' => 'realdir.uiowa.edu',
    ]);

    $this->assertSame('plain.uiowa.edu', $this->command($repo)->pubSiteDirectory('plain.uiowa.edu'));
  }

  /**
   * With no sites.php present, every host resolves to itself.
   */
  public function testMissingSitesFileResolvesToHost() {
    $repo = $this->fixtureRepo(NULL);

    $this->assertSame('anything.uiowa.edu', $this->command($repo)->pubSiteDirectory('anything.uiowa.edu'));
  }

  /**
   * A command whose drush calls are answered from a script, recording each.
   *
   * @param array $deploys
   *   Success flags for successive `drush deploy` calls.
   * @param array $maint
   *   Successive maintenance-mode readings: TRUE, FALSE, or NULL (unreadable).
   */
  private function scriptedCommand(array $deploys, array $maint): SiteUpdateCommand {
    return new class($deploys, $maint) extends SiteUpdateCommand {

      public array $calls = [];

      public function __construct(private array $deploys, private array $maint) {
        parent::__construct();
      }

      public function pubRunDeploy(string $site): bool {
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());
        return $this->runDeploy($io, $site);
      }

      protected function drush(array $args, bool $stream = FALSE, ?string $uri = NULL): Process {
        $this->calls[] = implode(' ', $args);
        $success = TRUE;
        $output = '';
        if ($args[0] === 'deploy') {
          $success = array_shift($this->deploys);
        }
        elseif ($args[0] === 'maint:get') {
          $value = array_shift($this->maint);
          $success = $value !== NULL;
          $output = $value ? "1\n" : "0\n";
        }
        return new class($success, $output) extends Process {

          public function __construct(private bool $success, private string $out) {
            parent::__construct(['true']);
          }

          public function isSuccessful(): bool {
            return $this->success;
          }

          public function getOutput(): string {
            return $this->out;
          }

        };
      }

    };
  }

  /**
   * A first-try success neither retries nor touches maintenance mode.
   */
  public function testDeploySuccessDoesNotRetry() {
    $command = $this->scriptedCommand([TRUE], [FALSE]);

    $this->assertTrue($command->pubRunDeploy('site.uiowa.edu'));
    $this->assertSame([
      'maint:get',
      'deploy --yes',
    ], $command->calls);
  }

  /**
   * A failure that leaves maintenance mode on clears it, then retries.
   */
  public function testFailureLeavingMaintenanceModeOnIsRestoredBeforeRetry() {
    $command = $this->scriptedCommand([FALSE, TRUE], [FALSE, TRUE]);

    $this->assertTrue($command->pubRunDeploy('site.uiowa.edu'));
    $this->assertSame([
      'maint:get',
      'deploy --yes',
      'maint:get',
      'maint:set 0',
      'deploy --yes -vvv',
    ], $command->calls);
  }

  /**
   * A site its owner put in maintenance mode is never taken out of it.
   */
  public function testOwnerMaintenanceModeIsLeftOn() {
    $command = $this->scriptedCommand([FALSE, FALSE], [TRUE]);

    $this->assertFalse($command->pubRunDeploy('site.uiowa.edu'));
    $this->assertNotContains('maint:set 0', $command->calls);
  }

  /**
   * An unreadable starting state skips that attempt and retries.
   */
  public function testUnreadableMaintenanceModeRetries() {
    $command = $this->scriptedCommand([TRUE], [NULL, FALSE]);

    $this->assertTrue($command->pubRunDeploy('site.uiowa.edu'));
    $this->assertSame([
      'maint:get',
      'maint:get',
      'deploy --yes -vvv',
    ], $command->calls);
  }

  /**
   * A state that stays unreadable never deploys and reports failure.
   */
  public function testPersistentlyUnreadableMaintenanceModeFails() {
    $command = $this->scriptedCommand([], [NULL, NULL]);

    $this->assertFalse($command->pubRunDeploy('site.uiowa.edu'));
    $this->assertSame(['maint:get', 'maint:get'], $command->calls);
  }

  /**
   * Two failures restore maintenance mode after each and report failure.
   */
  public function testRepeatedFailureRestoresMaintenanceModeEachTime() {
    $command = $this->scriptedCommand([FALSE, FALSE], [FALSE, TRUE, TRUE]);

    $this->assertFalse($command->pubRunDeploy('site.uiowa.edu'));
    $sets = array_filter($command->calls, fn($call) => $call === 'maint:set 0');
    $this->assertCount(2, $sets);
  }

}
