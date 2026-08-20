<?php

namespace SiteNow\Command;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Endpoints\Environments;
use SiteNow\Config\Applications;
use SiteNow\Acquia\CloudApi;
use SiteNow\Acquia\Mounts;
use SiteNow\Config\Manifest;
use SiteNow\Config\SitesPhp;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CheckStatus;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Traits\SiteNowCommandsTrait;
use SiteNow\Utility\Multisite;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Deletes a SiteNow multisite.
 *
 * Removes its files, database and domains on Acquia, and its directory, drush
 * alias, sites.php aliases and manifest entry here.
 */
#[AsCommand(
  name: 'multisite:delete',
  description: 'Delete a SiteNow multisite.',
  aliases: ['md', 'umd'],
)]
class MultisiteDeleteCommand extends Command {

  use SiteNowCommandsTrait;
  use PlanTrait;
  use CommonChecks;

  // Machine names recorded in validation results.
  const CHECK_SITE_IN_MANIFEST = 'site_in_manifest';
  const CHECK_NOT_DEFAULT_SITE = 'not_default_site';
  const CHECK_SITE_DIR_EXISTS = 'site_dir_exists';
  const CHECK_APP_REGISTERED = 'app_registered';
  const CHECK_DATABASE_NAME_MATCHES = 'database_name_matches';
  const CHECK_DRUSH_ALIAS_MOUNTS = 'drush_alias_mounts';
  const CHECK_CLOUD_READABLE = 'cloud_readable';
  const CHECK_CLOUD_FILES = 'cloud_files_present';
  const CHECK_CLOUD_DATABASE = 'cloud_database_present';
  const CHECK_CLOUD_DOMAINS = 'cloud_domains_present';

  /**
   * Environments a multisite exists on, as the drush alias names them.
   */
  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * The shared site directory, which no multisite delete may target.
   */
  const DEFAULT_SITE_DIRECTORY = 'default';

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
      ->addArgument('host', InputArgument::OPTIONAL, 'The multisite host to delete. Omit to choose from a list.')
      ->addOption('no-commit', NULL, InputOption::VALUE_NONE, 'Do not create a git commit.')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Show plan and exit; no side effects.')
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Apply without prompting. Blocked by any WARN.')
      ->setHelp(<<<'HELP'
Deletes a multisite everywhere it exists: its files, database and domains on
Acquia, then its directories, drush alias, sites.php aliases and manifest entry
in the repository.

The cloud comes down first, and every cloud delete is confirmed gone before the
repository is touched. A run that fails partway is safe to run again.

A resource that is already absent is a WARN, not a failure — the state a
half-finished delete leaves behind. The run continues, but not under --yes;
rerun it interactively and read the warnings first.

Files are deleted per environment, the site's whole directory rather than the
contents of files/.

Domains considered are the three internal *.drupal.uiowa.edu names, the site
host, and www.<host>. Only the ones an environment actually reports are touched.

Runs on the host shell: it commits to the working tree, and .git is not mounted
into the container. The files deletion goes out over the site's drush alias, so
an Acquia SSH key has to be available there too.

Examples:
  # What would come down? Reads cloud and local state, changes nothing.
  ./sn multisite:delete foo.sites.uiowa.edu --dry-run

  # Choose the site from the manifest instead of naming it.
  ./sn multisite:delete

  # Delete everything, but leave the repository removals uncommitted.
  ./sn multisite:delete foo.sites.uiowa.edu --no-commit
HELP);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);

    if (!$this->requireManifest($io)) {
      return Command::FAILURE;
    }

    $sites = $this->sitesByHost($this->manifest());
    $host = $input->getArgument('host');

    if (!$host) {
      $host = $this->selectHost($io, $sites);

      if ($host === NULL) {
        $io->error('No site selected. Pass a host argument when running non-interactively.');
        return Command::FAILURE;
      }
    }

    $options = [
      'no-commit' => $input->getOption('no-commit'),
      'dry-run' => $input->getOption('dry-run'),
      'yes' => $input->getOption('yes'),
    ];

    $plan = $this->decide($host, $sites, $options);

    return $this->executePlan($io, $plan, $options);
  }

  /**
   * Flatten the manifest into a host => application map.
   *
   * The manifest is the source of truth for which sites exist and which
   * application owns each one, so the same read answers both questions.
   *
   * @param array $manifest
   *   The manifest, keyed by application.
   *
   * @return array
   *   Application name keyed by site host, sorted by host.
   */
  protected function sitesByHost(array $manifest): array {
    $sites = [];

    foreach ($manifest as $app => $hosts) {
      foreach ((array) $hosts as $host) {
        $sites[$host] = $app;
      }
    }

    ksort($sites);

    return $sites;
  }

  /**
   * Prompt for the site to delete, with autocompletion.
   *
   * A plain choice list is unusable at fleet size, so this takes free text and
   * autocompletes against the manifest, refusing anything not in it.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style.
   * @param array $sites
   *   Application name keyed by site host.
   *
   * @return string|null
   *   The chosen host, or NULL when the session is not interactive.
   */
  protected function selectHost(SymfonyStyle $io, array $sites): ?string {
    if (empty($sites)) {
      return NULL;
    }

    $hosts = array_keys($sites);
    $question = new Question('Site to delete (start typing, then Tab)');
    $question->setAutocompleterValues($hosts);
    $question->setValidator(function ($answer) use ($hosts) {
      $answer = trim((string) $answer);

      if (!in_array($answer, $hosts, TRUE)) {
        throw new \RuntimeException("'{$answer}' is not a site in blt/manifest.yml.");
      }

      return $answer;
    });
    $question->setMaxAttempts(3);

    return $io->askQuestion($question);
  }

  /**
   * Produce the complete Plan: the decision, and on pass the steps to run.
   *
   * @param string $host
   *   The multisite host to delete.
   * @param array $sites
   *   Application name keyed by site host, from the manifest.
   * @param array $options
   *   Command options.
   *
   * @return \SiteNow\Plan\Plan
   *   The plan: the decision always, plus the steps and next-steps when
   *   validation passes (a failed plan carries neither).
   */
  private function decide(string $host, array $sites, array $options): Plan {
    $root = $this->repoRoot;
    $title = "multisite:delete {$host}";

    $app = $sites[$host] ?? NULL;
    $dir = $this->siteDirectory($host);
    $id = Multisite::getIdentifier("https://{$host}");
    $db = $app ? $this->databaseName($host, $app) : '';

    $flags = array_filter(
      $options,
      fn($value) => $value !== NULL && $value !== FALSE && $value !== ''
    );

    $input = [
      'host' => $host,
      'dir' => $dir,
      'id' => $id,
      'db' => $db,
      'app' => $app,
      'flags' => $flags,
    ];

    // Checks that need no Acquia API: environment, manifest, and local
    // filesystem. A FAIL here returns before any API call is made.
    $checks = [
      $this->checkHostShell(),
      $this->checkAcquiaCredentials(),
      new Check(self::CHECK_SITE_IN_MANIFEST, function () use ($app, $host): CheckResult {
        return $app !== NULL
          ? CheckResult::pass(['app' => $app])
          : CheckResult::fail("Site {$host} is not in blt/manifest.yml. Nothing to delete.");
      }),
      new Check(self::CHECK_NOT_DEFAULT_SITE, function () use ($dir, $host): CheckResult {
        // demo.sitenow.uiowa.edu is in the manifest and sites.php maps it to
        // 'default', so every other check passes for it: the directory exists,
        // and its derived database is the application's own. Deleting it would
        // take out docroot/sites/default, the application database, the default
        // site's files on all three mounts, and the sites.php aliases the whole
        // application is served on. No multisite is worth that, so the shared
        // directory is refused by name rather than left to a later check.
        return $dir !== self::DEFAULT_SITE_DIRECTORY
          ? CheckResult::pass()
          : CheckResult::fail("{$host} resolves to the shared docroot/sites/default directory, which this command will not delete.");
      }),
    ];

    $mounts = $this->mountsByEnv($id);

    // The checks below describe a delete only a site with its own manifest
    // entry and its own directory can have. A host that is absent from the
    // manifest or resolves to the shared default site has neither, and the
    // FAIL above already says so; running these anyway contradicts it.
    if ($app !== NULL && $dir !== self::DEFAULT_SITE_DIRECTORY) {
      $registry = new Applications("{$root}/sitenow/applications.yml");

      $checks[] = new Check(self::CHECK_SITE_DIR_EXISTS, function () use ($root, $dir, $host): CheckResult {
        // The manifest and the working tree disagreeing is the state a
        // half-finished delete leaves behind; fail rather than guess.
        return is_dir("{$root}/docroot/sites/{$dir}")
          ? CheckResult::pass()
          : CheckResult::fail("Site directory docroot/sites/{$dir} does not exist, but {$host} is in the manifest.");
      });

      $checks[] = new Check(self::CHECK_APP_REGISTERED, function () use ($registry, $app): CheckResult {
        return $registry->uuid($app) !== NULL
          ? CheckResult::pass(['uuid' => $registry->uuid($app)])
          : CheckResult::fail("Application '{$app}' is not in sitenow/applications.yml, so its cloud resources cannot be addressed.");
      });

      $checks[] = new Check(self::CHECK_DRUSH_ALIAS_MOUNTS, function () use ($root, $mounts, $id): CheckResult {
        // An absent alias file and one that defines no users both leave
        // mountsByEnv() with nothing to return, so they are told apart here
        // rather than reported as the same fault.
        $path = "drush/sites/{$id}.site.yml";

        if (!is_file("{$root}/{$path}")) {
          return CheckResult::fail("{$path} does not exist. The files mount path cannot be resolved.");
        }

        $missing = array_diff(self::ENVIRONMENTS, array_keys($mounts));

        return empty($missing)
          ? CheckResult::pass(['mounts' => $mounts])
          : CheckResult::fail("{$path} is missing a user for: " . implode(', ', $missing) . '. The files mount path cannot be resolved.');
      });

      $checks[] = new Check(self::CHECK_DATABASE_NAME_MATCHES, function () use ($root, $dir, $db): CheckResult {
        // The site's blt.yml is authoritative for the database it uses. If that
        // disagrees with the name derived from the directory, the derived name
        // belongs to a database this site never used, so refuse rather than
        // guess which one to delete.
        $blt_path = "{$root}/docroot/sites/{$dir}/blt.yml";

        if (!is_file($blt_path)) {
          return CheckResult::fail("No blt.yml found at docroot/sites/{$dir}, so the site's database cannot be confirmed.");
        }

        $configured = Yaml::parseFile($blt_path)['drupal']['db']['database'] ?? NULL;

        return $configured === $db
          ? CheckResult::pass()
          : CheckResult::fail("Database mismatch: docroot/sites/{$dir}/blt.yml names '{$configured}', expected '{$db}'.");
      });
    }

    if (empty($options['no-commit'])) {
      $checks = array_merge(
        $checks,
        $this->gitChecks($this->currentBranch(), !empty($options['dry-run']))
      );
    }

    $validation = $this->runChecks($checks);
    $summary = $this->summary($input);

    // Nothing above touches the network. A local failure returns here so a
    // misidentified site never reaches the cloud reads, let alone a delete.
    if ($validation['overall'] === CheckStatus::Fail) {
      return new Plan($title, $input, $validation, $summary);
    }

    $creds = $this->getAcquiaCredentials();
    $client = $this->getAcquiaCloudApiClient($creds['key'], $creds['secret']);
    $uuid = (new Applications("{$root}/sitenow/applications.yml"))->uuid($app);

    // What actually exists on Acquia. Read for every run, including --dry-run:
    // the steps are built from these facts, so the preview lists the real
    // resources rather than the ones a site is assumed to have.
    try {
      $cloud = $this->gatherCloud($client, $uuid, $input, $mounts);
    }
    catch (\Throwable $e) {
      $validation = $this->mergeValidation($validation, $this->runChecks([
        new Check(self::CHECK_CLOUD_READABLE, fn() => CheckResult::fail("Cannot read cloud state for {$host}: {$e->getMessage()}")),
      ]));

      return new Plan($title, $input, $validation, $summary);
    }

    $validation = $this->mergeValidation($validation, $this->runChecks($this->cloudChecks($cloud, $input)));
    $summary = array_merge($summary, $this->cloudSummary($cloud));
    $plan = new Plan($title, $input, $validation, $summary, $cloud);

    // A failed plan carries the decision only; skip building the steps that
    // would never run.
    if ($plan->failed()) {
      return $plan;
    }

    $this->buildSteps($plan, $input, $options, $cloud, $client);
    $plan->nextSteps = $this->nextSteps($options);

    return $plan;
  }

  /**
   * Resolve each environment's mount name from the site's drush alias.
   *
   * Applications disagree with drush on the middle environment's name:
   * uiowa07-09 call it 'stage' where the alias calls it 'test'. The alias
   * records the Acquia name in its user (@x.test has `user: uiowa09.stage`),
   * and that is what the shared filesystem path uses, so it is read here
   * rather than derived.
   *
   * @param string $id
   *   The multisite identifier.
   *
   * @return array
   *   Mount name (app.env) keyed by drush alias environment, omitting any
   *   environment the alias does not define.
   */
  protected function mountsByEnv(string $id): array {
    $path = "{$this->repoRoot}/drush/sites/{$id}.site.yml";

    if (!is_file($path)) {
      return [];
    }

    $alias = Yaml::parseFile($path) ?? [];
    $mounts = [];

    foreach (self::ENVIRONMENTS as $env) {
      $user = $alias[$env]['user'] ?? NULL;

      if (is_string($user) && $user !== '') {
        $mounts[$env] = $user;
      }
    }

    return $mounts;
  }

  /**
   * The domains a site could own on Acquia.
   *
   * Scope is this site's own domains: the three internal names generated at
   * creation, the host, and `www.<host>`, which many sites register. The local
   * ddev domain is left out, since it exists only in sites.php.
   *
   * Which of these actually exist varies per site, so the caller intersects
   * this list with the environment rather than assuming all of them.
   *
   * @param string $host
   *   The multisite host.
   * @param string $id
   *   The multisite identifier.
   *
   * @return string[]
   *   Candidate domains, deduplicated.
   */
  protected function candidateDomains(string $host, string $id): array {
    $internal = Multisite::getInternalDomains($id);

    return array_values(array_unique([
      $internal['dev'],
      $internal['test'],
      $internal['prod'],
      $host,
      "www.{$host}",
    ]));
  }

  /**
   * Read what the site still has on Acquia.
   *
   * Read-only. Three SSH probes and two API calls, so it is the slow part of a
   * run; it happens once and both the checks and the steps use the result.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   * @param string $uuid
   *   The application UUID.
   * @param array $input
   *   Normalized command input.
   * @param array $mounts
   *   Mount name keyed by environment, from mountsByEnv().
   *
   * @return array
   *   ['uuid' => string, 'mounts' => array, 'files' => [env => bool],
   *   'database' => bool, 'domains' => [['domain', 'env', 'env_uuid'], ...]].
   *
   * @throws \RuntimeException
   *   If an environment or the API cannot be reached.
   */
  protected function gatherCloud(Client $client, string $uuid, array $input, array $mounts): array {
    $cloud = [
      'uuid' => $uuid,
      'mounts' => $mounts,
      'files' => [],
      'database' => FALSE,
      'domains' => [],
    ];

    $filesystem = new Mounts($this->repoRoot);

    foreach ($mounts as $env => $mount) {
      $cloud['files'][$env] = $filesystem->siteDirectoryExists("{$input['id']}.{$env}", $mount, $input['dir']);
    }

    $cloud['database'] = (new CloudApi($client))->databaseExists($uuid, $input['db']);

    // Each environment response already carries its domain list, so the
    // candidates are matched against it rather than queried one at a time.
    $candidates = $this->candidateDomains($input['host'], $input['id']);

    foreach ((new Environments($client))->getAll($uuid) as $environment) {
      foreach (array_intersect($candidates, (array) $environment->domains) as $domain) {
        $cloud['domains'][] = [
          'domain' => $domain,
          'env' => $environment->name,
          'env_uuid' => $environment->uuid,
        ];
      }
    }

    return $cloud;
  }

  /**
   * Checks describing what the cloud reads found.
   *
   * A resource that is already gone is a WARN, not a failure: that is the state
   * a partially finished delete leaves behind, and the right response is to
   * clean up what remains — but not silently, and not under --yes.
   *
   * @param array $cloud
   *   Output of gatherCloud().
   * @param array $input
   *   Normalized command input.
   *
   * @return \SiteNow\Plan\Check[]
   *   The cloud presence checks.
   */
  protected function cloudChecks(array $cloud, array $input): array {
    return [
      new Check(self::CHECK_CLOUD_FILES, function () use ($cloud, $input): CheckResult {
        $missing = array_keys(array_filter($cloud['files'], fn($present) => !$present));

        return empty($missing)
          ? CheckResult::pass()
          : CheckResult::warn("No files directory for {$input['dir']} on: " . implode(', ', $missing) . '. Already deleted, or the site never had files there.');
      }),
      new Check(self::CHECK_CLOUD_DATABASE, function () use ($cloud, $input): CheckResult {
        return $cloud['database']
          ? CheckResult::pass()
          : CheckResult::warn("Database {$input['db']} is not on {$input['app']}. Already deleted, or it never existed.");
      }),
      new Check(self::CHECK_CLOUD_DOMAINS, function () use ($cloud, $input): CheckResult {
        return $cloud['domains']
          ? CheckResult::pass(['count' => count($cloud['domains'])])
          : CheckResult::warn("No domains for {$input['host']} are registered on {$input['app']}. Already deleted, or the site was served without them.");
      }),
    ];
  }

  /**
   * Summary rows describing the cloud resources found.
   *
   * @param array $cloud
   *   Output of gatherCloud().
   *
   * @return array
   *   Array of ['label' => string, 'value' => string] rows.
   */
  protected function cloudSummary(array $cloud): array {
    $with_files = array_keys(array_filter($cloud['files']));
    $domains = array_map(fn($d) => "{$d['domain']} ({$d['env']})", $cloud['domains']);

    return [
      ['label' => 'Cloud files', 'value' => $with_files ? implode(', ', $with_files) : 'none'],
      ['label' => 'Cloud DB', 'value' => $cloud['database'] ? 'present' : 'none'],
      ['label' => 'Cloud domains', 'value' => $domains ? implode(', ', $domains) : 'none'],
    ];
  }

  /**
   * Assemble the rows for the plan header.
   *
   * @param array $input
   *   Normalized command input.
   *
   * @return array
   *   Array of ['label' => string, 'value' => string] rows.
   */
  private function summary(array $input): array {
    if ($input['app'] === NULL) {
      return [];
    }

    return [
      ['label' => 'Application', 'value' => $input['app']],
      ['label' => 'Directory', 'value' => "docroot/sites/{$input['dir']}"],
      ['label' => 'Database', 'value' => $input['db']],
      ['label' => 'Drush alias', 'value' => "drush/sites/{$input['id']}.site.yml"],
    ];
  }

  /**
   * Build the ordered steps that delete the multisite.
   *
   * Cloud first, then the repository: a failed cloud teardown leaves the
   * working tree untouched, and the repository is what makes the site
   * findable, so a run that dies partway can be retried against it.
   *
   * Each addStep() call pairs a display label with a closure that performs the
   * action when the plan is applied; the same steps drive the plan preview.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan to add the steps to.
   * @param array $input
   *   Normalized command input.
   * @param array $options
   *   Command options.
   * @param array $cloud
   *   Output of gatherCloud().
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   */
  protected function buildSteps(Plan $plan, array $input, array $options, array $cloud, Client $client): void {
    $root = $this->repoRoot;
    $host = $input['host'];
    $dir = $input['dir'];
    $id = $input['id'];
    $app = $input['app'];
    $fs = new Filesystem();

    $this->addCloudSteps($plan, $input, $cloud, $client);

    // Whether the site carries committed configuration decides what the
    // commit stages; read it before the removal makes it unknowable.
    $has_site_config = is_dir("{$root}/config/sites/{$dir}");

    if ($has_site_config) {
      $plan->addStep(
        "Remove <info>config/sites/{$dir}</info>",
        function () use ($fs, $root, $dir) {
          $fs->remove("{$root}/config/sites/{$dir}");
        }
      );
    }

    $plan->addStep(
      "Remove <info>docroot/sites/{$dir}</info>",
      function () use ($fs, $root, $dir) {
        $fs->remove("{$root}/docroot/sites/{$dir}");
      }
    );

    $plan->addStep(
      "Remove <info>drush/sites/{$id}.site.yml</info>",
      function () use ($fs, $root, $id) {
        $fs->remove("{$root}/drush/sites/{$id}.site.yml");
      }
    );

    $sites_php = "{$root}/docroot/sites/sites.php";
    $plan->addStep(
      "Remove <info>sites.php</info> directory aliases for <info>{$host}</info>",
      function () use ($sites_php, $dir) {
        (new SitesPhp($sites_php))->removeAliases($dir);
      }
    );

    // The manifest entry goes last of the repository steps: it is what makes
    // the site selectable, so a run that fails before this point can be
    // retried against the same site.
    $manifest_path = $this->manifestPath();
    $plan->addStep(
      "Remove <info>{$host}</info> from <info>blt/manifest.yml</info> (app: <info>{$app}</info>)",
      function () use ($manifest_path, $app, $host) {
        (new Manifest($manifest_path))->removeSite($app, $host);
      }
    );

    if (empty($options['no-commit'])) {
      $message = "Delete {$host} multisite on {$app}";
      $commit_paths = [
        'docroot/sites/sites.php',
        'blt/manifest.yml',
        "docroot/sites/{$dir}",
        "drush/sites/{$id}.site.yml",
      ];

      if ($has_site_config) {
        $commit_paths[] = "config/sites/{$dir}";
      }

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
   * Add the cloud teardown steps.
   *
   * One step per resource that gatherCloud() found, so the plan lists what will
   * actually be deleted and a resource already gone produces no step. Each
   * operation confirms its own deletion before returning, which is what lets
   * the repository steps that follow assume the cloud half succeeded.
   *
   * Files come first because they are the only part that cannot be recreated
   * from the repository; the database and domains are addressed by name, so a
   * run that fails partway can be retried.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan to add the steps to.
   * @param array $input
   *   Normalized command input.
   * @param array $cloud
   *   Output of gatherCloud().
   * @param \AcquiaCloudApi\Connector\Client $client
   *   An authenticated Acquia Cloud API client.
   */
  protected function addCloudSteps(Plan $plan, array $input, array $cloud, Client $client): void {
    $root = $this->repoRoot;
    $app = $input['app'];
    $dir = $input['dir'];
    $id = $input['id'];
    $db = $input['db'];

    $filesystem = new Mounts($root);

    foreach (array_keys(array_filter($cloud['files'])) as $env) {
      $mount = $cloud['mounts'][$env];
      $alias = "{$id}.{$env}";
      $path = $filesystem->siteDirectory($mount, $dir);

      $plan->addStep(
        "Delete files <info>{$path}</info>",
        function (SymfonyStyle $io) use ($filesystem, $alias, $mount, $dir, $path) {
          $filesystem->deleteSiteDirectory($alias, $mount, $dir);
          $io->writeln("  Deleted <info>{$path}</info>.");
        }
      );
    }

    if (!empty($cloud['database'])) {
      $uuid = $cloud['uuid'];

      $plan->addStep(
        "Delete cloud database <info>{$db}</info> on <info>{$app}</info>",
        function (SymfonyStyle $io) use ($client, $uuid, $app, $db) {
          (new CloudApi($client))->deleteDatabase($uuid, $app, $db);
          $io->writeln("  Deleted database <info>{$db}</info> on <info>{$app}</info>.");
        }
      );
    }

    foreach ($cloud['domains'] as $found) {
      $domain = $found['domain'];
      $env_uuid = $found['env_uuid'];
      $env_label = "{$app}.{$found['env']}";

      $plan->addStep(
        "Delete domain <info>{$domain}</info> on <info>{$env_label}</info>",
        function (SymfonyStyle $io) use ($client, $env_uuid, $env_label, $domain) {
          (new CloudApi($client))->deleteDomain($env_uuid, $env_label, $domain);
          $io->writeln("  Deleted domain <info>{$domain}</info> on <info>{$env_label}</info>.");
        }
      );
    }
  }

  /**
   * Build the post-apply guidance lines for a delete.
   *
   * @param array $options
   *   Command options.
   *
   * @return string[]
   *   Guidance lines shown after a successful run.
   */
  private function nextSteps(array $options): array {
    return !empty($options['no-commit'])
      ? ['Commit the removals when ready.']
      : [$this->pushGuidance()];
  }

}
