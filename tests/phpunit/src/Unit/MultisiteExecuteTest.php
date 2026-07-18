<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\MultisiteExecuteCommand;

/**
 * Unit tests for MultisiteExecuteCommand's per-site failure reason.
 *
 * The reason leads with drush's own error message (so a developer sees what
 * went wrong without decoding an exit code) and trails the exit code as a
 * detail.
 *
 * @group unit
 */
class MultisiteExecuteTest extends UnitTestCase {

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

}
