<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteExecuteCommand;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Unit tests for MultisiteExecuteCommand.
 *
 * Covers the per-site failure reason — leads with drush's own error message
 * so a developer sees what went wrong without decoding an exit code, trails
 * the exit code as a detail — and the Acquia Cloud --apps guard, which pins
 * the fleet selection to the application actually running the command
 * whenever AH_SITE_ENVIRONMENT is set, so a scheduled job or shell on one
 * Acquia application can't reach another's sites now that the ddev gate no
 * longer confines this command to a developer's own machine.
 *
 * @group unit
 */
class MultisiteExecuteTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    putenv('AH_SITE_ENVIRONMENT');
    putenv('AH_SITE_GROUP');
    parent::tearDown();
  }

  /**
   * Expose the protected failure-reason builder.
   *
   * @param int $exit
   *   The exit code.
   * @param string $error
   *   The stderr text.
   * @param string $output
   *   The stdout text.
   *
   * @return string
   *   The one-line reason.
   */
  private function reason(int $exit, string $error = '', string $output = ''): string {
    $command = new class extends MultisiteExecuteCommand {

      /**
       * Calls the protected reason builder.
       */
      public function expose(array $result): string {
        return $this->failureReason($result);
      }

    };
    return $command->expose(['exit' => $exit, 'output' => $output, 'error' => $error]);
  }

  /**
   * The stderr message leads; the exit code trails in parentheses.
   */
  public function testLeadsWithStderrMessage(): void {
    $reason = $this->reason(1, "Some noise\nThere are no commands defined in the \"fake\" namespace.");
    $this->assertSame('There are no commands defined in the "fake" namespace. (exit 1)', $reason);
  }

  /**
   * Falls back to the stdout tail when stderr is empty.
   */
  public function testFallsBackToStdoutTail(): void {
    $reason = $this->reason(3, '', "line one\nThe MySQL server has gone away");
    $this->assertSame('The MySQL server has gone away (exit 3)', $reason);
  }

  /**
   * A silent failure still reads as words, not a bare code.
   */
  public function testNoOutputReadsPlainly(): void {
    $this->assertSame('no error output (exit 255)', $this->reason(255));
  }

  /**
   * Expose the protected --apps guard.
   *
   * @param string[] $apps
   *   The --apps option, already comma-split.
   *
   * @return array{0: array|null, 1: string}
   *   The guard's return value, and anything written to the error output.
   */
  private function restrict(array $apps): array {
    $command = new class extends MultisiteExecuteCommand {

      /**
       * Calls the protected guard.
       */
      public function expose(array $apps, SymfonyStyle $err): ?array {
        return $this->restrictToRunningApp($apps, $err);
      }

    };
    $buffer = new BufferedOutput();
    $err = new SymfonyStyle(new ArrayInput([]), $buffer);

    $result = $command->expose($apps, $err);

    return [$result, $buffer->fetch()];
  }

  /**
   * Locally (no AH_SITE_ENVIRONMENT), --apps passes through untouched.
   */
  public function testLocalPassesThrough(): void {
    [$result, $output] = $this->restrict(['uiowa02', 'uiowa03']);

    $this->assertSame(['uiowa02', 'uiowa03'], $result);
    $this->assertSame('', $output);
  }

  /**
   * On Acquia Cloud, an omitted --apps is pinned to the running application.
   *
   * The pin is noted on the error output so it's visible in scheduled job
   * logs, not just inferred from the resulting site list.
   */
  public function testAcquiaPinsEmptyApps(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    putenv('AH_SITE_GROUP=uiowa02');

    [$result, $output] = $this->restrict([]);

    $this->assertSame(['uiowa02'], $result);
    $this->assertStringContainsString('uiowa02', $output);
  }

  /**
   * On Acquia Cloud, --apps naming only the running application passes.
   */
  public function testAcquiaAllowsMatchingApp(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    putenv('AH_SITE_GROUP=uiowa02');

    [$result, $output] = $this->restrict(['uiowa02']);

    $this->assertSame(['uiowa02'], $result);
    $this->assertSame('', $output);
  }

  /**
   * On Acquia Cloud, --apps naming a different application is rejected.
   */
  public function testAcquiaRejectsOtherApp(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    putenv('AH_SITE_GROUP=uiowa02');

    [$result, $output] = $this->restrict(['uiowa09']);

    $this->assertNull($result);
    $this->assertStringContainsString('uiowa02', $output);
    $this->assertStringContainsString('uiowa09', $output);
  }

  /**
   * On Acquia Cloud, --apps naming several matching apps is still rejected.
   *
   * Pinning silently to just the running app would be a quieter,
   * easier-to-miss version of the same over-broad run this guard exists to
   * catch.
   */
  public function testAcquiaRejectsMultipleAppsEvenIfOneMatches(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');
    putenv('AH_SITE_GROUP=uiowa02');

    [$result] = $this->restrict(['uiowa02', 'uiowa03']);

    $this->assertNull($result);
  }

  /**
   * On Acquia Cloud with no AH_SITE_GROUP, the guard fails closed.
   */
  public function testAcquiaWithoutSiteGroupFails(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');

    [$result, $output] = $this->restrict([]);

    $this->assertNull($result);
    $this->assertStringContainsString('AH_SITE_GROUP', $output);
  }

  /**
   * Expose the protected SSH-agent-skip check.
   *
   * @param string $env
   *   The --env option value.
   *
   * @return bool
   *   TRUE when the SSH agent precondition can be skipped.
   */
  private function canSkip(string $env): bool {
    $command = new class extends MultisiteExecuteCommand {

      /**
       * Calls the protected check.
       */
      public function expose(string $env): bool {
        return $this->canSkipSshAgent($env);
      }

    };
    return $command->expose($env);
  }

  /**
   * Locally (no AH_SITE_ENVIRONMENT), the SSH agent is always required.
   */
  public function testLocalNeverSkipsSshAgent(): void {
    $this->assertFalse($this->canSkip('prod'));
  }

  /**
   * On Acquia Cloud, --env matching the running environment skips the agent.
   *
   * The resulting drush alias points at this same environment, which
   * resolves locally rather than over SSH.
   */
  public function testAcquiaSkipsSshAgentForMatchingEnv(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');

    $this->assertTrue($this->canSkip('prod'));
  }

  /**
   * On Acquia Cloud, a different --env still requires the SSH agent.
   *
   * That alias is a genuinely different, remote environment.
   */
  public function testAcquiaRequiresSshAgentForOtherEnv(): void {
    putenv('AH_SITE_ENVIRONMENT=prod');

    $this->assertFalse($this->canSkip('dev'));
  }

}
