<?php

namespace SiteNow\Robo\Plugin\Commands;

use AcquiaCloudApi\Endpoints\Databases;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Robo\Tasks;
use SiteNow\Robo\Plan\Precondition;
use SiteNow\Robo\Plan\PlanTrait;
use SiteNow\Robo\Task\Acquia\Tasks as AcquiaTasks;
use SiteNow\Robo\Task\Multisite\Tasks as MultisiteTasks;
use SiteNow\Robo\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Robo commands for SiteNow multisite management.
 */
class MultisiteCommands extends Tasks {

  use SiteNowCommandsTrait;
  use AcquiaTasks;
  use MultisiteTasks;
  use PlanTrait;

  const HEALTHCARE_APP = 'uiowa06';

  /**
   * Create a new SiteNow multisite.
   *
   * @param string $host
   *   The multisite URI host (e.g. newsite.uiowa.edu).
   *
   * @option no-commit Do not create a git commit.
   * @option no-db Do not create a cloud database.
   * @option requester The HawkID of the original requester.
   * @option split Config split(s) to activate. Comma-separate multiple values.
   * @option site-name The desired site name.
   * @option dry-run Show plan and exit; no side effects.
   * @option yes Apply without prompting. Blocked by any WARN.
   * @option output Output format. Pass 'json' for machine-readable plan.
   * @option app Override the target Acquia application.
   *
   * @command uiowa:multisite:create
   * @aliases umc
   *
   * @throws \Exception
   */
  public function create(
    string $host,
    array $options = [
      'no-commit' => FALSE,
      'no-db' => FALSE,
      'requester' => InputOption::VALUE_REQUIRED,
      'split' => InputOption::VALUE_REQUIRED,
      'site-name' => InputOption::VALUE_REQUIRED,
      'dry-run' => FALSE,
      'yes' => FALSE,
      'output' => '',
      'app' => InputOption::VALUE_REQUIRED,
    ],
  ): void {
    $decisions = $this->decide($host, $options);

    $title = "uiowa:multisite:create {$host}";

    if ($decisions['validation']['overall'] === Precondition::FAIL) {
      $this->renderPlan($title, [], $decisions['validation']);
      return;
    }

    // Resolve tied app selection.
    if ($decisions['decisions']['app'] === NULL) {
      if ($options['yes'] || $options['dry-run'] || $options['output'] === 'json') {
        $this->renderPlan($title, [], $decisions['validation']);
        $this->io()->warning('Application selection is ambiguous (tied DB counts). Use --app= to specify.');
        return;
      }
      $candidates = $decisions['decisions']['app_candidates'];
      $eligible = array_filter($candidates, fn($c) => $c['has_ssl'] && $c['name'] !== self::HEALTHCARE_APP);
      $names = array_column(array_values($eligible), 'name');
      $chosen = $this->askChoice('Which cloud application should be used?', $names);
      $decisions['decisions']['app'] = $eligible[$chosen] + ['reasoning' => 'Selected interactively.'];
    }

    $steps = $this->buildSteps($host, $options, $decisions);

    if ($options['output'] === 'json') {
      $out = $decisions;
      $out['actions_summary'] = array_column($steps, 'label');
      $this->output()->writeln(json_encode($out, JSON_PRETTY_PRINT));
      return;
    }

    $this->renderPlan($title, $this->planSummary($decisions), $decisions['validation'], $steps);

    if ($options['dry-run']) {
      return;
    }

    if ($options['yes']) {
      if ($decisions['validation']['overall'] === Precondition::WARN) {
        $this->io()->error('Aborting: --yes was passed but validation has a WARN. Resolve the warning or run interactively.');
        return;
      }
    }
    else {
      if ($this->promptApply() === 'n') {
        $this->say('Aborted.');
        return;
      }
    }

    $this->apply($steps);
  }

  /**
   * Queries Acquia Cloud and runs pre-flight checks.
   *
   * Returns a decisions array with 'input', 'decisions', and 'validation' keys.
   */
  private function decide(string $host, array $options): array {
    $root = getcwd();
    $checks = [];
    $umc_keys = ['no-commit', 'no-db', 'requester', 'split', 'site-name', 'dry-run', 'yes', 'output', 'app'];
    $flags = array_filter(
      array_intersect_key($options, array_flip($umc_keys)),
      fn($v) => $v !== NULL && $v !== FALSE && $v !== '' && $v !== InputOption::VALUE_REQUIRED
    );

    // --- Environment checks ---

    $checks['running_on_host_shell'] = $this->isDdev()
      ? Precondition::fail('running_on_host_shell', 'Must run on host shell, not inside DDEV. Use: ./vendor/bin/robo uiowa:multisite:create')
      : Precondition::pass('running_on_host_shell');

    $key = $this->getConfigValue('uiowa.credentials.acquia.key');
    $secret = $this->getConfigValue('uiowa.credentials.acquia.secret');
    $checks['has_acquia_credentials'] = ($key && $secret)
      ? Precondition::pass('has_acquia_credentials')
      : Precondition::fail('has_acquia_credentials', 'Acquia credentials not found. Set uiowa.credentials.acquia.key/secret in blt/local.blt.yml.');

    // --- Input checks ---

    $valid_host = (bool) preg_match(
      '/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)+$/',
      $host
    );
    $checks['hostname_format'] = $valid_host
      ? Precondition::pass('hostname_format')
      : Precondition::fail('hostname_format', "Invalid hostname: {$host}. Must be a valid dot-separated domain.");

    $checks['site_dir_does_not_exist'] = is_dir("{$root}/docroot/sites/{$host}")
      ? Precondition::fail('site_dir_does_not_exist', "Site directory docroot/sites/{$host} already exists.")
      : Precondition::pass('site_dir_does_not_exist');

    $manifest = file_exists("{$root}/blt/manifest.yml")
      ? (Yaml::parseFile("{$root}/blt/manifest.yml") ?? [])
      : [];
    $all_sites = array_merge(...(array_values($manifest) ?: [[]]));
    $checks['no_normalized_conflicts'] = in_array($host, $all_sites)
      ? Precondition::fail('no_normalized_conflicts', "Site {$host} already exists in blt/manifest.yml.")
      : Precondition::pass('no_normalized_conflicts');

    // Short-circuit before API calls if env/input checks fail.
    if (array_filter($checks, fn($c) => $c->isFail())) {
      return [
        'input' => ['host' => $host, 'db' => NULL, 'id' => NULL, 'flags' => $flags],
        'decisions' => ['app' => NULL, 'app_candidates' => []],
        'validation' => $this->buildValidation($checks),
      ];
    }

    // --- Acquia API ---

    $client = $this->getAcquiaCloudApiClient($key, $secret);
    $databases_api = new Databases($client);
    $environments_api = new Environments($client);
    $certificates_api = new SslCertificates($client);

    $ssl_parts = Multisite::getSslParts($host);
    $candidates = [];
    $has_ssl_coverage = FALSE;

    foreach ($this->getSortedApplications($client) as $app) {
      $app_name = str_replace('prod:', '', $app->hosting->id);
      $db_count = count($databases_api->getAll($app->uuid));
      $ssl_match = NULL;
      $related_match = NULL;
      $sans_count = NULL;

      foreach ($environments_api->getAll($app->uuid) as $env) {
        if ($env->name !== 'prod') {
          continue;
        }
        foreach ($certificates_api->getAll($env->uuid) as $cert) {
          if (!$cert->flags->active) {
            continue;
          }
          $sans_count = count($cert->domains);
          foreach ($cert->domains as $domain) {
            if ($domain === $ssl_parts['sans']) {
              $ssl_match = $domain;
              $has_ssl_coverage = TRUE;
            }
            elseif ($domain === $ssl_parts['related'] && !$related_match) {
              $related_match = $domain;
            }
          }
        }
      }

      $candidates[$app_name] = [
        'uuid' => $app->uuid,
        'name' => $app_name,
        'dbs' => $db_count,
        'has_ssl' => $ssl_match !== NULL,
        'ssl_match' => $ssl_match,
        'related' => $related_match,
        'sans' => $sans_count,
      ];
    }

    $checks['has_ssl_coverage'] = $has_ssl_coverage
      ? Precondition::pass('has_ssl_coverage')
      : Precondition::warn('has_ssl_coverage', "No SSL coverage found for {$host}. Install a certificate before updating DNS.");

    // --- Git checks (only when committing) ---

    if (!($options['no-commit'] ?? FALSE)) {
      $branch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
      $protected = ['main', 'master', 'develop'];
      $checks['on_feature_branch'] = in_array($branch, $protected)
        ? Precondition::fail('on_feature_branch', "Cannot commit on protected branch '{$branch}'.")
        : Precondition::pass('on_feature_branch', ['branch' => $branch]);

      $dirty = trim((string) shell_exec('git status --porcelain 2>/dev/null'));
      $checks['clean_working_tree'] = $dirty
        ? Precondition::fail('clean_working_tree', 'Working tree has uncommitted changes.')
        : Precondition::pass('clean_working_tree');

      shell_exec('git fetch origin --quiet 2>/dev/null');
      $rev = trim((string) shell_exec("git rev-list --left-right --count origin/{$branch}...HEAD 2>/dev/null"));
      $parts = explode("\t", $rev);
      $behind = (int) ($parts[0] ?? 0);
      $checks['up_to_date_with_origin'] = $behind > 0
        ? Precondition::fail('up_to_date_with_origin', "Branch is {$behind} commit(s) behind origin/{$branch}.")
        : Precondition::pass('up_to_date_with_origin');
    }

    // --- App selection ---

    $selected_app = NULL;
    $reasoning = '';

    if (!empty($options['app'])) {
      if (!isset($candidates[$options['app']])) {
        $checks['app_exists'] = Precondition::fail(
          'app_exists',
          "Specified application '{$options['app']}' not found in Acquia Cloud."
        );
      }
      else {
        $selected_app = $candidates[$options['app']];
        $reasoning = 'Explicitly specified via --app.';
      }
    }
    else {
      $eligible = array_filter(
        $candidates,
        fn($c) => $c['has_ssl'] && $c['name'] !== self::HEALTHCARE_APP
      );

      if (empty($eligible)) {
        $eligible = array_filter($candidates, fn($c) => $c['name'] !== self::HEALTHCARE_APP);
      }

      if (!empty($eligible)) {
        $sorted = array_values($eligible);
        usort($sorted, fn($a, $b) => $a['dbs'] <=> $b['dbs']);
        $min = $sorted[0]['dbs'];
        $tied = array_filter($sorted, fn($c) => $c['dbs'] === $min);

        if (count($tied) === 1) {
          $selected_app = reset($tied);
          $reasoning = "Lowest DB count ({$min}) among eligible apps.";
        }
        // else: $selected_app stays NULL — caller handles interactive pick.
      }
    }

    if ($selected_app) {
      $selected_app['reasoning'] = $reasoning;
    }

    return [
      'input' => [
        'host' => $host,
        'db' => Multisite::getDatabaseName($host),
        'id' => Multisite::getIdentifier("https://{$host}"),
        'flags' => $flags,
      ],
      'decisions' => [
        'app' => $selected_app,
        'app_candidates' => $candidates,
      ],
      'validation' => $this->buildValidation($checks),
    ];
  }

  /**
   * Build the ordered list of steps to execute.
   *
   * Each step carries a display label and the Robo task that performs the
   * action. The same list drives both the plan display and the collection.
   *
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   * @param array $decisions
   *   Output of decide(), with a resolved (non-null) app.
   *
   * @return array
   *   Ordered array of ['label' => string, 'task' => \Robo\Contract\TaskInterface].
   */
  private function buildSteps(string $host, array $options, array $decisions): array {
    $root = getcwd();
    $app = $decisions['decisions']['app'];
    $id = $decisions['input']['id'];
    $db = $decisions['input']['db'];

    $domains = Multisite::getInternalDomains($id);
    $local = $domains['local'];
    $dev = $domains['dev'];
    $test = $domains['test'];
    $prod_domain = $domains['prod'];

    $drush_alias = Yaml::parseFile("{$root}/drush/sites/{$app['name']}.site.yml");
    $files_path = "sites/{$host}/files";
    $drush_alias['local']['uri'] = $local;
    $drush_alias['local']['paths']['files'] = $files_path;
    $drush_alias['dev']['uri'] = $dev;
    $drush_alias['dev']['paths']['files'] = $files_path;
    $drush_alias['test']['uri'] = $test;
    $drush_alias['test']['paths']['files'] = $files_path;
    $drush_alias['prod']['uri'] = $host;
    $drush_alias['prod']['paths']['files'] = $files_path;

    $blt = [
      'project' => [
        'machine_name' => $id,
        'human_name' => $host,
        'local' => ['hostname' => $local, 'protocol' => 'https'],
      ],
      'drush' => ['aliases' => ['local' => 'self', 'remote' => "{$id}.prod"]],
      'drupal' => ['db' => ['database' => $db]],
      'uiowa' => ['stage_file_proxy' => ['origin' => "https://{$prod_domain}"]],
    ];

    if (!empty($options['requester'])) {
      $blt['uiowa']['requester'] = $options['requester'];
    }
    if (!empty($options['split'])) {
      $splits = array_map('trim', explode(',', $options['split']));
      $blt['uiowa']['config']['split'] = count($splits) === 1 ? $splits[0] : $splits;
    }
    if (!empty($options['site-name'])) {
      $blt['uiowa']['site-name'] = $options['site-name'];
    }

    $acquia_block = <<<EOD
\$ah_group = getenv('AH_SITE_GROUP');

if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/{\$ah_group}/{$db}-settings.inc";
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";
EOD;

    $steps = [];

    if (!($options['no-db'] ?? FALSE)) {
      $client = $this->getAcquiaCloudApiClient(
        $this->getConfigValue('uiowa.credentials.acquia.key'),
        $this->getConfigValue('uiowa.credentials.acquia.secret')
      );
      $steps[] = [
        'label' => "Create cloud DB <info>{$db}</info> on <info>{$app['name']}</info>",
        'task' => $this->taskCloudDbCreate($client, $app['uuid'], $app['name'], $db),
      ];
    }

    $steps[] = [
      'label' => "Copy <info>docroot/sites/default</info> → <info>docroot/sites/{$host}</info>",
      'task' => $this->taskCopyDir(["{$root}/docroot/sites/default" => "{$root}/docroot/sites/{$host}"])
        ->exclude(['local.settings.php', 'files', 'default.services.yml', 'services.yml']),
    ];

    $steps[] = [
      'label' => "Patch <info>settings.php</info> with Acquia DB include for <info>{$db}</info>",
      'task' => $this->taskReplaceInFile("{$root}/docroot/sites/{$host}/settings.php")
        ->from('require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";' . "\n")
        ->to($acquia_block . "\n"),
    ];

    $steps[] = [
      'label' => "Write <info>drush/sites/{$id}.site.yml</info>",
      'task' => $this->taskWriteToFile("{$root}/drush/sites/{$id}.site.yml")
        ->text(Yaml::dump($drush_alias, 10, 2)),
    ];

    $steps[] = [
      'label' => "Write <info>docroot/sites/{$host}/blt.yml</info>",
      'task' => $this->taskWriteToFile("{$root}/docroot/sites/{$host}/blt.yml")
        ->text(Yaml::dump($blt, 10, 2)),
    ];

    $steps[] = [
      'label' => "Append <info>sites.php</info> directory aliases for <info>{$host}</info>",
      'task' => $this->taskSitesPhpUpdate("{$root}/docroot/sites/sites.php")
        ->add($host, $local, $dev, $test, $prod_domain),
    ];

    $steps[] = [
      'label' => "Update <info>blt/manifest.yml</info> (app: <info>{$app['name']}</info>)",
      'task' => $this->taskManifestUpdate("{$root}/blt/manifest.yml")
        ->add($app['name'], $host),
    ];

    $steps[] = [
      'label' => 'Run <info>blt:init:settings</info> to generate local settings files',
      'task' => $this->taskExec('./vendor/bin/blt blt:init:settings')
        ->option('site', $host, '='),
    ];

    if (!($options['no-commit'] ?? FALSE)) {
      $steps[] = [
        'label' => "Commit \"Initialize {$host} multisite on {$app['name']}\"",
        'task' => $this->taskGitStack()
          ->dir($root)
          ->add('docroot/sites/sites.php')
          ->add('blt/manifest.yml')
          ->add("docroot/sites/{$host}")
          ->add("drush/sites/{$id}.site.yml")
          ->commit("Initialize {$host} multisite on {$app['name']}")
          ->interactive(FALSE)
          ->printOutput(FALSE)
          ->printMetadata(FALSE),
      ];
    }

    return $steps;
  }

  /**
   * Run all steps in a Robo collection with rollback on failure.
   */
  private function apply(array $steps): void {
    $collection = $this->collectionBuilder();
    foreach ($steps as $step) {
      $collection->addTask($step['task']);
    }

    $result = $collection->run();

    if (!$result->wasSuccessful()) {
      $this->io()->error('Multisite creation failed. Rolled back where possible.');
      return;
    }

    $this->say('<info>Done.</info>');
    $this->printNextSteps($steps);
  }

  /**
   * Assembles the application and database rows for the plan header.
   *
   * @return array
   *   Array of ['label' => string, 'value' => string] rows.
   */
  private function planSummary(array $decisions): array {
    $app = $decisions['decisions']['app'] ?? NULL;
    if (!$app) {
      return [];
    }
    return [
      ['label' => 'Application', 'value' => $app['name']],
      ['label' => 'Database', 'value' => $decisions['input']['db'] ?? '—'],
      ['label' => 'Reason', 'value' => $app['reasoning'] ?? ''],
    ];
  }

  /**
   * Print post-creation next steps.
   */
  private function printNextSteps(array $steps): void {
    // Determine if a commit was included by checking step labels.
    $committed = (bool) array_filter($steps, fn($s) => str_starts_with($s['label'], 'Commit'));

    if ($committed) {
      $branch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
      $items = [
        "Push and merge via a pull request: <comment>git push --set-upstream origin {$branch}</comment>",
        'Coordinate a new release and deploy to test and prod environments.',
        'Once deployed, run <comment>uiowa:multisite:install</comment> on the appropriate application(s).',
        'Add multisite domains to Acquia environments as needed.',
      ];
    }
    else {
      $items = [
        'Commit the generated files when ready.',
        'Deploy a release to production as per usual.',
        'Once deployed, run <comment>uiowa:multisite:install</comment> on the appropriate application(s).',
        'Add multisite domains to Acquia environments as needed.',
      ];
    }

    $this->say('Next steps:');
    $this->io()->listing($items);
  }

}
