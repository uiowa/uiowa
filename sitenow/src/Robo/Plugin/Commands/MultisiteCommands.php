<?php

namespace SiteNow\Robo\Plugin\Commands;

use AcquiaCloudApi\Connector\Client;
use Robo\Tasks;
use SiteNow\Config\Applications;
use SiteNow\Plan\Check;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Plan\Precondition;
use SiteNow\Task\Acquia\Tasks as AcquiaTasks;
use SiteNow\Task\Multisite\Tasks as MultisiteTasks;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
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
  use CommonChecks;

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
      'app' => InputOption::VALUE_REQUIRED,
    ],
  ): void {
    $plan = $this->decide($host, $options);
    $this->executePlan($plan, $options, fn() => $this->buildSteps($host, $options, $plan));
  }

  /**
   * Build the Plan for a multisite create: gather facts and evaluate checks.
   *
   * Read-only. Cheap env and input checks run first and gate the Acquia API
   * work behind them.
   *
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   *
   * @return \SiteNow\Plan\Plan
   *   The decided plan, ready to render and execute.
   */
  private function decide(string $host, array $options): Plan {
    $root = getcwd();
    $title = "uiowa:multisite:create {$host}";

    $umc_keys = ['no-commit', 'no-db', 'requester', 'split', 'site-name', 'dry-run', 'yes', 'app'];
    $flags = array_filter(
      array_intersect_key($options, array_flip($umc_keys)),
      fn($v) => $v !== NULL && $v !== FALSE && $v !== '' && $v !== InputOption::VALUE_REQUIRED
    );

    $input = [
      'host' => $host,
      'db' => Multisite::getDatabaseName($host),
      'id' => Multisite::getIdentifier("https://{$host}"),
      'flags' => $flags,
    ];

    // Cheap checks: environment and input. A FAIL here short-circuits before
    // any Acquia API call.
    $cheap = [
      $this->checkHostShell(),
      $this->checkAcquiaCredentials(),
      new Check('hostname_format', function () use ($host): Precondition {
        return Multisite::isValidHost($host)
          ? Precondition::pass('hostname_format')
          : Precondition::fail('hostname_format', "Invalid hostname: {$host}. Must be a valid dot-separated domain.");
      }),
      new Check('site_dir_does_not_exist', function () use ($root, $host): Precondition {
        return is_dir("{$root}/docroot/sites/{$host}")
          ? Precondition::fail('site_dir_does_not_exist', "Site directory docroot/sites/{$host} already exists.")
          : Precondition::pass('site_dir_does_not_exist');
      }),
      new Check('no_normalized_conflicts', function () use ($root, $host): Precondition {
        return $this->hasIdentifierConflict($host, Multisite::getAllSites($root))
          ? Precondition::fail('no_normalized_conflicts', "Site {$host} normalizes to an identifier already used by an existing site.")
          : Precondition::pass('no_normalized_conflicts');
      }),
    ];

    $validation = $this->runChecks($cheap);

    if ($validation['overall'] === Precondition::FAIL) {
      return new Plan($title, $input, $validation);
    }

    // Candidates come from the SiteNow registry (identity + reserved flag) and
    // the manifest (relative load by site count).
    $registry = new Applications("{$root}/sitenow/applications.yml");
    $site_counts = $this->siteCountsByApp($root);
    $candidates = [];
    foreach ($registry->all() as $name => $entry) {
      $candidates[$name] = [
        'name' => $name,
        'uuid' => $entry['uuid'],
        'reserved' => !empty($entry['reserved']),
        'sites' => $site_counts[$name] ?? 0,
      ];
    }

    // SSL coverage is the only live API query.
    $creds = $this->getAcquiaCredentials();
    $client = $this->getAcquiaCloudApiClient($creds['key'], $creds['secret']);
    $ssl_parts = Multisite::getSslParts($host);
    $coverage = $this->gatherSslCoverage($client, array_column($candidates, 'uuid', 'name'), $ssl_parts);
    foreach ($coverage as $name => $ssl) {
      $candidates[$name] += $ssl;
    }
    $has_ssl_coverage = (bool) array_filter($candidates, fn($c) => $c['has_ssl']);

    // SSL and git checks.
    $checks = [
      new Check('has_ssl_coverage', function () use ($has_ssl_coverage, $host): Precondition {
        return $has_ssl_coverage
          ? Precondition::pass('has_ssl_coverage')
          : Precondition::warn('has_ssl_coverage', "No SSL coverage found for {$host}. Install a certificate before updating DNS.");
      }),
    ];

    if (empty($options['no-commit'])) {
      $branch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
      $checks = array_merge($checks, $this->gitChecks($branch));
    }

    $validation = $this->mergeValidation($validation, $this->runChecks($checks));

    // App selection.
    [$app, $reasoning, $app_check] = $this->selectApp($candidates, $options);
    if ($app_check) {
      $validation = $this->mergeValidation($validation, $this->runChecks([$app_check]));
    }
    elseif (!$app) {
      $validation = $this->mergeValidation($validation, $this->runChecks([
        new Check('app_selection', fn() => Precondition::fail('app_selection', 'No eligible Acquia application found in the registry.')),
      ]));
    }
    if ($app) {
      $app['reasoning'] = $reasoning;
    }

    return new Plan(
      $title,
      $input,
      $validation,
      $this->planSummary($app, $input),
      ['app' => $app, 'app_candidates' => $candidates],
    );
  }

  /**
   * Pick the target application from the candidates.
   *
   * Honors an explicit --app (validated against the registry), otherwise
   * auto-picks the eligible application with the fewest sites, breaking ties
   * by application name (natural sort).
   *
   * @param array $candidates
   *   Application candidates keyed by name.
   * @param array $options
   *   Command options.
   *
   * @return array
   *   [?array $app, string $reasoning, ?\SiteNow\Plan\Check $check].
   */
  protected function selectApp(array $candidates, array $options): array {
    if (!empty($options['app'])) {
      if (!isset($candidates[$options['app']])) {
        return [NULL, '', new Check('app_exists', fn() => Precondition::fail(
          'app_exists',
          "Specified application '{$options['app']}' is not in the SiteNow application registry."
        ))];
      }
      return [$candidates[$options['app']], 'Explicitly specified via --app.', NULL];
    }

    $eligible = $this->eligibleApps($candidates);
    if (empty($eligible)) {
      return [NULL, '', NULL];
    }

    $sorted = array_values($eligible);
    usort($sorted, fn($a, $b) => $a['sites'] <=> $b['sites'] ?: strnatcmp($a['name'], $b['name']));
    $winner = $sorted[0];

    return [$winner, "Fewest sites ({$winner['sites']}) among eligible apps.", NULL];
  }

  /**
   * Filter candidates to those eligible for auto-selection.
   *
   * Prefers SSL-covered applications and excludes reserved ones. Falls back to
   * all non-reserved applications when none have coverage.
   *
   * @param array $candidates
   *   Application candidates keyed by name.
   *
   * @return array
   *   The eligible subset, keyed by name.
   */
  protected function eligibleApps(array $candidates): array {
    $eligible = array_filter(
      $candidates,
      fn($c) => $c['has_ssl'] && empty($c['reserved'])
    );

    if (empty($eligible)) {
      $eligible = array_filter($candidates, fn($c) => empty($c['reserved']));
    }

    return $eligible;
  }

  /**
   * Count multisites per application from the manifest.
   *
   * The manifest (app => [hosts]) is the repo-local proxy for relative load
   * used to rank applications.
   *
   * @param string $root
   *   The repository root.
   *
   * @return array
   *   Site counts keyed by application name.
   */
  private function siteCountsByApp(string $root): array {
    $path = "{$root}/blt/manifest.yml";
    $manifest = file_exists($path) ? (Yaml::parseFile($path) ?? []) : [];
    return array_map(fn($sites) => is_array($sites) ? count($sites) : 0, $manifest);
  }

  /**
   * Determine whether a host collides with an existing site's identifier.
   *
   * The drush alias filename and the sites.php internal-domain entries derive
   * from Multisite::getIdentifier(), so two different hosts that normalize to
   * the same identifier collide globally even though their directories differ.
   *
   * @param string $host
   *   The candidate host.
   * @param array $existing_sites
   *   Existing site hosts, e.g. from Multisite::getAllSites().
   *
   * @return bool
   *   TRUE if the candidate's identifier matches an existing site's.
   */
  protected function hasIdentifierConflict(string $host, array $existing_sites): bool {
    $id = Multisite::getIdentifier("https://{$host}");
    foreach ($existing_sites as $site) {
      if (Multisite::getIdentifier("https://{$site}") === $id) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gather SSL coverage for the candidate applications, with progress feedback.
   *
   * Progress writes to stderr so it never corrupts stdout plan output.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   * @param array $apps
   *   Application UUIDs keyed by application name.
   * @param array $ssl_parts
   *   Output of Multisite::getSslParts().
   *
   * @return array
   *   SSL coverage keyed by application name.
   */
  private function gatherSslCoverage(Client $client, array $apps, array $ssl_parts): array {
    $out = $this->acquiaProgress();
    $out->writeln('<comment>Checking SSL coverage across Acquia Cloud applications...</comment>');

    $bar = NULL;
    $coverage = $this->getSslCoverage($client, $apps, $ssl_parts, function (string $name, int $total) use (&$bar, $out) {
      if ($bar === NULL) {
        $bar = new ProgressBar($out, $total);
        $bar->setFormat(' %current%/%max% [%bar%] %message%');
        $bar->setMessage($name);
        $bar->start();
      }
      $bar->setMessage($name);
      $bar->advance();
    });

    if ($bar !== NULL) {
      $bar->setMessage('done');
      $bar->finish();
    }
    $out->writeln('');

    return $coverage;
  }

  /**
   * Resolve the output stream for query progress.
   *
   * Progress feedback writes to stderr so it stays out of stdout when the
   * rendered plan is piped or redirected.
   *
   * @return \Symfony\Component\Console\Output\OutputInterface
   *   The error output when available, otherwise the standard output.
   */
  private function acquiaProgress() {
    $output = $this->output();
    return $output instanceof ConsoleOutputInterface
      ? $output->getErrorOutput()
      : $output;
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
   * @param \SiteNow\Plan\Plan $plan
   *   The decided plan, with a resolved app.
   *
   * @return array
   *   Ordered array of ['label' => string, 'task' => \Robo\Contract\TaskInterface].
   */
  private function buildSteps(string $host, array $options, Plan $plan): array {
    $root = getcwd();
    $app = $plan->context['app'];
    $id = $plan->input['id'];
    $db = $plan->input['db'];

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

    $blt = $this->buildSiteConfig($host, $id, $db, $local, $prod_domain, $options);

    $acquia_block = <<<EOD
\$ah_group = getenv('AH_SITE_GROUP');

if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/{\$ah_group}/{$db}-settings.inc";
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";
EOD;

    $steps = [];

    if (empty($options['no-db'])) {
      $creds = $this->getAcquiaCredentials();
      $steps[] = [
        'label' => "Create cloud DB <info>{$db}</info> on <info>{$app['name']}</info>",
        'task' => $this->taskCloudDbCreate(
          $this->getAcquiaCloudApiClient($creds['key'], $creds['secret']),
          $app['uuid'],
          $app['name'],
          $db
        ),
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

    if (empty($options['no-commit'])) {
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
   * Assemble the per-site blt.yml configuration array.
   *
   * A single split is stored as a scalar and multiple splits as an array,
   * matching the shape the BLT install hook reads.
   *
   * @param string $host
   *   The multisite host.
   * @param string $id
   *   The site identifier.
   * @param string $db
   *   The database name.
   * @param string $local
   *   The local internal domain.
   * @param string $prod_domain
   *   The prod internal domain, used for the stage_file_proxy origin.
   * @param array $options
   *   Command options. Reads 'requester', 'split', and 'site-name'.
   *
   * @return array
   *   The per-site blt.yml structure.
   */
  protected function buildSiteConfig(string $host, string $id, string $db, string $local, string $prod_domain, array $options): array {
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

    return $blt;
  }

  /**
   * Assembles the application and database rows for the plan header.
   *
   * @param array|null $app
   *   The selected application facts, or NULL when unresolved.
   * @param array $input
   *   Normalized command input.
   *
   * @return array
   *   Array of ['label' => string, 'value' => string] rows.
   */
  private function planSummary(?array $app, array $input): array {
    if (!$app) {
      return [];
    }
    return [
      ['label' => 'Application', 'value' => $app['name']],
      ['label' => 'Database', 'value' => $input['db'] ?? 'n/a'],
      ['label' => 'Reason', 'value' => $app['reasoning'] ?? ''],
    ];
  }

  /**
   * Print domain-specific follow-up guidance after a successful apply.
   *
   * @param array $steps
   *   The steps that were executed.
   */
  protected function afterApply(array $steps): void {
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
