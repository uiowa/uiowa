<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\SiteUpdateCommand;

/**
 * Unit tests for the site:update command's directory resolution.
 *
 * Covers siteDirectory(), which mirrors Drupal's own sites.php aliasing:
 * an aliased host resolves to its mapped directory (notably the default site,
 * addressed as demo.sitenow.uiowa.edu but living in default), and an
 * unaliased host resolves to a same-named directory. No drush or Acquia
 * access.
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
      'demo.sitenow.uiowa.edu' => 'default',
      'alias.uiowa.edu' => 'realdir.uiowa.edu',
    ]);

    $this->assertSame('default', $this->command($repo)->pubSiteDirectory('demo.sitenow.uiowa.edu'));
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

}
