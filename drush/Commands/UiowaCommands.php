<?php

namespace Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Symfony\Component\Console\Input\InputInterface;

/**
 * General policy commands and hooks for the application.
 */
class UiowaCommands extends DrushCommands implements SiteAliasManagerAwareInterface, ProcessManagerAwareInterface {
  use SiteAliasManagerAwareTrait;
  use ProcessManagerAwareTrait;

  /**
   * Add database size to status command output.
   *
   * @param mixed $result
   *   The command result.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook alter core:status
   *
   * @return result
   *   The altered command result.
   */
  public function alterStatus($result, CommandData $commandData) {
    if ($app = getenv('AH_SITE_GROUP')) {
      $result['application'] = $app;
    }

    if (isset($result['bootstrap']) && $result['bootstrap'] == 'Successful') {
      $db = $result['db-name'];
      $selfRecord = $this->siteAliasManager()->getSelf();
      $args = ["SELECT SUM(ROUND(((data_length + index_length) / 1024 / 1024), 2)) AS \"Size\" FROM information_schema.TABLES WHERE table_schema = \"$db\";"];
      $options = ['yes' => TRUE];
      $process = $this->processManager()->drush($selfRecord, 'sql:query', $args, $options);
      $process->mustRun();
      $output = trim($process->getOutput());
      $result['db-size'] = $output . " MB";
    }

    return $result;
  }

  /**
   * Add the DB size field label to the status command annotation data.
   *
   * @hook init core:status
   */
  public function initStatus(InputInterface $input, AnnotationData $annotationData) {
    $annotationData->append('field-labels', "\n application: Application");
    $annotationData->append('field-labels', "\n db-size: DB Size");

    $defaults = $annotationData->getList('default-fields');
    $key = array_search('db-name', $defaults);
    array_splice($defaults, $key, 0, 'db-size');
    array_unshift($defaults, 'application');


    $annotationData->set('default-fields', $defaults);
    $input->setOption('fields', $defaults);
  }

  /**
   * Invoke BLT update command after sql:sync for remote targets only.
   *
   * @param mixed $result
   *   The command result.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook post-command sql:sync
   *
   * @throws \Exception
   */
  public function postSqlSync($result, CommandData $commandData) {
    $record = $this->siteAliasManager()->getAlias($commandData->input()->getArgument('target'));

    if ($record->isRemote()) {
      $process = $this->processManager()->drush($record, 'cache:rebuild');
      $process->run($process->showRealtime());

      $process = $this->processManager()->siteProcess(
        $record,
        [
          './vendor/bin/blt',
          'drupal:update',
        ],
        [
          'site' => $record->uri(),
        ]
      );

      $process->setWorkingDirectory($record->root() . '/..');
      $process->run($process->showRealtime());
    }
  }

  /**
   * Show tables larger than the input size.
   *
   * @param int $size
   *   The size in megabytes of table to filter on. Defaults to 1 MB.
   * @param mixed $options
   *   The command options.
   *
   * @command uiowa:sql:tables
   *
   * @aliases ust
   *
   * @field-labels
   *   table: Table
   *   size: Size
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Tables in RowsOfFields output formatter.
   */
  public function sqlTables(int $size = 1, $options = ['format' => 'table']) {
    $size = $this->input()->getArgument('size') * 1024 * 1024;
    $selfRecord = $this->siteAliasManager()->getSelf();
    $args = ["SELECT table_name AS \"Tables\", ROUND(((data_length + index_length) / 1024 / 1024), 2) \"Size in MB\" FROM information_schema.TABLES WHERE table_schema = DATABASE() AND (data_length + index_length) > $size ORDER BY (data_length + index_length) DESC;"];
    $options = ['yes' => TRUE];
    $process = $this->processManager()->drush($selfRecord, 'sql:query', $args, $options);
    $process->mustRun();
    $output = $process->getOutput();

    $rows = [];

    $output = explode(PHP_EOL, $output);
    foreach ($output as $line) {
      if (!empty($line)) {
        list($table, $table_size) = explode("\t", $line);

        $rows[] = [
          'table' => $table,
          'size' => $table_size . ' MB',
        ];
      }
    }

    $data = new RowsOfFields($rows);
    $data->addRendererFunction(function ($key, $cellData) {
      if ($key == 'first') {
        return "<comment>$cellData</>";
      }

      return $cellData;
    });

    return $data;
  }

}
