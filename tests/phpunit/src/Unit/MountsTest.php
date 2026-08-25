<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Acquia\Mounts;
use Symfony\Component\Process\Process;

/**
 * Unit tests for the Acquia GFS mount reader and deleter.
 *
 * The class reaches environments over drush ssh, so the remote is stood in for
 * and the tests cover what it asks and how it reads the answer. No remote
 * access.
 *
 * @group unit
 */
class MountsTest extends UnitTestCase {

  /**
   * The drush alias the probes and deletes are issued through.
   */
  const ALIAS = 'doomed.stage';

  /**
   * Application and Acquia environment name holding the fixture site.
   */
  const MOUNT = 'uiowa09.stage';

  /**
   * The fixture site's directory.
   */
  const DIRECTORY = 'doomed.uiowa.edu';

  /**
   * The mount's sites path, which find names alongside the site's directory.
   */
  const SITES_PATH = '/mnt/gfs/uiowa09.stage/sites';

  /**
   * The fixture site's directory on the mount.
   */
  const SITE_PATH = '/mnt/gfs/uiowa09.stage/sites/doomed.uiowa.edu';

  /**
   * A Mounts that answers remote commands from a script instead of a host.
   *
   * Replies are consumed in the order the commands are issued, each one an
   * array of optional 'out', 'err' and 'ok' keys. Every command is recorded on
   * the instance, so a test can assert on what would have run remotely.
   *
   * @param array $replies
   *   The scripted replies.
   *
   * @return \SiteNow\Acquia\Mounts
   *   A mount reader wired to the script.
   */
  private function mounts(array $replies): Mounts {
    return new class('/repo', $replies) extends Mounts {

      /**
       * Each remote command issued, as ['alias' => ..., 'command' => [...]].
       *
       * @var array
       */
      public array $commands = [];

      /**
       * {@inheritdoc}
       *
       * @param string $repoRoot
       *   Unused; the remote is never reached.
       * @param array $replies
       *   The scripted replies, consumed in order.
       */
      public function __construct(string $repoRoot, private array $replies) {
        parent::__construct($repoRoot);
      }

      /**
       * {@inheritdoc}
       */
      protected function remote(string $alias, array $command): Process {
        $this->commands[] = ['alias' => $alias, 'command' => $command];
        $reply = array_shift($this->replies) ?? [];

        // The command never runs; only the finished process's answers are
        // read, and those come from the script.
        $process = new class(['true']) extends Process {

          /**
           * Standard output the remote would have produced.
           *
           * @var string
           */
          public string $out = '';

          /**
           * Standard error the remote would have produced.
           *
           * @var string
           */
          public string $err = '';

          /**
           * Whether the remote command exited zero.
           *
           * @var bool
           */
          public bool $ok = TRUE;

          /**
           * {@inheritdoc}
           */
          public function getOutput(): string {
            return $this->out;
          }

          /**
           * {@inheritdoc}
           */
          public function getErrorOutput(): string {
            return $this->err;
          }

          /**
           * {@inheritdoc}
           */
          public function isSuccessful(): bool {
            return $this->ok;
          }

        };

        $process->out = $reply['out'] ?? '';
        $process->err = $reply['err'] ?? '';
        $process->ok = $reply['ok'] ?? TRUE;

        return $process;
      }

    };
  }

  /**
   * The path is the site's own directory on the environment's mount.
   */
  public function testSiteDirectoryIsTheSitesOwnDirectory() {
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
  public function testSiteDirectoryRefusesUnsafeDirectories(string $directory) {
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
  public function testSiteDirectoryRefusesUnsafeMounts() {
    $this->expectException(\InvalidArgumentException::class);
    (new Mounts('/repo'))->siteDirectory('/mnt/gfs', 'doomed.uiowa.edu');
  }

  /**
   * The shared default directory is refused on its own.
   *
   * A second layer: the delete command checks for this, but this is what
   * issues the remote rm, so it does not rely on a caller having checked.
   */
  public function testSiteDirectoryRefusesTheSharedDirectory() {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("shared 'default' site directory");

    (new Mounts('/repo'))->siteDirectory('uiowa.prod', 'default');
  }

  /**
   * The refusal is not case-sensitive.
   */
  public function testSiteDirectoryRefusesTheSharedDirectoryInAnyCase() {
    $this->expectException(\InvalidArgumentException::class);

    (new Mounts('/repo'))->siteDirectory('uiowa.prod', 'Default');
  }

  /**
   * The probe names both paths outright rather than descending the mount.
   *
   * The mount's sites path is a symlink into shared storage, which find will
   * not follow without -L, so a probe that looked inside it would report every
   * site's files absent and skip every files delete.
   */
  public function testTheProbeNamesBothPathsAtDepthZero() {
    $mounts = $this->mounts([['out' => self::SITES_PATH . "\n"]]);

    $mounts->siteDirectoryExists(self::ALIAS, self::MOUNT, self::DIRECTORY);

    $this->assertSame([
      [
        'alias' => self::ALIAS,
        'command' => [
          'find',
          self::SITES_PATH,
          self::SITE_PATH,
          '-maxdepth',
          '0',
        ],
      ],
    ], $mounts->commands);
  }

  /**
   * The site's directory echoed back is files that are present.
   */
  public function testDirectoryExistsWhenFindEchoesIt() {
    $mounts = $this->mounts([
      ['out' => self::SITES_PATH . "\n" . self::SITE_PATH . "\n"],
    ]);

    $this->assertTrue(
      $mounts->siteDirectoryExists(self::ALIAS, self::MOUNT, self::DIRECTORY)
    );
  }

  /**
   * The mount echoed alone is a site with no files there, not a failure.
   *
   * The find exits non-zero for the directory it could not stat, so the exit
   * status cannot distinguish this from a mount it could not read either.
   */
  public function testDirectoryIsAbsentWhenOnlyTheMountEchoes() {
    $mounts = $this->mounts([
      [
        'out' => self::SITES_PATH . "\n",
        'err' => 'find: ' . self::SITE_PATH . ": No such file or directory\n",
        'ok' => FALSE,
      ],
    ]);

    $this->assertFalse(
      $mounts->siteDirectoryExists(self::ALIAS, self::MOUNT, self::DIRECTORY)
    );
  }

  /**
   * Neither path echoed is refused rather than read as absent files.
   *
   * The bug this guards: taken for "already deleted", the files step is
   * skipped while the database, domains and repository entry all still go,
   * leaving the site's production files orphaned with nothing pointing at
   * them.
   */
  public function testUnreadableMountIsRefusedRatherThanReadAsAbsent() {
    $mounts = $this->mounts([
      [
        'err' => 'find: ' . self::SITES_PATH . ": No such file or directory\n",
        'ok' => FALSE,
      ],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot check files for doomed.uiowa.edu');
    $mounts->siteDirectoryExists(self::ALIAS, self::MOUNT, self::DIRECTORY);
  }

  /**
   * An answer with nothing in it at all is refused too.
   */
  public function testSilentProbeIsRefused() {
    $mounts = $this->mounts([[]]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage(self::SITES_PATH . ' could not be read.');
    $mounts->siteDirectoryExists(self::ALIAS, self::MOUNT, self::DIRECTORY);
  }

  /**
   * A directory that is already gone is a success with no rm issued.
   */
  public function testDeleteSkipsAnAbsentDirectory() {
    $mounts = $this->mounts([['out' => self::SITES_PATH . "\n"]]);

    $mounts->deleteSiteDirectory(self::ALIAS, self::MOUNT, self::DIRECTORY);

    $this->assertCount(1, $mounts->commands);
  }

  /**
   * The rm targets the site's directory, and nothing follows it.
   *
   * Acquia reprovisions the directory within about a minute, so a probe after
   * the rm cannot tell a successful delete from one that removed nothing.
   */
  public function testDeleteIssuesTheRemovalAndStops() {
    $mounts = $this->mounts([
      ['out' => self::SITES_PATH . "\n" . self::SITE_PATH . "\n"],
      [],
    ]);

    $mounts->deleteSiteDirectory(self::ALIAS, self::MOUNT, self::DIRECTORY);

    $this->assertCount(2, $mounts->commands);
    $this->assertSame(
      ['rm', '-rf', self::SITE_PATH],
      $mounts->commands[1]['command']
    );
  }

  /**
   * A failed rm is reported rather than confirmed.
   */
  public function testDeleteReportsFailedRemoval() {
    $mounts = $this->mounts([
      ['out' => self::SITES_PATH . "\n" . self::SITE_PATH . "\n"],
      ['err' => "rm: cannot remove: Permission denied\n", 'ok' => FALSE],
    ]);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Failed to delete files for doomed.uiowa.edu');
    $mounts->deleteSiteDirectory(self::ALIAS, self::MOUNT, self::DIRECTORY);
  }

}
