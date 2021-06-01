<?php

namespace Drush\Commands;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\AbstractStructuredList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Consolidation\SiteProcess\ProcessManagerAwareInterface;
use Consolidation\SiteProcess\ProcessManagerAwareTrait;
use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Consolidation\SiteProcess\SiteProcess;
use Drupal\user\Entity\User;
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
   */
  public function databaseSize() {
    $selfRecord = $this->siteAliasManager()->getSelf();

    /** @var SiteProcess $process */
    $process = $this->processManager()->drush($selfRecord, 'core-status', [], ['fields' => 'db-name', 'format' => 'json']);
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

  /**
   * Determine if a site is inactive based on user activity.
   *
   * @command uiowa:site:inactive
   *
   * @aliases usi
   *
   * @bootstrap full
   *
   * @field-labels
   *   email: User Email
   *
   * @table-style default
   *
   * @default-fields email
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   */
  public function inactive($last_login = '6 months ago') {
    $timestamp = strtotime($last_login);

    $query = \Drupal::entityQuery('user')
      ->condition('uid', 0, '!=')
      ->condition('roles', 'administrator', 'NOT IN')
      ->condition('login', $timestamp, '>=');


    // If the query is empty, there are no active non-admin users.
    $active = !empty($query->execute());

    if (!$active) {
      $this->logger()->info('Non-admins HAVE NOT accessed the site recently.');

      // Get a list of webmaster emails for contacting.
      $query = \Drupal::entityQuery('user')
        ->condition('uid', 0, '!=')
        ->condition('roles', 'webmaster', 'IN');

      $ids = $query->execute();

      $rows = [];

      // Get a list of webmaster emails for contact.
      foreach (User::loadMultiple($ids) as $user) {
        $rows[] = [
         'email' => $user->getEmail()
        ];
      }

      $result = new RowsOfFields($rows);
      $result->addRendererFunction(function ($key, $cellData, FormatterOptions $options) {
        if (is_array($cellData)) {
          return implode("\n", $cellData);
        }
        return $cellData;
      });

      return $result;

    }
    else {
      $this->logger()->notice('Non-admins HAVE accessed the site recently.');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @hook post-command sql-sanitize
   */
  public function sanitize($result, CommandData $commandData) {
    $record = $this->siteAliasManager()->getSelf();

    foreach ($this->sanitizedConfig as $config) {
      /** @var SiteProcess $process */
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
    ];

    foreach ($configs as $config) {
      /** @var SiteProcess $process */
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
