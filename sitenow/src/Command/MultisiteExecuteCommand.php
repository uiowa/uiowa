<?php

namespace SiteNow\Command;

use SiteNow\Process\FleetRunner;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Executes one drush command across manifest-selected sites.
 *
 * Replaces the BLT `uiowa:multisite:execute` (ume) command. The drush
 * command and everything after it pass through as an argv array — options
 * and arguments arrive at drush byte-for-byte, with no shell escaping
 * layer in between.
 */
#[AsCommand(
  name: 'multisite:execute',
  description: '(ddev required) Execute a drush command across manifest-selected sites.',
  aliases: ['me', 'ume'],
)]
class MultisiteExecuteCommand extends Command {

  use SiteNowCommandsTrait;
  use ParsesListOptions;

  /**
   * Exit code for a run that completed but had per-site failures.
   *
   * Distinct from FAILURE (1, the command itself could not run) so callers
   * can tell "some sites failed" from "nothing ran".
   */
  const EXITCODE_PARTIAL = 2;

  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates blt/manifest.yml.
   */
  public function __construct(
    private string $repoRoot = '',
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure(): void {
    $this
      ->addArgument('cmd', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The drush command with its arguments and options. Separate from this command\'s own options with "--", e.g.: sn ume --apps=uiowa09 -- pm:list --status=enabled')
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated app names to run against (e.g. uiowa02,uiowa03). Defaults to all apps.', '')
      ->addOption('exclude', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated site domains to skip.', '')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Target environment: dev, test, or prod.', 'prod')
      ->addOption('concurrency', NULL, InputOption::VALUE_REQUIRED, 'Maximum simultaneous drush processes. Defaults to 8 per app in scope, capped at 32. At most 8 run per app at once regardless.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Print the per-site drush invocations without running them.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip the confirmation prompt.')
      ->setHelp(<<<'HELP'
Everything after "--" is passed to drush verbatim, one site at a time.

The per-site drush processes run non-interactively — drush can't stop and ask a
question mid-fleet. Drush commands that normally ask their own confirmation
(pm:uninstall, config:set, ...) need their own -y inside the passthrough, or
every site will auto-answer with drush's default (usually cancel).

Examples:
  # Cache rebuild on every site of two apps:
  ddev exec ./sn ume --apps=uiowa02,uiowa03 -y -- cr

  # Arguments with spaces/quotes pass through unmodified:
  ddev exec ./sn ume --apps=uiowa09 -- sql:query "SELECT COUNT(*) FROM node"

  # Two different -y's: ours skips the fleet confirmation, drush's skips its own:
  ddev exec ./sn ume -y --apps=uiowa09 -- pm:uninstall some_module -y

  # Preview what would run, without executing anything:
  ddev exec ./sn ume --dry-run -- cron
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $err = $io->getErrorStyle();

    $cmd = $input->getArgument('cmd');
    $apps = $this->parseList($input->getOption('apps'));
    $exclude = $this->parseList($input->getOption('exclude'));
    $env = $input->getOption('env');
    $dry_run = (bool) $input->getOption('dry-run');

    if (!in_array($env, self::ENVIRONMENTS, TRUE)) {
      $err->error("Invalid environment '{$env}'. Must be one of: " . implode(', ', self::ENVIRONMENTS));
      return Command::FAILURE;
    }

    $runner = new FleetRunner("{$this->repoRoot}/blt/manifest.yml");

    try {
      $selection = $runner->select($apps, $exclude);
    }
    catch (\RuntimeException $e) {
      $err->error($e->getMessage());
      return Command::FAILURE;
    }

    $site_count = array_sum(array_map('count', $selection));
    if ($site_count === 0) {
      $err->error('No sites matched the selection.');
      return Command::FAILURE;
    }

    $concurrency_option = $input->getOption('concurrency');
    if ($concurrency_option !== NULL && (!ctype_digit($concurrency_option) || (int) $concurrency_option < 1)) {
      $err->error("Invalid --concurrency '{$concurrency_option}'. Must be a positive integer.");
      return Command::FAILURE;
    }
    $concurrency = $concurrency_option !== NULL
      ? (int) $concurrency_option
      : $runner->defaultConcurrency(count($selection));

    // A dry run touches nothing remote, so it can run anywhere.
    if ($dry_run) {
      ['jobs' => $jobs] = $runner->buildJobs($selection, $cmd, $env);
      $ssh_option = '--ssh-options=' . FleetRunner::SSH_OPTIONS;
      $io->writeln("Dry run: {$site_count} sites across " . count($selection) . " app(s), concurrency {$concurrency}.");
      $io->writeln('Each command also gets ' . escapeshellarg($ssh_option) . ' (SSH multiplexing, omitted below).');
      foreach ($jobs as $argv) {
        $argv = array_filter($argv, fn ($a) => $a !== $ssh_option);
        $io->writeln($this->renderArgv($argv));
      }
      return Command::SUCCESS;
    }

    if (!$this->requireDdev($io, $this->getName())) {
      return Command::FAILURE;
    }
    if (!$this->requireSshAgent($io)) {
      return Command::FAILURE;
    }

    $cmd_display = implode(' ', $cmd);
    if (!$input->getOption('yes')) {
      $question = "Run `drush {$cmd_display}` on {$site_count} sites across " . count($selection) . " app(s) ({$env})?";
      if (!$io->confirm($question, FALSE)) {
        $io->writeln('Aborted.');
        return Command::FAILURE;
      }
    }

    $err->writeln("Running on {$site_count} sites, {$concurrency} at a time...");
    $start = microtime(TRUE);
    $verbose = $output->isVerbose();

    $results = $runner->run($selection, $cmd, $env, $concurrency, function (int $done, int $total, ?string $key, ?array $result) use ($io, $verbose) {
      if ($key === NULL) {
        return;
      }
      if ($result['exit'] === 0) {
        $io->writeln("<info>✔</info> [{$done}/{$total}] {$key}");
        if ($verbose && trim($result['output']) !== '') {
          $io->writeln($this->indent($result['output']));
        }
      }
      else {
        $io->writeln("<error>✖</error> [{$done}/{$total}] {$key} (exit {$result['exit']})");
      }
    });

    $elapsed = round(microtime(TRUE) - $start, 1);
    $failed = array_filter($results, fn (array $r) => $r['exit'] !== 0);
    $ok_count = count($results) - count($failed);

    $io->writeln('');
    $io->writeln("Finished in {$elapsed}s: {$ok_count} succeeded, " . count($failed) . ' failed.');

    if (!empty($failed)) {
      $err->writeln('');
      $err->writeln('<comment>[WARNING] ' . count($failed) . ' site(s) failed:</comment>');
      foreach ($failed as $r) {
        $err->writeln("  {$r['app']}: {$r['site']} — " . $this->failureReason($r));
      }
      return self::EXITCODE_PARTIAL;
    }

    return Command::SUCCESS;
  }

  /**
   * Summarize why a site failed, from its stderr (or stdout) tail.
   *
   * @param array{exit: int, output: string, error: string} $result
   *   The per-site result.
   *
   * @return string
   *   A one-line reason.
   */
  protected function failureReason(array $result): string {
    $source = trim($result['error']) !== '' ? $result['error'] : $result['output'];
    $lines = array_filter(array_map('trim', preg_split('/\R/', $source)), fn ($l) => $l !== '');
    $tail = $lines ? end($lines) : '';

    return "exit {$result['exit']}" . ($tail !== '' ? " ({$tail})" : '');
  }

  /**
   * Render an argv array as a copy-pasteable shell command.
   *
   * The pool executes argv arrays directly with no shell, but the dry run
   * prints them for humans to read and paste — so elements the shell would
   * split or mangle get quoted.
   *
   * @param array<int, string> $argv
   *   The argv array.
   *
   * @return string
   *   The shell-safe command line.
   */
  protected function renderArgv(array $argv): string {
    return implode(' ', array_map(
      fn ($a) => preg_match('/[\s\'"\\\\$]/', $a) ? escapeshellarg($a) : $a,
      $argv
    ));
  }

  /**
   * Indent multi-line process output for display under its site line.
   *
   * @param string $text
   *   The raw output.
   *
   * @return string
   *   The output with each line indented.
   */
  protected function indent(string $text): string {
    $lines = preg_split('/\R/', rtrim($text));

    return implode("\n", array_map(fn ($l) => "    {$l}", $lines));
  }

}
