<?php

namespace SiteNow\Command;

use SiteNow\Config\Applications;
use SiteNow\Operation\ManifestRemove;
use SiteNow\Operation\SitesPhpRemove;
use SiteNow\Plan\Check;
use SiteNow\Plan\CheckResult;
use SiteNow\Plan\CommonChecks;
use SiteNow\Plan\Plan;
use SiteNow\Plan\PlanTrait;
use SiteNow\Traits\SiteNowCommandsTrait;
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
use Uiowa\Multisite;

/**
 * Deletes a SiteNow multisite.
 *
 * Replaces the BLT `uiowa:multisite:delete` (umd) command.
 *
 * The cloud resources come down before the repository does, for two reasons:
 * a failed cloud teardown then leaves the working tree untouched, and the
 * repository is what makes the site findable — remove it first and a failed
 * run could not be retried against the site it half-deleted.
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
  const CHECK_SITE_DIR_EXISTS = 'site_dir_exists';
  const CHECK_APP_REGISTERED = 'app_registered';
  const CHECK_DATABASE_NAME_MATCHES = 'database_name_matches';

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
      ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Apply without prompting. Blocked by any WARN.');
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
      new Check(self::CHECK_SITE_DIR_EXISTS, function () use ($root, $dir, $host): CheckResult {
        // The manifest and the working tree disagreeing is the state a
        // half-finished delete leaves behind; fail rather than guess.
        return is_dir("{$root}/docroot/sites/{$dir}")
          ? CheckResult::pass()
          : CheckResult::fail("Site directory docroot/sites/{$dir} does not exist, but {$host} is in the manifest.");
      }),
    ];

    if ($app !== NULL) {
      $registry = new Applications("{$root}/sitenow/applications.yml");

      $checks[] = new Check(self::CHECK_APP_REGISTERED, function () use ($registry, $app): CheckResult {
        return $registry->uuid($app) !== NULL
          ? CheckResult::pass(['uuid' => $registry->uuid($app)])
          : CheckResult::fail("Application '{$app}' is not in sitenow/applications.yml, so its cloud resources cannot be addressed.");
      });

      $checks[] = new Check(self::CHECK_DATABASE_NAME_MATCHES, function () use ($root, $dir, $db): CheckResult {
        // The database a site actually uses is the one named in its blt.yml,
        // which can drift from the name derived from the directory. Deleting
        // the derived name would take out a database this site never used.
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
      $branch_process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
      $branch_process->run();
      $branch = trim($branch_process->getOutput());
      $checks = array_merge($checks, $this->gitChecks($branch, !empty($options['dry-run'])));
    }

    $validation = $this->runChecks($checks);
    $summary = $this->summary($input);
    $plan = new Plan($title, $input, $validation, $summary);

    // A failed plan carries the decision only; skip building the steps that
    // would never run.
    if ($plan->failed()) {
      return $plan;
    }

    $this->buildSteps($plan, $input, $options);
    $plan->nextSteps = $this->nextSteps($options);

    return $plan;
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
   * Cloud first, then the repository. Each addStep() call pairs a display
   * label with a closure that performs the action when the plan is applied;
   * the same steps drive the plan preview.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan to add the steps to.
   * @param array $input
   *   Normalized command input.
   * @param array $options
   *   Command options.
   */
  private function buildSteps(Plan $plan, array $input, array $options): void {
    $root = $this->repoRoot;
    $host = $input['host'];
    $dir = $input['dir'];
    $id = $input['id'];
    $app = $input['app'];
    $fs = new Filesystem();

    $this->addCloudSteps($plan, $input);

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
        (new SitesPhpRemove($sites_php, $dir))->run();
      }
    );

    // The manifest entry goes last of the repository steps: it is what makes
    // the site selectable, so a run that fails before this point can be
    // retried against the same site.
    $manifest_path = $this->manifestPath();
    $plan->addStep(
      "Remove <info>{$host}</info> from <info>blt/manifest.yml</info> (app: <info>{$app}</info>)",
      function () use ($manifest_path, $app, $host) {
        (new ManifestRemove($manifest_path, $app, $host))->run();
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
   * Not yet implemented: what these delete is still open in #10011 — whether
   * the whole site directory comes off gfs or only its contents, and whether
   * the domains removed are the four derived ones or every domain on the
   * environment pointing at the site.
   *
   * They are added anyway, ahead of the repository steps, so that --dry-run
   * previews the real shape of the command and an apply stops here instead of
   * removing the repository half on its own — the failure mode that made BLT's
   * --simulate dangerous.
   *
   * @param \SiteNow\Plan\Plan $plan
   *   The plan to add the steps to.
   * @param array $input
   *   Normalized command input.
   */
  protected function addCloudSteps(Plan $plan, array $input): void {
    $db = $input['db'];
    $app = $input['app'];
    $id = $input['id'];
    $domains = Multisite::getInternalDomains($id);

    $pending = function (string $what): \Closure {
      return function () use ($what) {
        throw new \RuntimeException("Cloud teardown is not implemented yet: {$what}. See #10011; nothing was deleted.");
      };
    };

    foreach (['dev', 'test', 'prod'] as $env) {
      $plan->addStep(
        "Delete files for <info>{$input['dir']}</info> on <info>{$app}.{$env}</info>",
        $pending("file deletion on {$app}.{$env}")
      );
    }

    $plan->addStep(
      "Delete cloud database <info>{$db}</info> on <info>{$app}</info>",
      $pending("database {$db}")
    );

    $listed = implode(', ', [$domains['dev'], $domains['test'], $domains['prod'], $input['host']]);
    $plan->addStep(
      "Delete domains <info>{$listed}</info> on <info>{$app}</info>",
      $pending('domain deletion')
    );
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
    if (empty($options['no-commit'])) {
      $branch_process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
      $branch_process->run();
      $branch = trim($branch_process->getOutput());
      $first = "Push and merge via a pull request: <comment>git push --set-upstream origin {$branch}</comment>";
    }
    else {
      $first = 'Commit the removals when ready.';
    }

    return [
      $first,
      'An immediate production release is not necessary.',
    ];
  }

}
