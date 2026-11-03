<?php

namespace Uiowa\Tests\PHPUnit\Unit;

use Drupal\Tests\UnitTestCase;
use SiteNow\Command\SplitRefreshCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit tests for the checks split:refresh makes before touching anything.
 *
 * Each test runs against a throwaway git repository and stops before the
 * command reaches DDEV.
 *
 * @group unit
 */
class SplitRefreshTest extends UnitTestCase {

  /**
   * The throwaway repository root.
   */
  private string $fixture;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (getenv('IS_DDEV_PROJECT') || getenv('AH_SITE_ENVIRONMENT')) {
      $this->markTestSkipped('split:refresh only runs on the host shell.');
    }
    $this->fixture = sys_get_temp_dir() . '/split-refresh-test-' . uniqid();
    mkdir($this->fixture);
    $this->git('init', '--quiet', '--initial-branch=main');
    file_put_contents("{$this->fixture}/README.md", "fixture\n");
    $this->git('add', 'README.md');
    $this->git('-c', 'user.name=Test', '-c', 'user.email=test@example.com', 'commit', '--quiet', '-m', 'Initial');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->fixture)) {
      exec('rm -rf ' . escapeshellarg($this->fixture));
    }
    parent::tearDown();
  }

  /**
   * Run git in the throwaway repository.
   */
  private function git(string ...$args): void {
    exec('git -C ' . escapeshellarg($this->fixture) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $output, $code);
    $this->assertSame(0, $code, implode("\n", $output));
  }

  /**
   * Run the command and return the tester.
   */
  private function execute(array $options = []): CommandTester {
    $tester = new CommandTester(new SplitRefreshCommand($this->fixture));
    $tester->execute($options, ['interactive' => FALSE]);
    return $tester;
  }

  /**
   * The command refuses to run on the base branch.
   */
  public function testRefusesOnBaseBranch(): void {
    $tester = $this->execute();
    $this->assertSame(1, $tester->getStatusCode());
    $this->assertStringContainsString('Create a branch for the exports first', $tester->getDisplay());
  }

  /**
   * The command refuses to switch branches over uncommitted tracked changes.
   */
  public function testRefusesWithTrackedChanges(): void {
    $this->git('switch', '--quiet', '-c', 'exports');
    file_put_contents("{$this->fixture}/README.md", "changed\n");
    $tester = $this->execute();
    $this->assertSame(1, $tester->getStatusCode());
    $this->assertStringContainsString('Commit or discard changes to tracked files first', $tester->getDisplay());
  }

  /**
   * Untracked files do not block a run, but a missing base branch does.
   */
  public function testRefusesWhenBaseBranchIsMissing(): void {
    $this->git('switch', '--quiet', '-c', 'exports');
    file_put_contents("{$this->fixture}/untracked.txt", "left alone\n");
    $tester = $this->execute(['--base' => 'nonexistent']);
    $this->assertSame(1, $tester->getStatusCode());
    $this->assertStringContainsString('Base branch nonexistent does not exist', $tester->getDisplay());
  }

}
