<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Reader for the developer's SiteNow credentials (~/.sitenow/credentials.yml).
 *
 * Deliberately outside the repository: a secret that lives in the working tree
 * is a secret one command away from being committed, and the file is personal
 * rather than per-checkout. The location is a directory so that local config
 * added later has a home that is not the same file as the secrets.
 *
 * The file is optional. Every command that needs credentials states so as a
 * precondition and reports the absence itself, so an absent file reads as
 * "not configured yet" rather than an error from here.
 */
class Credentials {

  /**
   * The parsed file, read once per instance.
   *
   * @var array|null
   */
  private ?array $data = NULL;

  /**
   * Constructs a credentials reader.
   *
   * @param string $path
   *   Absolute path to the credentials YAML file.
   */
  public function __construct(
    private string $path,
  ) {}

  /**
   * The conventional location of the credentials file.
   *
   * Falls back to a literal tilde when the environment has no HOME, so the
   * path still reads correctly in the guidance a command prints. Nothing is
   * found there, which is the right outcome for an environment that cannot
   * name a home directory.
   *
   * @return string
   *   Absolute path to ~/.sitenow/credentials.yml.
   */
  public static function defaultPath(): string {
    return (getenv('HOME') ?: '~') . '/.sitenow/credentials.yml';
  }

  /**
   * The path this instance reads.
   *
   * @return string
   *   The credentials path.
   */
  public function path(): string {
    return $this->path;
  }

  /**
   * The Acquia Cloud API credentials.
   *
   * @return array
   *   ['key' => string|null, 'secret' => string|null].
   */
  public function acquia(): array {
    $acquia = $this->all()['acquia'] ?? [];

    return [
      'key' => $acquia['key'] ?? NULL,
      'secret' => $acquia['secret'] ?? NULL,
    ];
  }

  /**
   * Whether both Acquia Cloud API credentials are configured.
   *
   * @return bool
   *   TRUE when a key and a secret are both present and non-empty.
   */
  public function hasAcquia(): bool {
    $acquia = $this->acquia();

    return !empty($acquia['key']) && !empty($acquia['secret']);
  }

  /**
   * The whole credentials file.
   *
   * Parsed once and reused: nothing here writes, so a caller that asks whether
   * credentials are configured and then asks for them reads the same file twice
   * otherwise.
   *
   * @return array
   *   The parsed file; empty when it is absent or empty.
   */
  private function all(): array {
    return $this->data ??= is_file($this->path) ? (Yaml::parseFile($this->path) ?: []) : [];
  }

}
