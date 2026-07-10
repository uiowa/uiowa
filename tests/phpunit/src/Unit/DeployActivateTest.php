<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\DeployActivateCommand;

/**
 * Unit tests for the deploy:activate command's tag resolution.
 *
 * Covers resolveBuildTag(): parsing `git ls-remote --tags` output, ordering
 * the tags by semantic version, and appending the -build suffix distribute
 * pushes to the Acquia remotes. No git remote access.
 *
 * @group unit
 */
class DeployActivateTest extends UnitTestCase {

  /**
   * A command instance exposing the protected tag resolver.
   */
  private function command(): DeployActivateCommand {
    return new class('') extends DeployActivateCommand {

      public function pubResolveBuildTag(string $output): ?string {
        return $this->resolveBuildTag($output);
      }

    };
  }

  /**
   * Build an ls-remote output block from a list of tag names.
   */
  private function lsRemote(array $tags): string {
    $lines = [];
    foreach ($tags as $i => $tag) {
      // A fabricated but well-formed 40-char object name per ref.
      $sha = str_pad((string) ($i + 1), 40, '0', STR_PAD_LEFT);
      $lines[] = "{$sha}\trefs/tags/{$tag}";
    }
    return implode("\n", $lines) . "\n";
  }

  /**
   * The newest tag by semantic version wins and gains the -build suffix.
   */
  public function testResolvesNewestSemverTag() {
    $output = $this->lsRemote(['3.32.40', '3.32.41', '3.9.0', '3.100.0']);

    $this->assertSame('3.100.0-build', $this->command()->pubResolveBuildTag($output));
  }

  /**
   * Ordering is semantic, not lexical, across differing component widths.
   */
  public function testOrderingIsSemanticNotLexical() {
    // Lexically '3.9.0' sorts after '3.32.41'; semantically 32 > 9.
    $output = $this->lsRemote(['3.9.0', '3.32.41']);

    $this->assertSame('3.32.41-build', $this->command()->pubResolveBuildTag($output));
  }

  /**
   * Empty output resolves to NULL.
   */
  public function testEmptyOutputYieldsNull() {
    $this->assertNull($this->command()->pubResolveBuildTag(''));
  }

  /**
   * Output carrying no tag refs resolves to NULL.
   */
  public function testOutputWithoutTagRefsYieldsNull() {
    $this->assertNull($this->command()->pubResolveBuildTag("0000\trefs/heads/main\n"));
  }

}
