<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

/**
 * Lists routes declared by custom modules.
 *
 * Scans *.routing.yml under the custom/uiowa module trees and reports each
 * declared path. Reads files only, so it runs on the host or in DDEV.
 */
#[AsCommand(
  name: 'routes:custom',
  description: 'List routes declared by custom modules.',
)]
class RoutesCustomCommand extends Command {

  const HEADERS = ['Search Path', 'Module', 'Route', 'URL'];

  // Module trees to scan, relative to the docroot. Each *.routing.yml is one
  // directory deep (a module directory) within these paths.
  const SEARCH_PATHS = [
    'modules/custom',
    'modules/uiowa',
    'sites/**/modules',
  ];

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. The docroot is resolved beneath it.
   */
  public function __construct(
    private string $repoRoot = '',
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $docroot = "{$this->repoRoot}/docroot";

    $rows = [];
    foreach (self::SEARCH_PATHS as $path) {
      foreach (glob("{$docroot}/{$path}/*/*.routing.yml") as $file) {
        // The module directory is the second-to-last path segment.
        $parts = explode('/', $file);
        $module = $parts[count($parts) - 2];

        $yaml = Yaml::parseFile($file) ?? [];
        foreach ($yaml as $route_name => $route) {
          // Skip route_callbacks and other entries without a static path.
          if (!isset($route['path'])) {
            continue;
          }
          $rows[] = [$path, $module, $route_name, $route['path']];
        }
      }
    }

    if (empty($rows)) {
      $io->writeln('No custom routes found.');
      return Command::SUCCESS;
    }

    sort($rows);
    $io->table(self::HEADERS, $rows);

    return Command::SUCCESS;
  }

}
