<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\DeployActivateCommand;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Unit tests for the deploy:activate command's tag and environment resolution.
 *
 * Covers resolveBuildTag(): parsing `git ls-remote --tags` output, ordering
 * the tags by semantic version, and appending the -build suffix distribute
 * pushes to the Acquia remotes. Also covers findEnvironment(): matching an
 * application's environments against a requested drush alias environment
 * (dev/test/prod), which is not always Acquia's own name for it — uiowa07-09
 * call 'test' 'stage'. No git remote or Acquia API access.
 *
 * @group unit
 */
class DeployActivateTest extends UnitTestCase {

  /**
   * Scratch directory for fixture drush alias files.
   *
   * @var string
   */
  private string $dir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->dir = sys_get_temp_dir() . '/sn-deploy-activate-' . uniqid();
    mkdir("{$this->dir}/drush/sites", 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    (new Filesystem())->remove($this->dir);
    parent::tearDown();
  }

  /**
   * Write a fixture drush alias file for an application.
   *
   * @param string $app
   *   The application (AH_SITE_GROUP).
   * @param string $test_user
   *   The 'test' environment's user field, e.g. 'uiowa09.stage'.
   */
  private function writeAlias(string $app, string $test_user): void {
    file_put_contents("{$this->dir}/drush/sites/{$app}.site.yml", Yaml::dump([
      'dev' => ['user' => "{$app}.dev"],
      'test' => ['user' => $test_user],
      'prod' => ['user' => "{$app}.prod"],
    ], 4, 2));
  }

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
   * A command instance exposing the protected findEnvironment().
   *
   * Rooted at the scratch directory holding the fixture alias files.
   */
  private function commandInDir(): DeployActivateCommand {
    return new class($this->dir) extends DeployActivateCommand {

      public function pubFindEnvironment(iterable $environments, string $app, string $env): ?object {
        return $this->findEnvironment($environments, $app, $env);
      }

    };
  }

  /**
   * An application's own name for 'test' is matched, not the literal string.
   *
   * This is the exact bug report: on uiowa07-09, `--env=test` matched nothing
   * because the API's environment is named 'stage' there.
   */
  public function testFindEnvironmentMatchesTheApplicationsOwnName(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $environments = [
      (object) ['name' => 'dev', 'uuid' => 'env-dev'],
      (object) ['name' => 'stage', 'uuid' => 'env-stage'],
      (object) ['name' => 'prod', 'uuid' => 'env-prod'],
    ];

    $target = $this->commandInDir()->pubFindEnvironment($environments, 'uiowa09', 'test');

    $this->assertNotNull($target);
    $this->assertSame('env-stage', $target->uuid);
  }

  /**
   * An application with no divergence matches the requested name directly.
   */
  public function testFindEnvironmentMatchesDirectlyWhenUniform(): void {
    $this->writeAlias('uiowa04', 'uiowa04.test');

    $environments = [(object) ['name' => 'test', 'uuid' => 'env-test']];

    $target = $this->commandInDir()->pubFindEnvironment($environments, 'uiowa04', 'test');

    $this->assertSame('env-test', $target->uuid);
  }

  /**
   * No environment named 'test' resolves to NULL, not a false match on 'stage'.
   */
  public function testFindEnvironmentReturnsNullWhenNothingMatches(): void {
    $this->writeAlias('uiowa09', 'uiowa09.stage');

    $environments = [
      (object) ['name' => 'dev', 'uuid' => 'env-dev'],
      (object) ['name' => 'prod', 'uuid' => 'env-prod'],
    ];

    $this->assertNull($this->commandInDir()->pubFindEnvironment($environments, 'uiowa09', 'test'));
  }

  /**
   * Without an alias file, the requested name is used as-is.
   */
  public function testFindEnvironmentFallsBackWithoutAnAliasFile(): void {
    $environments = [(object) ['name' => 'test', 'uuid' => 'env-test']];

    $target = $this->commandInDir()->pubFindEnvironment($environments, 'unknownapp', 'test');

    $this->assertSame('env-test', $target->uuid);
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
   * A non-semver tag is skipped rather than aborting the resolution.
   */
  public function testSkipsNonSemverTags() {
    // A legacy or ad-hoc ref alongside real release tags: Semver::rsort would
    // throw on it, so it must be filtered before the sort.
    $output = $this->lsRemote(['3.32.40', 'pre-release', '3.32.41', 'nightly']);

    $this->assertSame('3.32.41-build', $this->command()->pubResolveBuildTag($output));
  }

  /**
   * Output carrying only non-semver tags resolves to NULL.
   */
  public function testAllNonSemverTagsYieldNull() {
    $output = $this->lsRemote(['pre-release', 'nightly']);

    $this->assertNull($this->command()->pubResolveBuildTag($output));
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
