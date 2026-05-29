<?php

namespace SiteNow\Task\Multisite;

use Robo\Contract\RollbackInterface;
use Robo\Contract\SimulatedInterface;
use Robo\Result;
use Robo\Task\BaseTask;

/**
 * Appends (or removes) site directory aliases in docroot/sites/sites.php.
 */
class SitesPhpUpdate extends BaseTask implements SimulatedInterface, RollbackInterface {

  /**
   * The public multisite host.
   *
   * @var string
   */
  private string $host = '';

  /**
   * The local internal domain.
   *
   * @var string
   */
  private string $local = '';

  /**
   * The dev internal domain.
   *
   * @var string
   */
  private string $dev = '';

  /**
   * The test internal domain.
   *
   * @var string
   */
  private string $test = '';

  /**
   * The prod internal domain.
   *
   * @var string
   */
  private string $prod = '';

  /**
   * The block appended to sites.php, retained for rollback.
   *
   * @var string
   */
  private string $appendedBlock = '';

  /**
   * Constructs a SitesPhpUpdate task.
   *
   * @param string $filePath
   *   Absolute path to sites.php.
   */
  public function __construct(private string $filePath) {}

  /**
   * Configures the task to add directory aliases for a host.
   *
   * @return $this
   */
  public function add(
    string $host,
    string $local,
    string $dev,
    string $test,
    string $prod,
  ): static {
    $this->host = $host;
    $this->local = $local;
    $this->dev = $dev;
    $this->test = $test;
    $this->prod = $prod;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function run(): Result {
    $this->appendedBlock = <<<EOD

// Directory aliases for {$this->host}.
\$sites['{$this->local}'] = '{$this->host}';
\$sites['{$this->dev}'] = '{$this->host}';
\$sites['{$this->test}'] = '{$this->host}';
\$sites['{$this->prod}'] = '{$this->host}';

EOD;
    if (file_put_contents($this->filePath, $this->appendedBlock, FILE_APPEND) === FALSE) {
      return Result::error($this, "Failed to write to {$this->filePath}.");
    }
    $this->printTaskInfo("Appended <info>{$this->host}</info> directory aliases to sites.php.");
    return Result::success($this);
  }

  /**
   * {@inheritdoc}
   */
  public function simulate($context): void {
    $this->printTaskInfo("Would append <info>{$this->host}</info> directory aliases to {$this->filePath}.");
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(): void {
    if (!$this->appendedBlock || !file_exists($this->filePath)) {
      return;
    }
    $content = file_get_contents($this->filePath);
    $updated = str_replace($this->appendedBlock, '', $content);
    file_put_contents($this->filePath, $updated);
    $this->printTaskInfo("Rolled back sites.php entries for {$this->host}.");
  }

}
