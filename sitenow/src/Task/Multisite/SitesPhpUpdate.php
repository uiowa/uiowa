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

  private string $host = '';
  private string $local = '';
  private string $dev = '';
  private string $test = '';
  private string $prod = '';
  private string $appendedBlock = '';

  public function __construct(private string $filePath) {}

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

  public function simulate($context): void {
    $this->printTaskInfo("Would append <info>{$this->host}</info> directory aliases to {$this->filePath}.");
  }

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
