<?php

namespace SiteNow\Traits;

use Consolidation\Config\Config;
use Consolidation\Config\Loader\ConfigProcessor;
use Consolidation\Config\Loader\YamlConfigLoader;
use AcquiaCloudApi\Connector\Client;
use AcquiaCloudApi\Connector\Connector;
use AcquiaCloudApi\Endpoints\Applications;
use AcquiaCloudApi\Endpoints\Environments;
use AcquiaCloudApi\Endpoints\SslCertificates;
use Symfony\Component\Console\Style\SymfonyStyle;
use Uiowa\Multisite;

/**
 * Acquia Cloud, drush, and environment helpers for SiteNow console commands.
 */
trait SiteNowCommandsTrait {

  /**
   * Get a config value by key from blt/local.blt.yml.
   *
   * @param string $key
   *   Dot-separated config key, e.g. 'uiowa.credentials.acquia.key'.
   *
   * @return mixed
   *   The config value, or NULL if not found.
   */
  protected function getConfigValue(string $key): mixed {
    // @todo Move out of BLT.
    $blt_local = getcwd() . '/blt/local.blt.yml';

    if (!file_exists($blt_local)) {
      return NULL;
    }

    $config = new Config();
    $loader = new YamlConfigLoader();
    $processor = new ConfigProcessor();
    $processor->extend($loader->load($blt_local));
    $config->replace($processor->export());

    return $config->get($key);
  }

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
   * Read the Acquia Cloud API credentials from blt/local.blt.yml.
   *
   * @return array
   *   ['key' => string|null, 'secret' => string|null].
   */
  protected function getAcquiaCredentials(): array {
    return [
      'key' => $this->getConfigValue('uiowa.credentials.acquia.key'),
      'secret' => $this->getConfigValue('uiowa.credentials.acquia.secret'),
    ];
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
    $creds = $this->getAcquiaCredentials();

    if (empty($creds['key']) || empty($creds['secret'])) {
      $io->error('Acquia credentials not found. Set uiowa.credentials.acquia.key/secret in blt/local.blt.yml.');
      return NULL;
    }

    return $this->getAcquiaCloudApiClient($creds['key'], $creds['secret']);
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
   * FALSE so the caller can exit. The command name is passed in (rather than
   * read via getName()) so this trait makes no assumption about being mixed
   * into a Symfony Command.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   The output style used to report the error.
   * @param string $command_name
   *   The command's name, for the suggested `ddev exec ./sn <name>` invocation.
   *
   * @return bool
   *   TRUE when running in DDEV; FALSE (after printing an error) otherwise.
   */
  protected function requireDdev(SymfonyStyle $io, string $command_name): bool {
    if (!$this->isDdev()) {
      $io->error("This command must be run inside the DDEV container. Use: ddev exec ./sn {$command_name}");
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
      $io->error("No SSH keys loaded. Run 'ddev auth ssh' before running this command.");
      return FALSE;
    }
    return TRUE;
  }

}
