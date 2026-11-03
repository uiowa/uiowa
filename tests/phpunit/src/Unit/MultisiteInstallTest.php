<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteInstallCommand;
use SiteNow\Command\SiteInstallCommand;
use SiteNow\Install\InstallState;
use SiteNow\Install\InstallStatus;

/**
 * Unit tests for how multisite:install sorts a scanned application.
 *
 * Covers selectTargets(), which decides what a run acts on: a site that was
 * never installed and one whose install never finished are both installed, a
 * complete install is left alone, and an unfinished install holding content is
 * held back for a human unless --force is given. No drush or Acquia access.
 *
 * @group unit
 */
class MultisiteInstallTest extends UnitTestCase {

  /**
   * A command instance exposing the protected selection under test.
   */
  private function command(): MultisiteInstallCommand {
    return new class('') extends MultisiteInstallCommand {

      public function pubSelectTargets(array $states, bool $force): array {
        return $this->selectTargets($states, $force);
      }

      public function pubClassifyResults(array $targets, array $results, array $blocked = []): array {
        return $this->classifyResults($targets, $results, $blocked);
      }

      public function pubFailureReason(array $result): string {
        return $this->failureReason($result);
      }

    };
  }

  /**
   * A representative scan of one application.
   *
   * @return array<string, \SiteNow\Install\InstallState>
   *   States keyed by site host.
   */
  private function scan(): array {
    return [
      'new.uiowa.edu' => new InstallState(InstallStatus::Absent),
      'halfway.uiowa.edu' => new InstallState(InstallStatus::Partial, 'install stopped at task \'install_profile_modules\'', 0, 0),
      'lived-in.uiowa.edu' => new InstallState(InstallStatus::Partial, 'Drupal never recorded an install task', 43, 12),
      'fine.uiowa.edu' => new InstallState(InstallStatus::Installed),
      'elsewhere.uiowa.edu' => new InstallState(InstallStatus::Unavailable, 'database not present on uiowa09'),
    ];
  }

  /**
   * An uninstalled site and an empty partial install are both installed.
   */
  public function testUninstalledAndEmptyPartialAreTargets() {
    $result = $this->command()->pubSelectTargets($this->scan(), FALSE);

    $this->assertSame(['new.uiowa.edu', 'halfway.uiowa.edu'], array_keys($result['targets']));
  }

  /**
   * A partial install holding content is held back, not installed.
   */
  public function testPartialInstallWithContentIsBlocked() {
    $result = $this->command()->pubSelectTargets($this->scan(), FALSE);

    $this->assertSame(['lived-in.uiowa.edu'], array_keys($result['blocked']));
    $this->assertArrayNotHasKey('lived-in.uiowa.edu', $result['targets']);
  }

  /**
   * With --force, a partial install holding content is installed anyway.
   */
  public function testForceMovesBlockedSitesIntoTargets() {
    $result = $this->command()->pubSelectTargets($this->scan(), TRUE);

    $this->assertSame([], $result['blocked']);
    $this->assertArrayHasKey('lived-in.uiowa.edu', $result['targets']);
  }

  /**
   * Installed and unavailable sites are counted, never acted on.
   */
  public function testInstalledAndUnavailableAreLeftAlone() {
    $result = $this->command()->pubSelectTargets($this->scan(), FALSE);

    $this->assertSame(['installed' => 1, 'unavailable' => 1], $result['counts']);
    $this->assertArrayNotHasKey('fine.uiowa.edu', $result['targets']);
    $this->assertArrayNotHasKey('elsewhere.uiowa.edu', $result['targets']);
  }

  /**
   * A fully installed application produces nothing to do.
   */
  public function testHealthyApplicationHasNoTargets() {
    $result = $this->command()->pubSelectTargets([
      'a.uiowa.edu' => new InstallState(InstallStatus::Installed),
      'b.uiowa.edu' => new InstallState(InstallStatus::Installed),
    ], FALSE);

    $this->assertSame([], $result['targets']);
    $this->assertSame([], $result['blocked']);
    $this->assertSame(2, $result['counts']['installed']);
  }

  /**
   * Force does not promote an unavailable site into a target.
   *
   * Nothing can be installed where there is no site directory or no database,
   * so --force must not reach past the content check into that tier.
   */
  public function testForceDoesNotTargetUnavailableSites() {
    $result = $this->command()->pubSelectTargets([
      'elsewhere.uiowa.edu' => new InstallState(InstallStatus::Unavailable, 'no site directory'),
    ], TRUE);

    $this->assertSame([], $result['targets']);
    $this->assertSame(1, $result['counts']['unavailable']);
  }

  /**
   * A site whose content could not be checked is held back, not installed.
   */
  public function testUncheckedContentIsBlocked() {
    $states = [
      'unreachable.uiowa.edu' => new InstallState(
        InstallStatus::Partial,
        'install stopped at task \'install_profile_modules\'',
        contentUnknown: TRUE,
      ),
    ];

    $blocked = $this->command()->pubSelectTargets($states, FALSE);
    $this->assertSame(['unreachable.uiowa.edu'], array_keys($blocked['blocked']));
    $this->assertSame([], $blocked['targets']);

    $forced = $this->command()->pubSelectTargets($states, TRUE);
    $this->assertSame(['unreachable.uiowa.edu'], array_keys($forced['targets']));
  }

  /**
   * Each child's exit code lands in the tier it reported, not in "failed".
   *
   * A site can be reclassified between the scan and its turn to install, so a
   * child can answer BLOCKED or SKIPPED for a site the scan chose to install.
   * Bucketing either as a failure would raise a failure-tier Slack alert for
   * something that is not one.
   */
  public function testChildExitCodesLandInTheirOwnTiers() {
    $targets = [
      'ok.uiowa.edu' => new InstallState(InstallStatus::Absent),
      'drifted.uiowa.edu' => new InstallState(InstallStatus::Absent),
      'reclassified.uiowa.edu' => new InstallState(InstallStatus::Partial, 'stopped'),
      'gone.uiowa.edu' => new InstallState(InstallStatus::Absent),
      'broke.uiowa.edu' => new InstallState(InstallStatus::Absent),
    ];
    $results = [
      'ok.uiowa.edu' => ['exit' => 0, 'output' => '', 'error' => ''],
      'drifted.uiowa.edu' => ['exit' => SiteInstallCommand::CONFIG_MISMATCH, 'output' => '', 'error' => ''],
      'reclassified.uiowa.edu' => ['exit' => SiteInstallCommand::BLOCKED, 'output' => '', 'error' => ''],
      'gone.uiowa.edu' => ['exit' => SiteInstallCommand::SKIPPED, 'output' => '', 'error' => ''],
      'broke.uiowa.edu' => ['exit' => 1, 'output' => '', 'error' => 'boom'],
    ];

    $tiers = $this->command()->pubClassifyResults($targets, $results);

    $this->assertSame(['ok.uiowa.edu'], $tiers['installed']);
    $this->assertSame(['drifted.uiowa.edu'], $tiers['mismatch']);
    $this->assertSame(['reclassified.uiowa.edu'], array_keys($tiers['blocked']));
    $this->assertSame(['gone.uiowa.edu'], $tiers['skipped']);
    $this->assertSame(['broke.uiowa.edu'], $tiers['failed']);
  }

  /**
   * A site the scan held back stays held back after the run.
   */
  public function testScanBlockedSitesSurviveClassification() {
    $already = ['lived-in.uiowa.edu' => new InstallState(InstallStatus::Partial, 'stopped', 43, 12)];

    $tiers = $this->command()->pubClassifyResults([], [], $already);

    $this->assertSame(['lived-in.uiowa.edu'], array_keys($tiers['blocked']));
  }

  /**
   * A site that was run but reported nothing counts as failed.
   */
  public function testMissingResultCountsAsFailed() {
    $targets = ['silent.uiowa.edu' => new InstallState(InstallStatus::Absent)];

    $tiers = $this->command()->pubClassifyResults($targets, []);

    $this->assertSame(['silent.uiowa.edu'], $tiers['failed']);
  }

  /**
   * The state each target was in is carried through, for reporting.
   */
  public function testTargetsCarryTheirState() {
    $result = $this->command()->pubSelectTargets($this->scan(), FALSE);

    $this->assertSame(InstallStatus::Absent, $result['targets']['new.uiowa.edu']->status);
    $this->assertSame(InstallStatus::Partial, $result['targets']['halfway.uiowa.edu']->status);
  }

  /**
   * A child's wrapped error block is quoted as the one sentence it was.
   *
   * The child reports through SymfonyStyle, which wraps to the terminal width
   * and pads the result into a rectangle. Reading the last line alone quoted a
   * fragment beginning mid-sentence, which is how this was found.
   */
  public function testWrappedChildErrorIsRejoined() {
    $error = <<<'ERR'

 [ERROR] Refusing to install over new.uiowa.edu: its content could not be
         checked, so this cannot be shown to be safe. Investigate the
         database, then re-run with --force to install regardless.


ERR;

    $reason = $this->command()->pubFailureReason(['exit' => 4, 'output' => '', 'error' => $error]);

    $this->assertSame(
      'Refusing to install over new.uiowa.edu: its content could not be checked, so this cannot be shown to be safe. Investigate the database, then re-run with --force to install regardless. (exit 4)',
      $reason,
    );
  }

  /**
   * Drush's own single-line errors keep the tail behaviour they had.
   *
   * Only Symfony's uppercase markers are rejoined, so nothing changes for the
   * other commands sharing this trait.
   */
  public function testDrushErrorStillReadsFromTheTail() {
    $error = "  [warning] Something earlier\n  [error]  The site.uiowa.edu alias is unknown\n";

    $reason = $this->command()->pubFailureReason(['exit' => 1, 'output' => '', 'error' => $error]);

    $this->assertSame('[error]  The site.uiowa.edu alias is unknown (exit 1)', $reason);
  }

  /**
   * A child that said nothing still gets a line.
   */
  public function testSilentFailureIsDescribed() {
    $reason = $this->command()->pubFailureReason(['exit' => 1, 'output' => '', 'error' => '']);

    $this->assertSame('no error output (exit 1)', $reason);
  }

}
