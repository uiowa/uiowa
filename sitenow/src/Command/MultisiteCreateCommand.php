<?php

namespace SiteNow\Command;

use AcquiaCloudApi\Connector\Client;
use SiteNow\Config\Applications;
use SiteNow\Operation\CloudDbCreate;
use SiteNow\Operation\ManifestUpdate;
use SiteNow\Operation\SitesPhpUpdate;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Traits\SiteNowCommandsTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use SiteNow\Utility\Multisite;

/**
 * Creates a new SiteNow multisite.
 */
#[AsCommand(
  name: 'multisite:create',
  description: 'Create a new SiteNow multisite.',
  aliases: ['mc', 'umc'],
)]
class MultisiteCreateCommand extends Command {

  use SiteNowCommandsTrait;
  use PlanTrait;
  use CommonChecks;

  // Machine names recorded in validation results.
  const CHECK_HOSTNAME_FORMAT = 'hostname_format';
  const CHECK_SITE_DIR_DOES_NOT_EXIST = 'site_dir_does_not_exist';
  const CHECK_NO_NORMALIZED_CONFLICTS = 'no_normalized_conflicts';
  const CHECK_SSL_COVERAGE = 'has_ssl_coverage';
  const CHECK_APP_SELECTION = 'app_selection';
  const CHECK_APP_EXISTS = 'app_exists';
  const CHECK_DRUSH_ALIAS_EXISTS = 'drush_alias_exists';

  /**
   * Constructs the command.
   *
   * @param string $repoRoot
   *   Absolute path to the repository root. The command runs on the host shell
   *   and operates on the working tree relative to this root.
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
      ->addArgument('host', InputArgument::REQUIRED, 'The multisite URI host (e.g. newsite.uiowa.edu).')
      ->addOption('no-commit', NULL, InputOption::VALUE_NONE, 'Do not create a git commit.')
      ->addOption('no-db', NULL, InputOption::VALUE_NONE, 'Do not create a cloud database.')
      ->addOption('requester', NULL, InputOption::VALUE_REQUIRED, 'The HawkID of the original requester.')
      ->addOption('split', NULL, InputOption::VALUE_REQUIRED, 'Config split(s) to activate. Comma-separate multiple values.')
      ->addOption('site-name', NULL, InputOption::VALUE_REQUIRED, 'The desired site name.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Show plan and exit; no side effects.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Apply without prompting. Blocked by any WARN.')
      ->addOption('app', NULL, InputOption::VALUE_REQUIRED, 'Override the target Acquia application.');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    $host = $input->getArgument('host');
    $options = [
      'no-commit' => $input->getOption('no-commit'),
      'no-db' => $input->getOption('no-db'),
      'requester' => $input->getOption('requester'),
      'split' => $input->getOption('split'),
      'site-name' => $input->getOption('site-name'),
      'dry-run' => $input->getOption('dry-run'),
      'yes' => $input->getOption('yes'),
      'app' => $input->getOption('app'),
    ];

    $plan = $this->decide($io, $host, $options);
    return $this->executePlan($io, $plan, $options);
  }

  /**
   * Produce the complete Plan: the decision, and on pass the steps to run.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style, used for SSL-coverage query progress.
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan: the decision always, plus the steps and next-steps when
   *   validation passes (a failed plan carries neither).
   */
  private function decide(SymfonyStyle $io, string $host, array $options): Plan {
    $root = $this->repoRoot;
    $title = "multisite:create {$host}";

    $umc_keys = ['no-commit', 'no-db', 'requester', 'split', 'site-name', 'dry-run', 'yes', 'app'];
    $flags = array_filter(
      array_intersect_key($options, array_flip($umc_keys)),
      fn($v) => $v !== NULL && $v !== FALSE && $v !== ''
    );

    $input = [
      'host' => $host,
      'db' => Multisite::getDatabaseName($host),
      'id' => Multisite::getIdentifier("https://{$host}"),
      'flags' => $flags,
    ];

    // The registry is a local file; load it now so --app can be validated
    // before any API call.
    $registry = new Applications("{$root}/sitenow/applications.yml");

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

    // --app names a registry entry; validate against the local file here
    // rather than after the API query.
    if (!empty($options['app'])) {
      $local_checks[] = new Check(self::CHECK_APP_EXISTS, function () use ($registry, $options): CheckResult {
        return $registry->uuid($options['app']) !== NULL
          ? CheckResult::pass()
          : CheckResult::fail("Specified application '{$options['app']}' is not in the SiteNow application registry.");
      });
    }

    $validation = $this->runChecks($local_checks);

    // Gate the API work: if a local check already failed, return the plan
    // without querying Acquia.
    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation);
    }

    // Candidates come from the SiteNow registry (identity + reserved flag) and
    // the manifest (relative load by site count).
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
    $coverage = $this->gatherSslCoverage($io, $client, array_column($candidates, 'uuid', 'name'), $ssl_parts);
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
      $branch_process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
      $branch_process->run();
      $branch = trim($branch_process->getOutput());
      $checks = array_merge($checks, $this->gitChecks($branch, !empty($options['dry-run'])));
    }

    $validation = $this->mergeValidation($validation, $this->runChecks($checks));

    // App selection (--app already validated against the registry above).
    [$app, $reasoning] = $this->selectApp($candidates, $options);
    if (!$app) {
      $validation = $this->mergeValidation($validation, $this->runChecks([
        new Check(self::CHECK_APP_SELECTION, fn() => CheckResult::fail('No eligible Acquia application found in the registry.')),
      ]));
    }
    if ($app) {
      $app['reasoning'] = $reasoning;
    }

    $context = ['app' => $app, 'app_candidates' => $candidates];
    $summary = $this->summary($app, $input);
    $plan = new Plan($title, $input, $validation, $summary, $context);

    // A failed plan carries the decision only; skip building the steps that
    // would never run.
    if ($plan->failed()) {
      return $plan;
    }

    // buildSteps() parses the selected app's drush alias template; a missing
    // file (e.g. a newly registered app whose alias isn't committed yet) would
    // otherwise throw mid-build. Surface it as a clean validation failure.
    if (!file_exists("{$root}/drush/sites/{$app['name']}.site.yml")) {
      $validation = $this->mergeValidation($validation, $this->runChecks([
        new Check(self::CHECK_DRUSH_ALIAS_EXISTS, fn() => CheckResult::fail(
          "Drush alias file drush/sites/{$app['name']}.site.yml not found. Commit this application's alias file before provisioning."
        )),
      ]));
      return new Plan($title, $input, $validation, $summary, $context);
    }

    $this->buildSteps($plan, $host, $options, $app, $input);
    $plan->nextSteps = $this->nextSteps($options);

    return $plan;
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
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style; progress is written to its error stream.
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
  private function gatherSslCoverage(SymfonyStyle $io, Client $client, array $apps, array $ssl_parts): array {
    $err = $io->getErrorStyle();
    $err->writeln('<comment>Checking SSL coverage across Acquia Cloud applications...</comment>');

    $bar = NULL;
    $coverage = $this->getSslCoverage($client, $apps, $ssl_parts, function (string $name, int $total) use (&$bar, $err) {
      // Create the bar on the first callback, when the total becomes known.
      if ($bar === NULL) {
        $bar = new ProgressBar($err, $total);
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
    $err->writeln('');

    return $coverage;
  }

  /**
   * Pick the target application from the candidates.
   *
   * Honors an explicit --app (already validated against the registry in the
   * local checks), otherwise auto-picks the eligible application with the
   * fewest sites, breaking ties by application name (natural sort).
   *
   * @param array $candidates
   *   Application candidates keyed by name.
   * @param array $options
   *   Command options.
   *
   * @return array
   *   [?array $app, string $reasoning]. The app is NULL when no eligible
   *   application exists for auto-selection, or when an explicit --app is not
   *   among the candidates.
   */
  protected function selectApp(array $candidates, array $options): array {
    if (!empty($options['app'])) {
      return [$candidates[$options['app']] ?? NULL, 'Explicitly specified via --app.'];
    }

    $eligible = $this->eligibleApps($candidates);
    if (empty($eligible)) {
      return [NULL, ''];
    }

    // Fewest sites wins; application name is the stable tie-break.
    $sorted = array_values($eligible);
    usort($sorted, fn($a, $b) => $a['sites'] <=> $b['sites'] ?: strnatcmp($a['name'], $b['name']));
    $winner = $sorted[0];

    return [$winner, "Fewest sites ({$winner['sites']}) among eligible apps."];
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
   * Each addStep() call pairs a display label with a closure that performs the
   * action when the plan is applied. The same steps drive the plan preview.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan to add the steps to.
   * @param string $host
   *   The multisite host.
   * @param array $options
   *   Command options.
   * @param array $app
   *   The selected application (name + uuid).
   * @param array $input
   *   Normalized command input (id, db).
   */
  private function buildSteps(Plan $plan, string $host, array $options, array $app, array $input): void {
    $root = $this->repoRoot;
    $id = $input['id'];
    $db = $input['db'];
    $fs = new Filesystem();

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
    if (empty($options['no-db'])) {
      $creds = $this->getAcquiaCredentials();
      $client = $this->getAcquiaCloudApiClient($creds['key'], $creds['secret']);
      $uuid = $app['uuid'];
      $app_name = $app['name'];
      $plan->addStep(
        "Create cloud DB <info>{$db}</info> on <info>{$app_name}</info>",
        function (SymfonyStyle $io) use ($client, $uuid, $app_name, $db) {
          (new CloudDbCreate($client, $uuid, $app_name, $db))->run();
          $io->writeln("  Database <info>{$db}</info> is being created on <info>{$app_name}</info>.");
        }
      );
    }

    $src = "{$root}/docroot/sites/default";
    $dest = "{$root}/docroot/sites/{$host}";
    $plan->addStep(
      "Copy <info>docroot/sites/default</info> → <info>docroot/sites/{$host}</info>",
      function () use ($fs, $src, $dest) {
        // Mirror the default site, omitting local-only and generated files.
        $finder = (new Finder())
          ->files()
          ->ignoreDotFiles(FALSE)
          ->in($src)
          ->exclude(['files'])
          ->notName(['local.settings.php', 'default.services.yml', 'services.yml']);
        $fs->mirror($src, $dest, $finder);
      }
    );

    $settings_path = "{$dest}/settings.php";
    $plan->addStep(
      "Patch <info>settings.php</info> with Acquia DB include for <info>{$db}</info>",
      function () use ($fs, $settings_path, $acquia_block) {
        $from = 'require DRUPAL_ROOT . "/../vendor/acquia/blt/settings/blt.settings.php";' . "\n";
        $to = $acquia_block . "\n";
        $contents = (string) file_get_contents($settings_path);
        if (!str_contains($contents, $from)) {
          throw new \RuntimeException("Expected BLT require line not found in {$settings_path}.");
        }
        $fs->dumpFile($settings_path, str_replace($from, $to, $contents));
      }
    );

    $alias_path = "{$root}/drush/sites/{$id}.site.yml";
    $plan->addStep(
      "Write <info>drush/sites/{$id}.site.yml</info>",
      function () use ($fs, $alias_path, $drush_alias) {
        $fs->dumpFile($alias_path, Yaml::dump($drush_alias, 10, 2));
      }
    );

    $blt_path = "{$dest}/blt.yml";
    $plan->addStep(
      "Write <info>docroot/sites/{$host}/blt.yml</info>",
      function () use ($fs, $blt_path, $blt) {
        $fs->dumpFile($blt_path, Yaml::dump($blt, 10, 2));
      }
    );

    $sites_php = "{$root}/docroot/sites/sites.php";
    $plan->addStep(
      "Append <info>sites.php</info> directory aliases for <info>{$host}</info>",
      function () use ($sites_php, $host, $local, $dev, $test, $prod_domain) {
        (new SitesPhpUpdate($sites_php, $host, $local, $dev, $test, $prod_domain))->run();
      }
    );

    $manifest_path = "{$root}/blt/manifest.yml";
    $app_name = $app['name'];
    $plan->addStep(
      "Update <info>blt/manifest.yml</info> (app: <info>{$app_name}</info>)",
      function () use ($manifest_path, $app_name, $host) {
        (new ManifestUpdate($manifest_path, $app_name, $host))->run();
      }
    );

    $plan->addStep(
      'Run <info>blt:init:settings</info> to generate local settings files',
      function (SymfonyStyle $io) use ($root, $host) {
        $process = new Process(['./vendor/bin/blt', 'blt:init:settings', "--site={$host}"], $root);
        $process->setTimeout(NULL);
        $process->run(function ($type, $buffer) use ($io) {
          $io->write($buffer);
        });
        if (!$process->isSuccessful()) {
          throw new \RuntimeException('blt:init:settings failed.');
        }
      }
    );

    if (empty($options['no-commit'])) {
      $message = "Initialize {$host} multisite on {$app_name}";
      $commit_paths = [
        'docroot/sites/sites.php',
        'blt/manifest.yml',
        "docroot/sites/{$host}",
        "drush/sites/{$id}.site.yml",
      ];
      $plan->addStep(
        "Commit \"{$message}\"",
        function () use ($root, $commit_paths, $message) {
          $add = new Process(array_merge(['git', 'add', '--'], $commit_paths), $root);
          $add->run();
          if (!$add->isSuccessful()) {
            throw new \RuntimeException('git add failed: ' . $add->getErrorOutput());
          }
          $commit = new Process(['git', 'commit', '-m', $message], $root);
          $commit->run();
          if (!$commit->isSuccessful()) {
            throw new \RuntimeException('git commit failed: ' . $commit->getErrorOutput());
          }
        }
      );
    }
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
      $branch_process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
      $branch_process->run();
      $branch = trim($branch_process->getOutput());
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
