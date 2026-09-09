<?php

namespace SiteNow\Traits;

use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\SslCertificates;
use SiteNow\Command\SiteUpdateCommand;
use SiteNow\Config\Credentials;
use SiteNow\Config\Manifest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use SiteNow\Utility\Multisite;

/**
 * Acquia Cloud, drush, and environment helpers for SiteNow console commands.
 */
trait SiteNowCommandsTrait {

  /**
   * The remote Acquia environments a command's --env option accepts.
   */
  const ENVIRONMENTS = ['dev', 'test', 'prod'];

  /**
   * Whether to force drush ANSI color, mirroring the command's own output.
   *
   * Drush runs through a pipe here and disables color by default; a command
   * sets this from its own decoration (on at an interactive terminal, off when
   * piped) so color survives, and drush() forwards it on every call.
   */
  protected bool $ansi = FALSE;

  /**
   * The sites.php host => directory aliases, read once per command run.
   *
   * Resolving a directory is a per-site lookup that callers make in a loop over
   * a whole application, and sites.php holds thousands of aliases, so the file
   * is included once and the map reused. A command that rewrites sites.php mid
   * run must not rely on this to reflect the write.
   */
  private ?array $siteAliases = NULL;

  /**
   * Build and return an Acquia Cloud API v2 client.
   *
   * @param string $key
   *   The API key (UUID) from cloud.acquia.com/a/profile/tokens.
   * @param string $secret
   *   The API secret from cloud.acquia.com/a/profile/tokens.
   *
   * @return \AcquiaCloudApi\Connector\Client
   *   An authenticated Acquia Cloud API client.
   */
  protected function getAcquiaCloudApiClient(string $key, string $secret): Client {
    $connector = new Connector([
      'key'    => $key,
      'secret' => $secret,
    ]);

    return Client::factory($connector);
  }

  /**
   * Credentials for accessing Acquia Cloud.
   *
   * @return \SiteNow\Config\Credentials
   *   A reader for ~/.sitenow/credentials.yml.
   */
  protected function credentials(): Credentials {
    return new Credentials(Credentials::defaultPath());
  }

  /**
   * The guidance printed when Acquia Cloud API credentials are missing.
   *
   * @return string
   *   An error message naming the keys to set and the file to set them in.
   */
  protected function acquiaCredentialsMissing(): string {
    return 'Acquia credentials not found. Set acquia.key and acquia.secret in ' . Credentials::defaultPath() . '.';
  }

  /**
   * Build an Acquia Cloud API client, or report missing credentials.
   *
   * Centralizes the "credentials present?" precondition that command classes
   * would otherwise each repeat. On success returns a ready client; on missing
   * credentials it prints a clean error and returns NULL so the caller can
   * exit before any API call fails opaquely.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report a missing-credentials error.
   *
   * @return \AcquiaCloudApi\Connector\Client|null
   *   An authenticated client, or NULL when credentials are not configured.
   */
  protected function requireAcquiaClient(SymfonyStyle $io): ?Client {
    $credentials = $this->credentials();

    if (!$credentials->hasAcquia()) {
      $io->getErrorStyle()->error($this->acquiaCredentialsMissing());
      return NULL;
    }

    $acquia = $credentials->acquia();

    return $this->getAcquiaCloudApiClient($acquia['key'], $acquia['secret']);
  }

  /**
   * Get Acquia Cloud applications sorted by name (natural sort).
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   *
   * @return array
   *   Array of ApplicationResponse objects sorted by app name.
   */
  protected function getSortedApplications(Client $client): array {
    $api_applications = new Applications($client);
    $applications = array_values(iterator_to_array($api_applications->getAll()));
    usort($applications, function ($a, $b) {
      $name_a = str_replace('prod:', '', $a->hosting->id);
      $name_b = str_replace('prod:', '', $b->hosting->id);
      return strnatcmp($name_a, $name_b);
    });
    return $applications;
  }

  /**
   * Get the drush alias for a multisite prod environment website.
   *
   * @param string $multisite
   *   The multisite domain.
   *
   * @return string
   *   The drush alias identifier (e.g., 'siteswebcommunity.prod').
   */
  protected function getDrushAlias(string $multisite): string {
    // @todo Move out of BLT.
    return Multisite::getIdentifier('http://' . $multisite);
  }

  /**
   * Get the sites enabled on this developer's machine.
   *
   * The list is the "sites" entries in sitenow/local.sites.yml, the local
   * counterpart to an application's manifest sites. An absent file or key
   * yields an empty list rather than a fleet-wide fallback, so a command never
   * acts on every site by accident.
   *
   * @return array
   *   Site hosts, empty when none are enabled.
   */
  protected function localSites(): array {
    $local = "{$this->repoRoot}/sitenow/local.sites.yml";

    return is_file($local) ? (Yaml::parseFile($local)['sites'] ?? []) : [];
  }

  /**
   * Get the path to the site manifest.
   *
   * @return string
   *   Absolute path to the manifest, present or not.
   */
  protected function manifestPath(): string {
    return Manifest::defaultPath($this->repoRoot);
  }

  /**
   * Require the site manifest to be present.
   *
   * A command that reads the manifest cannot do its job without it, and an
   * absent file would otherwise surface as a raw parser exception. Gate on this
   * first so the failure names the file plainly. Deliberately not a
   * fall-back-to-empty: an empty site list reads as "nothing to do", which
   * would turn a broken checkout or artifact into a silent success.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report the error.
   *
   * @return bool
   *   TRUE when the manifest exists; FALSE (after printing an error) otherwise.
   */
  protected function requireManifest(SymfonyStyle $io): bool {
    if (!is_file($this->manifestPath())) {
      $io->getErrorStyle()->error('Manifest file not found at ' . $this->manifestPath());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the whole site manifest, keyed by application.
   *
   * The manifest, sitenow/manifest.yml, is keyed by application (AH_SITE_GROUP)
   * and maintained by every provision. Callers that read it are expected to
   * have gated on requireManifest(); an absent file throws here rather than
   * reading as an empty fleet.
   *
   * @return array
   *   Site hosts keyed by application name.
   */
  protected function manifest(): array {
    return Yaml::parseFile($this->manifestPath()) ?: [];
  }

  /**
   * Get the sites one Acquia application owns, per the manifest.
   *
   * @param string $app
   *   The application name, e.g. 'uiowa09'.
   *
   * @return array
   *   Site hosts, empty when the application is not in the manifest.
   */
  protected function manifestSites(string $app): array {
    return $this->manifest()[$app] ?? [];
  }

  /**
   * Resolve a host to its multisite directory via sites.php.
   *
   * Mirrors Drupal's own aliasing: sites.php maps alias hosts to a directory,
   * and a host with no entry uses a same-named directory. This is what lets the
   * default site be addressed by its real domain (demo.sitenow.uiowa.edu) while
   * resolving to the default directory, and it is what keeps file syncs landing
   * in the right directory.
   *
   * Reads the using class's $repoRoot property, as every command in
   * sitenow/src/Command declares one.
   *
   * @param string $host
   *   The site host / canonical domain.
   *
   * @return string
   *   The multisite directory name.
   */
  protected function siteDirectory(string $host): string {
    if ($this->siteAliases === NULL) {
      $sites = [];
      $sites_file = "{$this->repoRoot}/docroot/sites/sites.php";
      if (is_file($sites_file)) {
        // sites.php populates $sites with host => directory aliases.
        include $sites_file;
      }
      $this->siteAliases = $sites;
    }

    return $this->siteAliases[$host] ?? $host;
  }

  /**
   * Derive the Acquia database name for a site.
   *
   * Acquia names each multisite's database after its directory with dots and
   * hyphens replaced by underscores, except the default site, whose database is
   * named after the application. The name comes from the sites.php-resolved
   * directory, not the host, so an aliased host lands on the same database
   * Acquia actually provisioned.
   *
   * Single-sourced deliberately: callers derive this in both directions — a
   * settings-include check from the site, and a copied database name back to
   * its site — and the two must agree.
   *
   * @param string $host
   *   The site host / canonical domain.
   * @param string $app
   *   The application (AH_SITE_GROUP), used for the default site.
   *
   * @return string
   *   The database name.
   */
  protected function databaseName(string $host, string $app): string {
    $dir = $this->siteDirectory($host);

    return $dir === 'default' ? $app : str_replace(['.', '-'], '_', $dir);
  }

  /**
   * The branch currently checked out.
   *
   * @return string
   *   The branch name, or an empty string when HEAD is not on a branch.
   */
  protected function currentBranch(): string {
    $process = new Process(['git', 'symbolic-ref', '--short', '--quiet', 'HEAD'], $this->repoRoot);
    $process->run();

    return $process->isSuccessful() ? trim($process->getOutput()) : '';
  }

  /**
   * The post-apply instruction for pushing a command's commit.
   *
   * A branch that already tracks a remote is told to push plainly.
   *
   * @return string
   *   The guidance line.
   */
  protected function pushGuidance(): string {
    $upstream = new Process(
      ['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}'],
      $this->repoRoot
    );
    $upstream->run();

    $push = $upstream->isSuccessful()
      ? 'git push'
      : "git push --set-upstream origin {$this->currentBranch()}";

    return "Push and merge via a pull request: <comment>{$push}</comment>";
  }

  /**
   * Run the repository's drush and return the finished process.
   *
   * Reads the using class's $repoRoot property, as every command in
   * sitenow/src/Command declares one.
   *
   * @param string[] $args
   *   Drush command and arguments. A caller addressing a site by alias passes
   *   it as a leading element; a caller addressing one by URI uses $uri.
   * @param bool $stream
   *   TRUE to stream output live, for a long-running call whose progress the
   *   user should see (a sync, an rsync, a deploy).
   * @param string|null $uri
   *   Site to run against as --uri, for a multisite call that is not addressed
   *   by alias. NULL runs without --uri.
   *
   * @return \Symfony\Component\Process\Process
   *   The finished process.
   */
  protected function drush(array $args, bool $stream = FALSE, ?string $uri = NULL): Process {
    $process = new Process(
      [
        "{$this->repoRoot}/vendor/bin/drush",
        ...($uri !== NULL ? ["--uri={$uri}"] : []),
        $this->ansi ? '--ansi' : '--no-ansi',
        ...$args,
      ],
      $this->repoRoot,
    );
    $process->setTimeout(NULL);
    if ($stream) {
      $process->run(fn ($type, $buffer) => print $buffer);
    }
    else {
      $process->run();
    }
    return $process;
  }

  /**
   * Reconcile one site by delegating to the site:update command.
   *
   * Both callers that copy a database — a local sync and the Acquia
   * post-db-copy hook — must bring the copy in line with the code, and
   * site:update already owns that. Its output streams so the user sees the
   * update as it happens.
   *
   * A skipped site or a config mismatch is not a failure of the caller's own
   * job: the update ran (or correctly declined to), and site:update has already
   * reported why. Only a genuine update error is returned as failure, so this
   * decides tolerance from site:update's exit codes in one place rather than in
   * each caller.
   *
   * @param string $site
   *   The site host / canonical domain to update.
   *
   * @return bool
   *   TRUE when site:update succeeded, skipped the site, or reported a config
   *   mismatch; FALSE when it failed.
   */
  protected function updateSite(string $site): bool {
    $process = new Process(
      ["{$this->repoRoot}/sn", 'site:update', $site, $this->ansi ? '--ansi' : '--no-ansi'],
      $this->repoRoot,
    );
    $process->setTimeout(NULL);
    $process->run(fn ($type, $buffer) => print $buffer);

    $tolerated = [
      Command::SUCCESS,
      SiteUpdateCommand::SKIPPED,
      SiteUpdateCommand::CONFIG_MISMATCH,
    ];

    return in_array($process->getExitCode(), $tolerated, TRUE);
  }

  /**
   * Determine if the command is running inside the DDEV container.
   *
   * @return bool
   *   TRUE if running in DDEV, FALSE otherwise.
   */
  protected function isDdev(): bool {
    return (bool) getenv('IS_DDEV_PROJECT');
  }

  /**
   * Require the command to run inside the DDEV container.
   *
   * On failure, prints an error naming the exact invocation to use and returns
   * FALSE so the caller can exit. The error says "locally" because the commands
   * that also run on Acquia call this behind that check, so it is only ever a
   * developer on a workstation who reads it. The command name is passed in
   * (rather than read via getName()) so this trait makes no assumption about
   * being mixed into a Symfony Command.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report the error.
   * @param string $command_name
   *   The command's name, for the suggested `ddev sn <name>` invocation.
   *
   * @return bool
   *   TRUE when running in DDEV; FALSE (after printing an error) otherwise.
   */
  protected function requireDdev(SymfonyStyle $io, string $command_name): bool {
    if (!$this->isDdev()) {
      $io->getErrorStyle()->error("Locally, this command must be run inside the DDEV container. Use: ddev sn {$command_name}");
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Require the --env option to name a real remote environment.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report the error.
   * @param string $env
   *   The --env option value.
   *
   * @return bool
   *   TRUE when the environment is valid; FALSE (after printing an error)
   *   otherwise.
   */
  protected function requireEnvironment(SymfonyStyle $io, string $env): bool {
    if (!in_array($env, self::ENVIRONMENTS, TRUE)) {
      $io->getErrorStyle()->error("Invalid environment '{$env}'. Must be one of: " . implode(', ', self::ENVIRONMENTS));
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Determine if the command is running on a developer host shell.
   *
   * @return bool
   *   TRUE when not inside DDEV and not on an Acquia Cloud environment.
   */
  protected function isHostShell(): bool {
    return !getenv('IS_DDEV_PROJECT') && !getenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Determine if the command is running on an Acquia Cloud environment.
   *
   * @return bool
   *   TRUE when running on Acquia Cloud (dev, stage, or prod), FALSE
   *   otherwise (host shell or DDEV).
   */
  protected function isAcquia(): bool {
    return (bool) getenv('AH_SITE_ENVIRONMENT');
  }

  /**
   * Get the application this process is running on, per Acquia Cloud.
   *
   * @return string|null
   *   The application name (AH_SITE_GROUP), or NULL when not on Acquia Cloud
   *   or AH_SITE_GROUP is unset.
   */
  protected function currentApp(): ?string {
    return $this->isAcquia() ? (getenv('AH_SITE_GROUP') ?: NULL) : NULL;
  }

  /**
   * Pin --apps to the running application when on Acquia Cloud.
   *
   * Locally (host shell or DDEV), --apps passes through untouched — fanning a
   * command out across several apps at once is the point there. On Acquia
   * Cloud, though, this process's own drush and SSH context belong to one
   * application's environment (e.g. a scheduled job on uiowa02.prod), which
   * has no business reaching every other application's sites. So the
   * selection is pinned to the running application there, and an --apps
   * naming a different one is rejected outright rather than silently
   * widened or narrowed.
   *
   * @param string[] $apps
   *   The --apps option, already comma-split.
   * @param \Symfony\Component\Console\Style\SymfonyStyle $err
   *   The error output style used to report a rejected --apps, and to note
   *   when an omitted --apps was silently pinned.
   *
   * @return string[]|null
   *   The (possibly pinned) app list, or NULL (after printing an error) when
   *   --apps conflicts with the running application.
   */
  protected function restrictToRunningApp(array $apps, SymfonyStyle $err): ?array {
    if (!$this->isAcquia()) {
      return $apps;
    }

    $current_app = $this->currentApp();
    if ($current_app === NULL) {
      $err->error('On Acquia Cloud but AH_SITE_GROUP is not set; cannot determine which application this is running on.');
      return NULL;
    }

    if ($apps && $apps !== [$current_app]) {
      $err->error("Running on Acquia Cloud ({$current_app}): --apps can only target the application this command is running on. Got: " . implode(', ', $apps));
      return NULL;
    }

    if (!$apps) {
      $err->writeln("Running on Acquia Cloud: --apps pinned to {$current_app}.");
    }

    return [$current_app];
  }

  /**
   * Gather active prod SSL coverage for a set of Acquia applications.
   *
   * The only live API query in the multisite-create decision: for each given
   * application it inspects the active prod certificate's SANs to see whether
   * the host (or a related domain) is covered. Application identity and load
   * come from the registry and manifest, not the API.
   *
   * @param \AcquiaCloudApi\Connector\Client $client
   *   The Acquia Cloud API client.
   * @param array $apps
   *   Application UUIDs keyed by application name.
   * @param array $ssl_parts
   *   Output of Multisite::getSslParts(), with 'sans' and 'related' keys.
   * @param callable|null $on_progress
   *   Optional progress callback invoked per application as
   *   function (string $app_name, int $total): void.
   *
   * @return array
   *   Coverage keyed by application name, each with: has_ssl, ssl_match,
   *   related, sans.
   */
  protected function getSslCoverage(Client $client, array $apps, array $ssl_parts, ?callable $on_progress = NULL): array {
    $environments = new Environments($client);
    $certificates = new SslCertificates($client);

    $total = count($apps);
    $coverage = [];

    foreach ($apps as $name => $uuid) {
      if ($on_progress) {
        $on_progress($name, $total);
      }

      $ssl_match = NULL;
      $related_match = NULL;
      $sans_count = NULL;

      foreach ($environments->getAll($uuid) as $env) {
        if ($env->name !== 'prod') {
          continue;
        }
        foreach ($certificates->getAll($env->uuid) as $cert) {
          if (!$cert->flags->active) {
            continue;
          }
          $sans_count = count($cert->domains);
          foreach ($cert->domains as $domain) {
            if ($domain === $ssl_parts['sans']) {
              $ssl_match = $domain;
            }
            elseif ($domain === $ssl_parts['related'] && !$related_match) {
              $related_match = $domain;
            }
          }
        }
      }

      $coverage[$name] = [
        'has_ssl' => $ssl_match !== NULL,
        'ssl_match' => $ssl_match,
        'related' => $related_match,
        'sans' => $sans_count,
      ];
    }

    return $coverage;
  }

  /**
   * Determine if SSH agent has keys loaded.
   *
   * @return bool
   *   TRUE if keys are available, FALSE otherwise.
   */
  protected function hasSshAgent(): bool {
    exec('ssh-add -l >/dev/null', $output, $exit_code);
    return $exit_code === 0;
  }

  /**
   * Require SSH agent keys to be loaded.
   *
   * Commands that reach prod sites over drush/SSH need forwarded agent keys.
   * On failure, prints an error and returns FALSE so the caller can exit.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report the error.
   *
   * @return bool
   *   TRUE when the SSH agent has keys; FALSE (after printing an error)
   *   otherwise.
   */
  protected function requireSshAgent(SymfonyStyle $io): bool {
    if (!$this->hasSshAgent()) {
      $io->getErrorStyle()->error("No SSH keys loaded. Run 'ssh-add' on the host, or 'ddev auth ssh' in the container, before running this command.");
      return FALSE;
    }
    return TRUE;
  }

}
