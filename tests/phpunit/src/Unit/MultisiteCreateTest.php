<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Robo\Plugin\Commands\MultisiteCreateCommand;
use Uiowa\Multisite;

/**
 * Unit tests for the multisite create command's plan layer.
 *
 * Covers the generic plan primitives (CheckResult, Check, Plan, and the
 * PlanTrait validation aggregation) and the command's application-selection
 * rules. Both are pure logic: no Acquia API, git, or filesystem access.
 *
 * @group unit
 */
class MultisiteCreateTest extends UnitTestCase {

  /**
   * Build an application candidate fixture as decide() assembles it.
   */
  private function app(string $name, int $sites, bool $ssl, bool $reserved = FALSE): array {
    return [
      'uuid' => "uuid-{$name}",
      'name' => $name,
      'sites' => $sites,
      'reserved' => $reserved,
      'has_ssl' => $ssl,
      'ssl_match' => $ssl ? '*.uiowa.edu' : NULL,
      'related' => NULL,
      'sans' => $ssl ? 50 : NULL,
    ];
  }

  /**
   * A command instance exposing the protected selection methods.
   */
  private function command(): MultisiteCreateCommand {
    return new class extends MultisiteCreateCommand {

      public function pubSelectApp(array $candidates, array $options): array {
        return $this->selectApp($candidates, $options);
      }

      public function pubEligibleApps(array $candidates): array {
        return $this->eligibleApps($candidates);
      }

      public function pubBuildSiteConfig(string $host, string $id, string $db, string $local, string $prod_domain, array $options): array {
        return $this->buildSiteConfig($host, $id, $db, $local, $prod_domain, $options);
      }

      public function pubHasIdentifierConflict(string $host, array $existing): bool {
        return $this->hasIdentifierConflict($host, $existing);
      }

    };
  }

  /**
   * Build a per-site config with default fixture arguments.
   */
  private function siteConfig(array $options): array {
    return $this->command()->pubBuildSiteConfig(
      'newsite.uiowa.edu',
      'newsite',
      'newsite_uiowa_edu',
      'newsite.dev.local.drupal.uiowa.edu',
      'newsite.prod.drupal.uiowa.edu',
      $options
    );
  }

  /**
   * A harness exposing the protected PlanTrait aggregation helpers.
   */
  private function planHarness(): object {
    return new class() {

      use PlanTrait;

      public function checks(array $checks): array {
        return $this->runChecks($checks);
      }

      public function merge(array $base, array $extra): array {
        return $this->mergeValidation($base, $extra);
      }

    };
  }

  // --- Application selection (command domain rules) ---------------------------

  /**
   * Auto-pick chooses the fewest sites among SSL-covered applications.
   */
  public function testAutoPicksFewestSitesAmongSslCovered() {
    $candidates = [
      'uiowa' => $this->app('uiowa', 87, TRUE),
      'uiowa02' => $this->app('uiowa02', 65, TRUE),
      'uiowa03' => $this->app('uiowa03', 12, TRUE),
    ];

    [$app, $reasoning] = $this->command()->pubSelectApp($candidates, []);

    $this->assertSame('uiowa03', $app['name']);
    $this->assertStringContainsString('12', $reasoning);
  }

  /**
   * A reserved application is excluded from auto-pick even when smallest.
   */
  public function testExcludesReservedFromAutoPick() {
    $candidates = [
      'uiowa06' => $this->app('uiowa06', 2, TRUE, TRUE),
      'uiowa03' => $this->app('uiowa03', 12, TRUE),
    ];

    [$app] = $this->command()->pubSelectApp($candidates, []);

    $this->assertSame('uiowa03', $app['name']);
  }

  /**
   * With no SSL coverage anywhere, auto-pick falls back to non-reserved apps.
   */
  public function testFallsBackToNonSslWhenNoCoverage() {
    $candidates = [
      'uiowa' => $this->app('uiowa', 87, FALSE),
      'uiowa04' => $this->app('uiowa04', 30, FALSE),
      'uiowa06' => $this->app('uiowa06', 5, FALSE, TRUE),
    ];

    [$app] = $this->command()->pubSelectApp($candidates, []);

    $this->assertSame('uiowa04', $app['name']);
  }

  /**
   * Ties at the lowest site count break deterministically by application name.
   */
  public function testTiesBreakByApplicationName() {
    $candidates = [
      'uiowa03' => $this->app('uiowa03', 12, TRUE),
      'uiowa02' => $this->app('uiowa02', 12, TRUE),
    ];

    [$app, $reasoning] = $this->command()->pubSelectApp($candidates, []);

    $this->assertSame('uiowa02', $app['name']);
    $this->assertStringContainsString('12', $reasoning);
  }

  /**
   * An explicit --app overrides auto-pick, even against the smallest app.
   */
  public function testExplicitAppOverridesAutoPick() {
    $candidates = [
      'uiowa' => $this->app('uiowa', 87, TRUE),
      'uiowa03' => $this->app('uiowa03', 12, TRUE),
    ];

    [$app, $reasoning] = $this->command()->pubSelectApp($candidates, ['app' => 'uiowa']);

    $this->assertSame('uiowa', $app['name']);
    $this->assertStringContainsString('--app', $reasoning);
  }

  /**
   * A reserved application is accepted when named explicitly.
   */
  public function testExplicitReservedAppAccepted() {
    $candidates = [
      'uiowa06' => $this->app('uiowa06', 5, TRUE, TRUE),
      'uiowa03' => $this->app('uiowa03', 12, TRUE),
    ];

    [$app] = $this->command()->pubSelectApp($candidates, ['app' => 'uiowa06']);

    $this->assertSame('uiowa06', $app['name']);
  }

  /**
   * Eligible filtering prefers SSL-covered apps and drops reserved ones.
   */
  public function testEligibleAppsPrefersSslAndDropsReserved() {
    $candidates = [
      'uiowa' => $this->app('uiowa', 87, TRUE),
      'uiowa04' => $this->app('uiowa04', 30, FALSE),
      'uiowa06' => $this->app('uiowa06', 5, TRUE, TRUE),
    ];

    $eligible = $this->command()->pubEligibleApps($candidates);

    $this->assertSame(['uiowa'], array_keys($eligible));
  }

  // --- Validation aggregation (generic PlanTrait) -----------------------------

  /**
   * All passing checks yield an overall PASS.
   */
  public function testRunChecksAllPass() {
    $result = $this->planHarness()->checks([
      new Check('a', fn() => CheckResult::pass()),
      new Check('b', fn() => CheckResult::pass()),
    ]);

    $this->assertSame(CheckStatus::Pass, $result['overall']);
    $this->assertCount(2, $result['checks']);
  }

  /**
   * A single WARN raises the overall status to WARN.
   */
  public function testRunChecksWarnRaisesOverall() {
    $result = $this->planHarness()->checks([
      new Check('a', fn() => CheckResult::pass()),
      new Check('b', fn() => CheckResult::warn('careful')),
    ]);

    $this->assertSame(CheckStatus::Warn, $result['overall']);
    $this->assertSame('careful', $result['checks']['b']['message']);
  }

  /**
   * A FAIL dominates a WARN regardless of order.
   */
  public function testRunChecksFailDominatesWarn() {
    $result = $this->planHarness()->checks([
      new Check('a', fn() => CheckResult::warn('careful')),
      new Check('b', fn() => CheckResult::fail('nope')),
    ]);

    $this->assertSame(CheckStatus::Fail, $result['overall']);
  }

  /**
   * Merging keeps the worst status and combines both check sets.
   */
  public function testMergeValidationKeepsWorstStatusAndCombines() {
    $harness = $this->planHarness();
    $base = $harness->checks([new Check('a', fn() => CheckResult::pass())]);
    $extra = $harness->checks([new Check('b', fn() => CheckResult::fail('nope'))]);

    $merged = $harness->merge($base, $extra);

    $this->assertSame(CheckStatus::Fail, $merged['overall']);
    $this->assertArrayHasKey('a', $merged['checks']);
    $this->assertArrayHasKey('b', $merged['checks']);
  }

  /**
   * Merging a lower-severity extra does not downgrade the base status.
   */
  public function testMergeValidationDoesNotDowngrade() {
    $harness = $this->planHarness();
    $base = $harness->checks([new Check('a', fn() => CheckResult::warn('careful'))]);
    $extra = $harness->checks([new Check('b', fn() => CheckResult::pass())]);

    $merged = $harness->merge($base, $extra);

    $this->assertSame(CheckStatus::Warn, $merged['overall']);
  }

  // --- Value objects ----------------------------------------------------------

  /**
   * Plan reflects its overall validation status.
   */
  public function testPlanStatusHelpers() {
    $fail = new Plan('t', [], ['overall' => CheckStatus::Fail, 'checks' => []]);
    $warn = new Plan('t', [], ['overall' => CheckStatus::Warn, 'checks' => []]);
    $pass = new Plan('t', [], ['overall' => CheckStatus::Pass, 'checks' => []]);

    $this->assertTrue($fail->failed());
    $this->assertFalse($fail->warned());
    $this->assertTrue($warn->warned());
    $this->assertFalse($warn->failed());
    $this->assertFalse($pass->failed());
    $this->assertFalse($pass->warned());
  }

  /**
   * CheckResult factories set the expected status.
   */
  public function testCheckResultFactories() {
    $this->assertSame(CheckStatus::Pass, CheckResult::pass()->status);
    $this->assertSame(CheckStatus::Warn, CheckResult::warn('m')->status);
    $this->assertSame(CheckStatus::Fail, CheckResult::fail('m')->status);
    $this->assertSame('m', CheckResult::fail('m')->message);
  }

  /**
   * A Check evaluates its closure on demand.
   */
  public function testCheckEvaluatesClosure() {
    $check = new Check('a', fn() => CheckResult::fail('boom'));
    $result = $check->evaluate();

    $this->assertSame(CheckStatus::Fail, $result->status);
    $this->assertSame('boom', $result->message);
  }

  // --- Per-site config shaping ------------------------------------------------

  /**
   * A single split is stored as a scalar string.
   */
  public function testSingleSplitStoredAsScalar() {
    $blt = $this->siteConfig(['split' => 'sitenow_v2']);

    $this->assertSame('sitenow_v2', $blt['uiowa']['config']['split']);
  }

  /**
   * Multiple splits are stored as a trimmed array.
   */
  public function testMultipleSplitsStoredAsTrimmedArray() {
    $blt = $this->siteConfig(['split' => 'ccom, research ,sitenow_v2']);

    $this->assertSame(['ccom', 'research', 'sitenow_v2'], $blt['uiowa']['config']['split']);
  }

  /**
   * No split option leaves the config split unset.
   */
  public function testNoSplitOmitsConfigKey() {
    $blt = $this->siteConfig([]);

    $this->assertArrayNotHasKey('config', $blt['uiowa']);
  }

  /**
   * Requester and site name are included only when provided.
   */
  public function testOptionalFieldsIncludedWhenProvided() {
    $bare = $this->siteConfig([]);
    $this->assertArrayNotHasKey('requester', $bare['uiowa']);
    $this->assertArrayNotHasKey('site-name', $bare['uiowa']);

    $full = $this->siteConfig(['requester' => 'hawkid', 'site-name' => 'New Site']);
    $this->assertSame('hawkid', $full['uiowa']['requester']);
    $this->assertSame('New Site', $full['uiowa']['site-name']);
  }

  /**
   * The core project and database keys derive from the host and identifier.
   */
  public function testCoreConfigDerivation() {
    $blt = $this->siteConfig([]);

    $this->assertSame('newsite', $blt['project']['machine_name']);
    $this->assertSame('newsite.uiowa.edu', $blt['project']['human_name']);
    $this->assertSame('newsite_uiowa_edu', $blt['drupal']['db']['database']);
    $this->assertSame('https://newsite.prod.drupal.uiowa.edu', $blt['uiowa']['stage_file_proxy']['origin']);
  }

  // --- Hostname validation ----------------------------------------------------

  /**
   * Well-formed hosts validate.
   *
   * @dataProvider validHostProvider
   */
  public function testValidHosts(string $host) {
    $this->assertTrue(Multisite::isValidHost($host));
  }

  /**
   * Malformed hosts are rejected.
   *
   * @dataProvider invalidHostProvider
   */
  public function testInvalidHosts(string $host) {
    $this->assertFalse(Multisite::isValidHost($host));
  }

  /**
   * Valid host fixtures.
   */
  public function validHostProvider(): array {
    return [
      ['newsite.uiowa.edu'],
      ['a.b.uiowa.edu'],
      ['my-site.uiowa.edu'],
      ['site123.uiowa.edu'],
      ['foo.bar.baz.uiowa.edu'],
    ];
  }

  // --- Normalized identifier conflict -----------------------------------------

  /**
   * Hosts that normalize to the same identifier are flagged as conflicts.
   */
  public function testIdentifierConflictDetectsNormalizedCollision() {
    // The www prefix is stripped, so www.foo.uiowa.edu and foo.uiowa.edu
    // share an id.
    $this->assertTrue(
      $this->command()->pubHasIdentifierConflict('www.foo.uiowa.edu', ['foo.uiowa.edu'])
    );
  }

  /**
   * Distinct identifiers do not conflict.
   */
  public function testIdentifierConflictAllowsDistinctHosts() {
    $this->assertFalse(
      $this->command()->pubHasIdentifierConflict('bar.uiowa.edu', ['foo.uiowa.edu'])
    );
  }

  /**
   * The normalization that drives the conflict check collapses www.
   */
  public function testGetIdentifierCollapsesWww() {
    $this->assertSame(
      Multisite::getIdentifier('https://foo.uiowa.edu'),
      Multisite::getIdentifier('https://www.foo.uiowa.edu')
    );
  }

  /**
   * Invalid host fixtures.
   */
  public function invalidHostProvider(): array {
    return [
      'uppercase' => ['NewSite.uiowa.edu'],
      'underscore' => ['new_site.uiowa.edu'],
      'single label' => ['localhost'],
      'leading dot' => ['.uiowa.edu'],
      'trailing dot' => ['newsite.uiowa.edu.'],
      'leading hyphen' => ['-site.uiowa.edu'],
      'trailing hyphen' => ['site-.uiowa.edu'],
      'space' => ['new site.uiowa.edu'],
      'empty' => [''],
    ];
  }

}
