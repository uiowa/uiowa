<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Process\ProcessPool;

/**
 * Unit tests for ProcessPool's scheduling, retry, and failure handling.
 *
 * Jobs are real subprocesses (the PHP CLI running tiny inline scripts), so
 * these tests exercise the actual Process lifecycle: capture, non-zero
 * exits, launch failures, timeouts, the per-group cap, and the retry pass
 * with its all-failed short-circuit. No drush or SSH.
 *
 * @group unit
 */
class ProcessPoolTest extends UnitTestCase {

  /**
   * Scratch files created by tests, removed in tearDown().
   *
   * @var string[]
   */
  protected array $scratch = [];

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    foreach ($this->scratch as $file) {
      @unlink($file);
    }
    parent::tearDown();
  }

  /**
   * Build a job argv that runs inline PHP code.
   *
   * @param string $code
   *   The PHP code to run.
   *
   * @return array<int, string>
   *   The argv array.
   */
  protected function phpJob(string $code): array {
    return [PHP_BINARY, '-r', $code];
  }

  /**
   * Create a tracked scratch file path for a job to write state into.
   *
   * @return string
   *   An absolute path; the file does not exist yet.
   */
  protected function scratchFile(): string {
    $path = tempnam(sys_get_temp_dir(), 'pool');
    unlink($path);
    $this->scratch[] = $path;

    return $path;
  }

  /**
   * Stdout, stderr, and exit codes are captured per job, keyed by job key.
   */
  public function testResultsCaptured(): void {
    $pool = new ProcessPool(2);

    $results = $pool->run([
      'ok' => $this->phpJob('echo "out"; fwrite(STDERR, "err");'),
      'bad' => $this->phpJob('exit(3);'),
    ]);

    $this->assertSame(0, $results['ok']['exit']);
    $this->assertSame('out', $results['ok']['output']);
    $this->assertSame('err', $results['ok']['error']);
    $this->assertSame(3, $results['bad']['exit']);
  }

  /**
   * A binary that cannot launch becomes a non-zero result, not an exception.
   *
   * Depending on platform this surfaces as a shell exit code (126/127) or
   * as a start exception the pool converts; either way the caller sees a
   * failed result and the run continues.
   */
  public function testLaunchFailureBecomesResult(): void {
    $pool = new ProcessPool(2, 8, 300, 0);

    $results = $pool->run(['gone' => ['/nonexistent/binary/xyz']]);

    $this->assertNotSame(0, $results['gone']['exit']);
  }

  /**
   * A job exceeding the timeout becomes a non-zero result with the reason.
   */
  public function testTimeoutBecomesResult(): void {
    $pool = new ProcessPool(2, 8, 1, 0);

    $results = $pool->run(['slow' => $this->phpJob('sleep(5);')]);

    $this->assertSame(1, $results['slow']['exit']);
    $this->assertStringContainsString('timed out', $results['slow']['error']);
  }

  /**
   * A transient failure succeeds on the retry pass and reports exit 0.
   */
  public function testTransientFailureRetried(): void {
    $marker = $this->scratchFile();
    $code = sprintf(
      'if (file_exists(%1$s)) { exit(0); } touch(%1$s); exit(1);',
      var_export($marker, TRUE)
    );

    $pool = new ProcessPool(2);
    $results = $pool->run(['flaky' => $this->phpJob($code)]);

    $this->assertSame(0, $results['flaky']['exit']);
  }

  /**
   * When every job fails, the failure is systematic: no retry pass runs.
   */
  public function testAllFailedSkipsRetry(): void {
    $counters = [$this->scratchFile(), $this->scratchFile()];
    $jobs = [];
    foreach ($counters as $i => $counter) {
      $jobs["job{$i}"] = $this->phpJob(sprintf(
        'file_put_contents(%s, "x", FILE_APPEND); exit(1);',
        var_export($counter, TRUE)
      ));
    }

    $pool = new ProcessPool(2);
    $results = $pool->run($jobs);

    foreach ($results as $result) {
      $this->assertSame(1, $result['exit']);
    }
    foreach ($counters as $counter) {
      $this->assertSame('x', file_get_contents($counter), 'Job ran exactly once.');
    }
  }

  /**
   * A single failing job still gets its retry: "all failed" means nothing
   * at n=1, and the retry is cheap.
   */
  public function testSingleFailedJobStillRetries(): void {
    $counter = $this->scratchFile();
    $code = sprintf(
      'file_put_contents(%s, "x", FILE_APPEND); exit(1);',
      var_export($counter, TRUE)
    );

    $pool = new ProcessPool(2);
    $results = $pool->run(['only' => $this->phpJob($code)]);

    $this->assertSame(1, $results['only']['exit']);
    $this->assertSame('xx', file_get_contents($counter), 'Job ran twice.');
  }

  /**
   * Jobs in the same group never overlap when the group cap is 1.
   */
  public function testGroupCapPreventsOverlap(): void {
    $files = [$this->scratchFile(), $this->scratchFile()];
    $jobs = [];
    $groups = [];
    foreach ($files as $i => $file) {
      $jobs["job{$i}"] = $this->phpJob(sprintf(
        '$s = microtime(TRUE); usleep(300000); file_put_contents(%s, $s . " " . microtime(TRUE));',
        var_export($file, TRUE)
      ));
      $groups["job{$i}"] = 'app';
    }

    $pool = new ProcessPool(4, 1);
    $results = $pool->run($jobs, $groups);

    $this->assertSame([0, 0], array_column($results, 'exit'));
    [$s1, $e1] = array_map('floatval', explode(' ', file_get_contents($files[0])));
    [$s2, $e2] = array_map('floatval', explode(' ', file_get_contents($files[1])));
    $this->assertTrue($s2 >= $e1 || $s1 >= $e2, 'Same-group jobs ran sequentially.');
  }

  /**
   * The progress callback fires once per finished job with its result.
   */
  public function testProgressCallback(): void {
    $pool = new ProcessPool(2);
    $seen = [];

    $pool->run(
      [
        'a' => $this->phpJob('exit(0);'),
        'b' => $this->phpJob('exit(0);'),
      ],
      [],
      function (int $done, int $total, ?string $key, ?array $result) use (&$seen) {
        if ($key !== NULL) {
          $seen[$key] = $result['exit'];
        }
      }
    );

    $this->assertSame(['a' => 0, 'b' => 0], $seen);
  }

}
