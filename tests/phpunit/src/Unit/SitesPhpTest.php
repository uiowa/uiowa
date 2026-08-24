<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\SitesPhp;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Unit tests for the sites.php directory-alias reader/writer.
 *
 * @group unit
 */
class SitesPhpTest extends UnitTestCase {

  /**
   * Scratch directory holding the sites.php fixture.
   *
   * @var string
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-sites-php-' . uniqid();
    mkdir($this->dir);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new Filesystem())->remove($this->dir);
    parent::tearDown();
  }

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

  /**
   * An added block carries its marker comment and every host given.
   */
  public function testAddAliasesAppendsTheBlock() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->addAliases('fresh.uiowa.edu', [
      'fresh.uiowa.ddev.site',
      'fresh.prod.drupal.uiowa.edu',
    ]);

    $contents = file_get_contents($path);

    $this->assertStringContainsString('// Directory aliases for fresh.uiowa.edu.', $contents);
    $this->assertStringContainsString("\$sites['fresh.uiowa.ddev.site'] = 'fresh.uiowa.edu';", $contents);
    $this->assertStringContainsString("\$sites['fresh.prod.drupal.uiowa.edu'] = 'fresh.uiowa.edu';", $contents);
  }

  /**
   * The file ends on one newline, not a blank line.
   *
   * A trailing blank line is a phpcs error, and multisite:create commits the
   * file it just wrote, so a second newline here fails CI on the next create.
   */
  public function testAddAliasesEndsWithOneNewline() {
    $path = $this->sitesPhp();

    (new SitesPhp($path))->addAliases('fresh.uiowa.edu', ['fresh.uiowa.ddev.site']);

    $contents = file_get_contents($path);

    $this->assertStringEndsWith("= 'fresh.uiowa.edu';\n", $contents);
    $this->assertStringEndsNotWith("\n\n", $contents);
  }

  /**
   * A blank line separates the new block from whatever preceded it.
   */
  public function testAddAliasesSeparatesBlocks() {
    $path = $this->sitesPhp();
    $sites = new SitesPhp($path);

    $sites->addAliases('alpha.uiowa.edu', ['alpha.uiowa.ddev.site']);
    $sites->addAliases('beta.uiowa.edu', ['beta.uiowa.ddev.site']);

    $contents = file_get_contents($path);

    $this->assertStringContainsString(
      "\n\n// Directory aliases for alpha.uiowa.edu.",
      $contents
    );
    $this->assertStringContainsString(
      "\n\n// Directory aliases for beta.uiowa.edu.",
      $contents
    );
    $this->assertStringEndsNotWith("\n\n", $contents);
  }

  /**
   * Adding a site that already has a block leaves the file alone.
   */
  public function testAddAliasesIsIdempotent() {
    $path = $this->sitesPhp();
    $sites = new SitesPhp($path);

    $sites->addAliases('fresh.uiowa.edu', ['fresh.uiowa.ddev.site']);
    $once = file_get_contents($path);
    $sites->addAliases('fresh.uiowa.edu', ['fresh.uiowa.ddev.site']);

    $this->assertSame($once, file_get_contents($path));
  }

}
