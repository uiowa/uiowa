<?php

namespace Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * General policy commands and hooks for the application.
 */
class UiowaCommands extends DrushCommands implements SiteAliasManagerAwareInterface, ProcessManagerAwareInterface, SanitizePluginInterface {
  use SiteAliasManagerAwareTrait;
  use ProcessManagerAwareTrait;

  /**
   * Configuration that should sanitized.
   *
   * @var array
   */
  protected $sanitizedConfig = [];

  /**
   * Add additional fields to status command output.
   *
   * @param mixed $result
   *   The command result.
   * @param \Consolidation\AnnotatedCommand\CommandData $commandData
   *   The command data.
   *
   * @hook alter core:status
   *
   * @return array
   *   The altered command result.
   */
  public function alterStatus($result, CommandData $commandData) {
    if ($app = getenv('AH_SITE_GROUP')) {
      $result['application'] = $app;
    }

    return $result;
  }

  /**
   * Add custom field labels to the status command annotation data.
   *
   * @hook init core:status
   */
  public function initStatus(InputInterface $input, AnnotationData $annotationData) {
    $fields = explode(',', $input->getOption('fields'));
    $defaults = $annotationData->getList('default-fields');

    // If no specific fields were requested, add ours to the defaults.
    // @todo Is there a more-defined context for when to do this?
    if ($fields == $defaults) {
      $annotationData->append('field-labels', "\n application: Application");
      array_unshift($defaults, 'application');
      $annotationData->set('default-fields', $defaults);
      $input->setOption('fields', $defaults);
    }
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
   * Show the database size.
   *
   * @command uiowa:database:size
   *
   * @aliases uds
   *
   * @field-labels
   *   table: Table
   *   size: Size
   *
   * @return string
   *   The size of the database in megabytes.
   */
  public function databaseSize() {
    $selfRecord = $this->siteAliasManager()->getSelf();

    /** @var \Consolidation\SiteProcess\SiteProcess $process */
    $process = $this->processManager()->drush($selfRecord, 'core-status', [], [
      'fields' => 'db-name',
      'format' => 'json',
    ]);

    $process->run();
    $result = $process->getOutputAsJson();

    if (isset($result['db-name'])) {
      $db = $result['db-name'];
      $args = ["SELECT SUM(ROUND(((data_length + index_length) / 1024 / 1024), 2)) AS \"Size\" FROM information_schema.TABLES WHERE table_schema = \"$db\";"];
      $options = ['yes' => TRUE];
      $process = $this->processManager()->drush($selfRecord, 'sql:query', $args, $options);
      $process->mustRun();
      $output = trim($process->getOutput());
      return "{$output} MB";
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
   * @command uiowa:table:size
   *
   * @aliases uts
   *
   * @field-labels
   *   table: Table
   *   size: Size
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Tables in RowsOfFields output formatter.
   */
  public function tableSize(int $size = 1, $options = ['format' => 'table']) {
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
        [$table, $table_size] = explode("\t", $line);

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

  /**
   * {@inheritdoc}
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    $record = $this->siteAliasManager()->getSelf();

    foreach ($this->sanitizedConfig as $config) {
      /** @var \Consolidation\SiteProcess\SiteProcess $process */
      $process = $this->processManager()->drush($record, 'config:delete', [
        $config,
      ]);

      $process->run();

      if ($process->isSuccessful()) {
        $this->logger()->success(dt('Deleted @config configuration.', [
          '@config' => $config,
        ]));
      }
      else {
        $this->logger()->warning(dt('Unable to delete @config configuration.'), [
          '@config' => $config,
        ]);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input) {
    $record = $this->siteAliasManager()->getSelf();

    $configs = [
      'migrate_plus.migration_group.sitenow_migrate',
      'sitenow_dispatch.settings',
    ];

    foreach ($configs as $config) {
      /** @var \Consolidation\SiteProcess\SiteProcess $process */
      $process = $this->processManager()->drush($record, 'config:get', [
        $config,
      ]);

      $process->run();

      if ($process->isSuccessful()) {
        $this->sanitizedConfig[] = $config;

        $messages[] = dt('Delete the @config configuration.', [
          '@config' => $config,
        ]);
      }
    }
  }

}
