<?php

namespace SiteNow\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Reader for the developer's SiteNow credentials (~/.sitenow/credentials.yml).
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
   * path still reads correctly in the guidance a command prints.
   *
   * @return string
   *   Absolute path to ~/.sitenow/credentials.yml.
   */
  public static function defaultPath(): string {
    return (getenv('HOME') ?: '~') . '/.sitenow/credentials.yml';
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
   * The file is optional.
   *
   * @return array
   *   The parsed file; empty when it is absent or empty.
   */
  private function all(): array {
    return $this->data ??= is_file($this->path) ? (Yaml::parseFile($this->path) ?: []) : [];
  }

}
