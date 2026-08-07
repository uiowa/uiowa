<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Config\Manifest;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the site manifest reader/writer.
 *
 * @group unit
 */
class ManifestTest extends UnitTestCase {

  /**
   * Scratch directory holding the manifest fixture.
   *
   * @var string
   */
  private string $dir;

  /**
   * Path to the manifest fixture.
   *
   * @var string
   */
  private string $path;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-manifest-' . uniqid();
    mkdir($this->dir);
    $this->path = $this->dir . '/manifest.yml';
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
   * Write a manifest fixture and return a reader/writer for it.
   */
  private function manifest(array $manifest): Manifest {
    file_put_contents($this->path, Yaml::dump($manifest, 8, 2, Yaml::DUMP_OBJECT_AS_MAP));
    return new Manifest($this->path);
  }

  /**
   * An absent file reads as an empty manifest rather than raising.
   */
  public function testAllHandlesMissingFile() {
    $this->assertSame([], (new Manifest($this->dir . '/absent.yml'))->all());
  }

  /**
   * A host is added under its application, creating the key if needed.
   */
  public function testAddSiteCreatesTheApplicationKey() {
    $manifest = $this->manifest(['uiowa02' => ['a.uiowa.edu']]);

    $manifest->addSite('uiowa07', 'c.uiowa.edu');

    $this->assertSame(['c.uiowa.edu'], $manifest->sites('uiowa07'));
  }

  /**
   * Adding a host that is already present does not duplicate it.
   */
  public function testAddSiteIsIdempotent() {
    $manifest = $this->manifest(['uiowa02' => ['a.uiowa.edu']]);

    $manifest->addSite('uiowa02', 'a.uiowa.edu');

    $this->assertSame(['a.uiowa.edu'], $manifest->sites('uiowa02'));
  }

  /**
   * Removing an application's last site drops the application entirely.
   */
  public function testRemoveSiteDropsEmptyApplication() {
    $manifest = $this->manifest([
      'uiowa02' => ['a.uiowa.edu'],
      'uiowa07' => ['c.uiowa.edu'],
    ]);

    $manifest->removeSite('uiowa02', 'a.uiowa.edu');

    $this->assertSame(['uiowa07'], array_keys($manifest->all()));
  }

  /**
   * Removing a host that is already gone is a no-op, so a retry is safe.
   */
  public function testRemoveSiteIsIdempotent() {
    $manifest = $this->manifest(['uiowa02' => ['b.uiowa.edu']]);

    $manifest->removeSite('uiowa02', 'a.uiowa.edu');
    $manifest->removeSite('uiowa02', 'a.uiowa.edu');

    $this->assertSame(['uiowa02' => ['b.uiowa.edu']], $manifest->all());
  }

  /**
   * Both writers sort applications and their hosts.
   */
  public function testWritesAreSorted() {
    $manifest = $this->manifest([]);

    $manifest->addSite('uiowa07', 'z.uiowa.edu');
    $manifest->addSite('uiowa02', 'b.uiowa.edu');
    $manifest->addSite('uiowa02', 'a.uiowa.edu');

    $parsed = $manifest->all();
    $this->assertSame(['uiowa02', 'uiowa07'], array_keys($parsed));
    $this->assertSame(['a.uiowa.edu', 'b.uiowa.edu'], $parsed['uiowa02']);
  }

  /**
   * An add and a matching remove leave the file byte-identical.
   *
   * The reason both writers share save(): a provision and a deletion must
   * agree on layout, or unrelated changes arrive as whole-file diffs.
   */
  public function testAddAndRemoveRoundTripToIdenticalBytes() {
    $manifest = $this->manifest([
      'uiowa02' => ['a.uiowa.edu', 'b.uiowa.edu'],
      'uiowa07' => ['c.uiowa.edu'],
    ]);
    $before = file_get_contents($this->path);

    $manifest->addSite('uiowa02', 'new.uiowa.edu');
    $manifest->removeSite('uiowa02', 'new.uiowa.edu');

    $this->assertSame($before, file_get_contents($this->path));
  }

}
