<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteInstallCommand;
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
   * The state each target was in is carried through, for reporting.
   */
  public function testTargetsCarryTheirState() {
    $result = $this->command()->pubSelectTargets($this->scan(), FALSE);

    $this->assertSame(InstallStatus::Absent, $result['targets']['new.uiowa.edu']->status);
    $this->assertSame(InstallStatus::Partial, $result['targets']['halfway.uiowa.edu']->status);
  }

}
