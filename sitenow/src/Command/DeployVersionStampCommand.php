<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Stamps the Git version into the build artifact's custom .info.yml files.
 *
 * Called by scripts/deploy/build.sh after the artifact is assembled. The Git
 * version is read from the source repository (the build dir has no .git) and
 * written into custom profile, theme, and module .info.yml files that do not
 * already declare a version, mirroring how drupal.org packages a release.
 */
#[AsCommand(
  name: 'deploy:version-stamp',
  description: "Stamp the Git version into the build artifact's custom .info.yml files.",
)]
class DeployVersionStampCommand extends Command {

  /**
   * Custom code locations to stamp, relative to the build dir's docroot.
   *
   * Scanned one level deep, so an extension's top-level .info.yml is found
   * but nested ones are left alone.
   */
  const CUSTOM_DIRS = [
    'profiles/custom',
    'themes/custom',
    'modules/custom',
  ];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. The Git version is read here.
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
    $this->addOption('build-dir', NULL, InputOption::VALUE_REQUIRED, 'Path to the assembled build directory whose .info.yml files are stamped.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $build_dir = $input->getOption('build-dir');
    if (!$build_dir || !is_dir($build_dir)) {
      $io->error('A valid --build-dir is required.');
      return Command::FAILURE;
    }

    $version = $this->gitVersion();
    if ($version === NULL) {
      // A missing version is a warning, not a failed build.
      $io->warning('Unable to determine Git version; skipping .info.yml stamping.');
      return Command::SUCCESS;
    }

    $dirs = [];
    foreach (self::CUSTOM_DIRS as $dir) {
      $path = "{$build_dir}/docroot/{$dir}";
      if (is_dir($path)) {
        $dirs[] = $path;
      }
    }

    if (!$dirs) {
      $io->warning('No custom code directories found in the build dir; nothing to stamp.');
      return Command::SUCCESS;
    }

    $finder = (new Finder())
      ->files()
      ->in($dirs)
      ->depth('< 2')
      ->name('*.info.yml')
      ->sortByName();

    $stamped = 0;
    foreach ($finder as $file) {
      $info = Yaml::parseFile($file->getPathname());

      // Leave a file alone if it already declares a version.
      if (is_array($info) && isset($info['version'])) {
        continue;
      }

      // Append rather than re-dump so comments and formatting survive.
      $contents = rtrim($file->getContents(), "\n");
      file_put_contents($file->getPathname(), "{$contents}\nversion: '{$version}'\n");
      $io->writeln("Stamped {$version} into {$file->getRelativePathname()}", OutputInterface::VERBOSITY_VERBOSE);
      $stamped++;
    }

    $io->success("Stamped version {$version} into {$stamped} .info.yml file(s).");
    return Command::SUCCESS;
  }

  /**
   * Reads the Git version from the source repository.
   *
   * @return string|null
   *   The `git describe --tags` value, or NULL if it cannot be determined.
   */
  private function gitVersion(): ?string {
    $process = new Process(['git', 'describe', '--tags'], $this->repoRoot);
    $process->run();

    if (!$process->isSuccessful()) {
      return NULL;
    }

    $version = trim($process->getOutput());
    return $version !== '' ? $version : NULL;
  }

}
