<?php

namespace SiteNow\Command;

use SiteNow\Config\Applications;
use SiteNow\Traits\ParsesListOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Distributes a built artifact to the Acquia Cloud git remotes.
 *
 * Commits the artifact produced by scripts/deploy/build.sh and pushes it to
 * each application's git remote. Two paths:
 *   --branch  dev/test: force-push the disposable {branch}-build branch.
 *   --tag     release:  push the {tag}-build tag.
 *
 * The build dir has no .git (the build excludes it), so a fresh repository is
 * initialised here for each run; there is no persistent deploy repository.
 * Pushing uses the deploy SSH key.
 */
#[AsCommand(
  name: 'deploy:distribute',
  description: 'Commit the built artifact and push it to the Acquia Cloud git remotes.',
)]
class DeployDistributeCommand extends Command {

  use ParsesListOptions;

  /**
   * Identity recorded on the artifact commit (the artifact repo is throwaway).
   */
  const COMMITTER_NAME = 'SiteNow Deploy';
  const COMMITTER_EMAIL = 'noreply@uiowa.edu';

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. Locates the registry and the
   *   default build dir.
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
      ->addOption('build-dir', NULL, InputOption::VALUE_REQUIRED, 'Path to the built artifact directory.', 'deploy')
      ->addOption('branch', NULL, InputOption::VALUE_REQUIRED, 'Force-push the artifact to this build branch (dev/test path), e.g. develop-build.')
      ->addOption('tag', NULL, InputOption::VALUE_REQUIRED, 'Push the artifact under this tag (release path), e.g. 2025.10.0-build.')
      ->addOption('commit-msg', NULL, InputOption::VALUE_REQUIRED, 'Commit message for the artifact commit.')
      ->addOption('apps', NULL, InputOption::VALUE_REQUIRED, 'Comma-separated application names to push to (default: all registered).', '')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Prepare the commit locally and report the pushes without contacting the remotes.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $branch = $input->getOption('branch');
    $tag = $input->getOption('tag');
    if (($branch === NULL) === ($tag === NULL)) {
      $io->error('Pass exactly one of --branch (dev/test) or --tag (release).');
      return Command::FAILURE;
    }
    $ref = $branch ?? $tag;

    // Resolve and sanity-check the build dir.
    $build_dir = $input->getOption('build-dir');
    if ($build_dir[0] !== '/') {
      $build_dir = "{$this->repoRoot}/{$build_dir}";
    }
    if (!is_dir($build_dir) || !is_dir("{$build_dir}/docroot/core") || !is_file("{$build_dir}/vendor/autoload.php")) {
      $io->error("No built artifact at {$build_dir}. Run scripts/deploy/build.sh first.");
      return Command::FAILURE;
    }

    // Resolve target applications and their remotes.
    $registry = new Applications("{$this->repoRoot}/sitenow/applications.yml");
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

    $targets = [];
    foreach ($names as $name) {
      $remote = $registry->remote($name);
      if (!$remote) {
        $io->error("Application {$name} has no remote configured in sitenow/applications.yml.");
        return Command::FAILURE;
      }
      $targets[$name] = $remote;
    }

    $dry_run = (bool) $input->getOption('dry-run');
    $commit_msg = $input->getOption('commit-msg') ?: "SiteNow deploy artifact for {$ref}";

    $io->section($dry_run ? "Distribute {$ref} (dry run)" : "Distribute {$ref}");
    $io->writeln("Build dir: {$build_dir}");
    $io->listing(array_map(fn($n, $r) => "{$n}  ->  {$r}", array_keys($targets), $targets));

    // Commit the artifact into a fresh repository.
    try {
      $this->prepareArtifact($build_dir, $ref, $commit_msg, $tag !== NULL);
    }
    catch (\RuntimeException $e) {
      $io->error($e->getMessage());
      return Command::FAILURE;
    }

    // Push to each remote, isolating per-remote failures.
    $rows = [];
    $failed = 0;
    foreach ($targets as $name => $remote) {
      if ($dry_run) {
        $rows[] = [$name, 'would push', $this->pushDescription($branch !== NULL, $remote, $ref)];
        continue;
      }
      [$ok, $detail] = $this->push($build_dir, $remote, $ref, $branch !== NULL);
      $rows[] = [$name, $ok ? 'pushed' : 'FAILED', $detail];
      if (!$ok) {
        $failed++;
      }
    }

    $io->table(['Application', 'Result', 'Detail'], $rows);

    if ($dry_run) {
      $io->note('Dry run: the local artifact commit was prepared, but nothing was pushed.');
      return Command::SUCCESS;
    }

    if ($failed) {
      $io->error("{$failed} of " . count($targets) . ' push(es) failed.');
      return Command::FAILURE;
    }

    $io->success('Artifact distributed to all targets.');
    return Command::SUCCESS;
  }

  /**
   * Initialise a fresh repository in the build dir and commit the artifact.
   *
   * @param string $build_dir
   *   The built artifact directory.
   * @param string $ref
   *   The branch or tag name.
   * @param string $commit_msg
   *   The commit message.
   * @param bool $is_tag
   *   TRUE to also create a tag named $ref on the commit.
   *
   * @throws \RuntimeException
   *   If any git step fails.
   */
  private function prepareArtifact(string $build_dir, string $ref, string $commit_msg, bool $is_tag): void {
    $steps = [
      ['git', 'init', '-q'],
      ['git', 'config', 'user.name', self::COMMITTER_NAME],
      ['git', 'config', 'user.email', self::COMMITTER_EMAIL],
      ['git', 'add', '-A'],
      ['git', 'commit', '-q', '-m', $commit_msg],
    ];
    if ($is_tag) {
      $steps[] = ['git', 'tag', $ref];
    }

    foreach ($steps as $step) {
      $process = new Process($step, $build_dir);
      $process->setTimeout(NULL);
      $process->run();
      if (!$process->isSuccessful()) {
        $cmd = implode(' ', $step);
        throw new \RuntimeException("Artifact prep failed at `{$cmd}`: " . trim($process->getErrorOutput()));
      }
    }
  }

  /**
   * Push the prepared artifact to one remote.
   *
   * @param string $build_dir
   *   The built artifact directory (the repository being pushed).
   * @param string $remote
   *   The git remote URL.
   * @param string $ref
   *   The branch or tag name.
   * @param bool $is_branch
   *   TRUE for the dev/test branch path (force-push), FALSE for the tag path.
   *
   * @return array{0: bool, 1: string}
   *   Success flag and a short detail string.
   */
  private function push(string $build_dir, string $remote, string $ref, bool $is_branch): array {
    $cmd = $is_branch
      ? ['git', 'push', '--force', $remote, "HEAD:refs/heads/{$ref}"]
      : ['git', 'push', $remote, "refs/tags/{$ref}"];

    $process = new Process($cmd, $build_dir);
    $process->setTimeout(NULL);
    $process->run();

    if ($process->isSuccessful()) {
      return [TRUE, $is_branch ? "force-pushed {$ref}" : "pushed tag {$ref}"];
    }
    return [FALSE, trim($process->getErrorOutput()) ?: 'push failed'];
  }

  /**
   * Describe the push that would run, for dry-run output.
   */
  private function pushDescription(bool $is_branch, string $remote, string $ref): string {
    return $is_branch
      ? "git push --force {$remote} HEAD:refs/heads/{$ref}"
      : "git push {$remote} refs/tags/{$ref}";
  }

}
