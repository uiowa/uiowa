<?php

namespace SiteNow\Robo\Plugin\Commands;

use AcquiaCloudApi\Connector\Client;
use Robo\Tasks;
use SiteNow\Config\Applications;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Task\Acquia\Tasks as AcquiaTasks;
use SiteNow\Task\Multisite\Tasks as MultisiteTasks;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Yaml\Yaml;
use Uiowa\Multisite;

/**
 * Creates a new SiteNow multisite.
 */
class MultisiteCreateCommand extends Tasks {

  use SiteNowCommandsTrait;
  use AcquiaTasks;
  use MultisiteTasks;
  use PlanTrait;
  use CommonChecks;

  // Machine names recorded in validation results.
  const CHECK_HOSTNAME_FORMAT = 'hostname_format';
  const CHECK_SITE_DIR_DOES_NOT_EXIST = 'site_dir_does_not_exist';
  const CHECK_NO_NORMALIZED_CONFLICTS = 'no_normalized_conflicts';
  const CHECK_SSL_COVERAGE = 'has_ssl_coverage';
  const CHECK_APP_SELECTION = 'app_selection';
  const CHECK_APP_EXISTS = 'app_exists';

  /**
   * Create a new SiteNow multisite.
   *
   * @param string $host
   *   The multisite URI host (e.g. newsite.uiowa.edu).
   * @param array $options
   *   Keyed command options, as declared by the @option tags.
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
   * @command sitenow:multisite:create
   * @aliases smc
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
    $this->executePlan($plan, $options);
  }

  /**
   * Produce the complete Plan: the decision, and on pass the steps to run.
   *
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan: the decision always, plus the steps and next-steps when
   *   validation passes (a failed plan carries neither).
   */
  private function decide(string $host, array $options): Plan {
    $root = getcwd();
    $title = "sitenow:multisite:create {$host}";

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

    // Checks that need no Acquia API: environment, input, and local
    // filesystem. Run these first so a FAIL returns before any API call.
    $local_checks = [
      $this->checkHostShell(),
      $this->checkAcquiaCredentials(),
      new Check(self::CHECK_HOSTNAME_FORMAT, function () use ($host): CheckResult {
        return Multisite::isValidHost($host)
          ? CheckResult::pass()
          : CheckResult::fail("Invalid hostname: {$host}. Must be a valid dot-separated domain.");
      }),
      new Check(self::CHECK_SITE_DIR_DOES_NOT_EXIST, function () use ($root, $host): CheckResult {
        return is_dir("{$root}/docroot/sites/{$host}")
          ? CheckResult::fail("Site directory docroot/sites/{$host} already exists.")
          : CheckResult::pass();
      }),
      new Check(self::CHECK_NO_NORMALIZED_CONFLICTS, function () use ($root, $host): CheckResult {
        return $this->hasIdentifierConflict($host, Multisite::getAllSites($root))
          ? CheckResult::fail("Site {$host} normalizes to an identifier already used by an existing site.")
          : CheckResult::pass();
      }),
    ];

    $validation = $this->runChecks($local_checks);

    // Gate the API work: if a local check already failed, return the plan
    // without querying Acquia.
    if ($validation['overall'] === CheckStatus::Fail) {
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
      new Check(self::CHECK_SSL_COVERAGE, function () use ($has_ssl_coverage, $host): CheckResult {
        return $has_ssl_coverage
          ? CheckResult::pass()
          : CheckResult::warn("No SSL coverage found for {$host}. Install a certificate before updating DNS.");
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
        new Check(self::CHECK_APP_SELECTION, fn() => CheckResult::fail('No eligible Acquia application found in the registry.')),
      ]));
    }
    if ($app) {
      $app['reasoning'] = $reasoning;
    }

    $context = ['app' => $app, 'app_candidates' => $candidates];
    $summary = $this->summary($app, $input);

    // A failed plan carries the decision only; skip building the steps that
    // would never run.
    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation, $summary, $context);
    }

    $steps = $this->buildSteps($host, $options, $app, $input);
    $next_steps = $this->nextSteps($options);

    return new Plan($title, $input, $validation, $summary, $context, $steps, $next_steps);
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
      // Create the bar on the first callback, when the total becomes known.
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
        $check = new Check(self::CHECK_APP_EXISTS, fn() => CheckResult::fail(
          "Specified application '{$options['app']}' is not in the SiteNow application registry."
        ));
        return [NULL, '', $check];
      }
      return [$candidates[$options['app']], 'Explicitly specified via --app.', NULL];
    }

    $eligible = $this->eligibleApps($candidates);
    if (empty($eligible)) {
      return [NULL, '', NULL];
    }

    // Fewest sites wins; application name is the stable tie-break.
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
  private function summary(?array $app, array $input): array {
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
   * Build the ordered steps that create the multisite.
   *
   * Each step carries a display label and the Robo task that performs the
   * action. The same list drives both the plan display and the collection.
   *
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   * @param array $app
   *   The selected application (name + uuid).
   * @param array $input
   *   Normalized command input (id, db).
   *
   * @return array
   *   Ordered array of ['label' => string, 'task' => \Robo\Contract\TaskInterface].
   */
  private function buildSteps(string $host, array $options, array $app, array $input): array {
    $root = getcwd();
    $id = $input['id'];
    $db = $input['db'];

    $domains = Multisite::getInternalDomains($id);
    $local = $domains['local'];
    $dev = $domains['dev'];
    $test = $domains['test'];
    $prod_domain = $domains['prod'];

    // Start from the target app's drush alias and retarget every environment
    // at the new site's domains and files path.
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

    // settings.php include: on Acquia, load the per-environment DB credentials,
    // then BLT's settings. Replaces the bare BLT require the copied file ships
    // with (see the patch step below).
    $acquia_block = <<<EOD
\$ah_group = getenv('AH_SITE_GROUP');

if (file_exists('/var/www/site-php')) {
  require "/var/www/site-php/{\$ah_group}/{$db}-settings.inc";
}

require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";
EOD;

    // Steps run in order; the commit comes last so it captures every
    // generated file.
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
      // One split is stored as a scalar, multiple as a list, matching the
      // shape the BLT install hook reads.
      $splits = array_map('trim', explode(',', $options['split']));
      $blt['uiowa']['config']['split'] = count($splits) === 1 ? $splits[0] : $splits;
    }
    if (!empty($options['site-name'])) {
      $blt['uiowa']['site-name'] = $options['site-name'];
    }

    return $blt;
  }

  /**
   * Build the post-apply guidance lines for a create.
   *
   * @param array $options
   *   Command options.
   *
   * @return string[]
   *   Guidance lines shown after a successful run.
   */
  private function nextSteps(array $options): array {
    // Whether the run will land its own commit decides the first instruction:
    // push the commit, or commit the generated files by hand.
    if (empty($options['no-commit'])) {
      $branch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null'));
      $first = "Push and merge via a pull request: <comment>git push --set-upstream origin {$branch}</comment>";
    }
    else {
      $first = 'Commit the generated files when ready.';
    }

    return [
      $first,
      'Coordinate a new release and deploy to test and prod environments.',
      'Once deployed, run <comment>uiowa:multisite:install</comment> on the appropriate application(s).',
      'Add multisite domains to Acquia environments as needed.',
    ];
  }

}
