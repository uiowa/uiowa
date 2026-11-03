<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\DeployVersionStampCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the deploy:version-stamp command.
 *
 * Covers the stamping pass over the build artifact's custom .info.yml files:
 * a file with no version gains one, a file that already declares a version is
 * left alone, and a missing Git version is a warning rather than a failure.
 * The Git version itself is injected, so no git access is needed.
 *
 * @group unit
 */
class DeployVersionStampTest extends UnitTestCase {

  /**
   * Build directories to remove after each test.
   *
   * @var string[]
   */
  private array $cleanup = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->cleanup as $dir) {
      $this->removeRecursive($dir);
    }
    parent::tearDown();
  }

  /**
   * A command whose Git version is fixed, or NULL to simulate an unknown one.
   */
  private function command(?string $version): DeployVersionStampCommand {
    return new class('', $version) extends DeployVersionStampCommand {

      public function __construct(string $repoRoot, private ?string $stubVersion) {
        parent::__construct($repoRoot);
      }

      protected function gitVersion(): ?string {
        return $this->stubVersion;
      }

    };
  }

  /**
   * Build a fixture build dir with the given docroot-relative .info.yml files.
   *
   * @param array<string, string> $files
   *   Map of docroot-relative path => file contents.
   */
  private function buildDir(array $files): string {
    $dir = sys_get_temp_dir() . '/sn_build_' . uniqid();
    $this->cleanup[] = $dir;
    foreach ($files as $rel => $contents) {
      $path = "{$dir}/docroot/{$rel}";
      mkdir(dirname($path), 0777, TRUE);
      file_put_contents($path, $contents);
    }
    // Guarantee the dir exists even when no files are given.
    if (!is_dir($dir)) {
      mkdir($dir, 0777, TRUE);
    }
    return $dir;
  }

  /**
   * Recursively remove a directory tree.
   */
  private function removeRecursive(string $path): void {
    if (is_dir($path)) {
      foreach (array_diff(scandir($path), ['.', '..']) as $entry) {
        $this->removeRecursive("{$path}/{$entry}");
      }
      @rmdir($path);
    }
    elseif (is_file($path)) {
      @unlink($path);
    }
  }

  /**
   * Run the command against a build dir and return the tester.
   */
  private function runStamp(?string $version, string $buildDir): CommandTester {
    $tester = new CommandTester($this->command($version));
    $tester->execute(['--build-dir' => $buildDir]);
    return $tester;
  }

  /**
   * An unversioned .info.yml is stamped; an already-versioned one is skipped.
   */
  public function testStampsUnversionedAndSkipsVersioned() {
    $buildDir = $this->buildDir([
      'modules/custom/foo/foo.info.yml' => "name: Foo\ntype: module\n",
      'themes/custom/bar/bar.info.yml' => "name: Bar\ntype: theme\nversion: '1.2.3'\n",
    ]);

    $tester = $this->runStamp('9.9.9', $buildDir);

    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    $this->assertStringContainsString('into 1 .info.yml file(s)', $tester->getDisplay());

    $foo = file_get_contents("{$buildDir}/docroot/modules/custom/foo/foo.info.yml");
    $this->assertStringContainsString("version: '9.9.9'", $foo);
    // The pre-existing content survives the append.
    $this->assertStringContainsString('name: Foo', $foo);

    $bar = file_get_contents("{$buildDir}/docroot/themes/custom/bar/bar.info.yml");
    $this->assertStringContainsString("version: '1.2.3'", $bar);
    $this->assertStringNotContainsString('9.9.9', $bar);
  }

  /**
   * Nested .info.yml files (deeper than one level) are left alone.
   */
  public function testDoesNotStampNestedInfoFiles() {
    $buildDir = $this->buildDir([
      'modules/custom/foo/foo.info.yml' => "name: Foo\ntype: module\n",
      'modules/custom/foo/tests/nested/nested.info.yml' => "name: Nested\ntype: module\n",
    ]);

    $tester = $this->runStamp('9.9.9', $buildDir);

    $this->assertStringContainsString('into 1 .info.yml file(s)', $tester->getDisplay());

    $nested = file_get_contents("{$buildDir}/docroot/modules/custom/foo/tests/nested/nested.info.yml");
    $this->assertStringNotContainsString('version', $nested);
  }

  /**
   * An unknown Git version warns and stamps nothing, but does not fail.
   */
  public function testUnknownVersionWarnsButSucceeds() {
    $buildDir = $this->buildDir([
      'modules/custom/foo/foo.info.yml' => "name: Foo\ntype: module\n",
    ]);

    $tester = $this->runStamp(NULL, $buildDir);

    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    $foo = file_get_contents("{$buildDir}/docroot/modules/custom/foo/foo.info.yml");
    $this->assertStringNotContainsString('version', $foo);
  }

  /**
   * A build dir with no custom code directories warns and succeeds.
   */
  public function testNoCustomDirectoriesWarnsAndSucceeds() {
    $buildDir = $this->buildDir([]);

    $tester = $this->runStamp('9.9.9', $buildDir);

    $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    $this->assertStringContainsString('No custom code directories', $tester->getDisplay());
  }

  /**
   * A missing build dir is a failure.
   */
  public function testMissingBuildDirFails() {
    $tester = $this->runStamp('9.9.9', sys_get_temp_dir() . '/sn_absent_' . uniqid());

    $this->assertSame(Command::FAILURE, $tester->getStatusCode());
  }

}
