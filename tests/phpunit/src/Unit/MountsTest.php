<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Acquia\Mounts;

/**
 * Unit tests for the Acquia GFS mount paths.
 *
 * The class issues remote rm commands, so these cover the argument checks that
 * decide what a path may name. No remote access.
 *
 * @group unit
 */
class MountsTest extends UnitTestCase {

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

}
