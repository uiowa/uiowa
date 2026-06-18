<?php

namespace SiteNow\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists available commands and the environment each one runs in.
 *
 * Overrides the built-in list command so the output shows whether a command
 * runs on the host shell or inside the DDEV container.
 */
#[AsCommand(
  name: 'list',
  description: 'List available commands and where each one runs.',
)]
class ListCommand extends Command {

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $rows = [];
    // all() keys each command by its name and by every alias; skip the alias
    // keys so a command with aliases isn't listed more than once.
    foreach ($this->getApplication()->all() as $name => $command) {
      if ($command->isHidden() || $name !== $command->getName()) {
        continue;
      }
      $rows[] = [
        $command->getName(),
        implode(', ', $command->getAliases()),
        $command instanceof EnvironmentAwareInterface ? $command->environment() : '—',
        $command->getDescription(),
      ];
    }

    usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

    $io->title('SiteNow CLI (sn)');
    $io->text('Run host commands directly. Run container commands via "ddev exec" (see the "Runs in" column).');
    $io->newLine();
    $io->table(['Command', 'Aliases', 'Runs in', 'Description'], $rows);

    return Command::SUCCESS;
  }

}
