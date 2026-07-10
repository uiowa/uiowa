<?php

namespace SiteNow\Command;

use AcquiaCloudApi\Endpoints\Code;
use AcquiaCloudApi\Endpoints\Environments;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use SiteNow\Config\Applications;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\PlanTrait;
use SiteNow\Traits\ParsesListOptions;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Switches Acquia Cloud environments to a built release tag.
 *
 * The activation half of a release: points each application's environment at a
 * {tag}-build tag already distributed to its git remote, through the Acquia
 * Cloud API. The per-application switch is isolated so one failure does not
 * stop the rest.
 */
#[AsCommand(
  name: 'deploy:activate',
  description: 'Switch Acquia Cloud environments to a built release tag.',
)]
class DeployActivateCommand extends Command {

  use SiteNowCommandsTrait;
  use CommonChecks;
  use ParsesListOptions;
  use PlanTrait;

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates the application registry
   *   and resolves the release tag from origin.
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
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'Release tag to activate, e.g. 3.32.42-build. Defaults to the latest build tag on origin.', '')
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated application subset (default: all registered).', '')
      ->addOption('env', NULL, InputOption::VALUE_REQUIRED, 'Environment to switch.', 'prod')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Show the plan and exit without switching.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $registry = new Applications("{$this->repoRoot}/sitenow/applications.yml");

    $tag = trim($input->getOption('tag')) ?: $this->latestBuildTag();
    if (!$tag) {
      $io->error('Could not resolve a release tag from origin. Pass --tag explicitly.');
      return Command::FAILURE;
    }

    $names = $registry->names();
    $requested = $this->parseList($input->getOption('apps'));
    if ($requested) {
      $unknown = array_diff($requested, $names);
      if ($unknown) {
        $io->error('Unknown application(s): ' . implode(', ', $unknown));
        return Command::FAILURE;
      }
      $names = $requested;
    }

    $env = trim($input->getOption('env')) ?: 'prod';
    $dry_run = (bool) $input->getOption('dry-run');

    // Reuse the shared host-shell and credentials preconditions.
    $validation = $this->runChecks([
      $this->checkHostShell(),
      $this->checkAcquiaCredentials(),
    ]);

    $summary = [
      ['label' => 'Tag', 'value' => $tag],
      ['label' => 'Environment', 'value' => $env],
      ['label' => 'Applications', 'value' => implode(', ', $names)],
    ];
    $steps = array_map(fn($name) => ['label' => "Switch {$name} {$env} to {$tag}"], $names);

    $this->renderPlan($io, "deploy:activate {$tag}", $summary, $validation, $steps);

    if ($validation['overall'] === CheckStatus::Fail) {
      return Command::FAILURE;
    }
    if ($dry_run) {
      return Command::SUCCESS;
    }
    if (!$io->confirm("Switch the {$env} environment to {$tag} for the applications above?", FALSE)) {
      $io->writeln('Aborted.');
      return Command::SUCCESS;
    }

    return $this->switchApplications($io, $registry, $names, $env, $tag);
  }

  /**
   * Switch each application's environment to the tag, isolating failures.
   *
   * @return int
   *   A console exit code; FAILURE if any application did not switch.
   */
  private function switchApplications(SymfonyStyle $io, Applications $registry, array $names, string $env, string $tag): int {
    $client = $this->requireAcquiaClient($io);
    if (!$client) {
      return Command::FAILURE;
    }
    $environments = new Environments($client);
    $code = new Code($client);

    $failed = [];
    foreach ($names as $name) {
      try {
        $target = NULL;
        foreach ($environments->getAll($registry->uuid($name)) as $environment) {
          if ($environment->name === $env) {
            $target = $environment;
            break;
          }
        }
        if (!$target) {
          $io->warning("{$name}: no {$env} environment found; skipping.");
          $failed[] = $name;
          continue;
        }
        $code->switch($target->uuid, $tag);
        $io->writeln("{$name}: switch to {$tag} started.");
      }
      catch (\Throwable $e) {
        $io->error("{$name}: switch failed: {$e->getMessage()}");
        $failed[] = $name;
      }
    }

    if ($failed) {
      $io->error('Activation failed for: ' . implode(', ', $failed));
      return Command::FAILURE;
    }
    $io->success("Activation of {$tag} started on all applications.");
    return Command::SUCCESS;
  }

  /**
   * Resolve the latest {tag}-build tag from origin by semantic version.
   *
   * The release tags live on origin; distribute pushes the matching
   * {tag}-build to each Acquia remote, which is what an environment switches
   * to.
   *
   * @return string|null
   *   The newest tag with a -build suffix, or NULL if none are found.
   */
  private function latestBuildTag(): ?string {
    $process = new Process(['git', 'ls-remote', '--tags', '--refs', 'origin'], $this->repoRoot);
    $process->run();
    if (!$process->isSuccessful()) {
      return NULL;
    }

    return $this->resolveBuildTag($process->getOutput());
  }

  /**
   * Resolve the newest build tag from `git ls-remote --tags` output.
   *
   * Collects the tag names, orders them by semantic version, and appends the
   * -build suffix distribute pushes to the Acquia remotes. Kept separate from
   * the git call so the parse and ordering are testable without a remote.
   *
   * @param string $output
   *   The raw `git ls-remote --tags --refs origin` output.
   *
   * @return string|null
   *   The newest tag with a -build suffix, or NULL if none are found.
   */
  protected function resolveBuildTag(string $output): ?string {
    $parser = new VersionParser();
    $tags = [];
    foreach (explode("\n", trim($output)) as $line) {
      if (!str_contains($line, 'refs/tags/')) {
        continue;
      }
      $tag = explode('refs/tags/', $line)[1];
      // Skip anything that is not a valid semantic version, e.g. a legacy or
      // ad-hoc ref. Semver::rsort normalizes every element and throws on the
      // first unparseable one, so a single stray tag would otherwise abort the
      // whole resolution.
      try {
        $parser->normalize($tag);
      }
      catch (\UnexpectedValueException $e) {
        continue;
      }
      $tags[] = $tag;
    }
    if (!$tags) {
      return NULL;
    }

    $tags = Semver::rsort($tags);
    return "{$tags[0]}-build";
  }

}
