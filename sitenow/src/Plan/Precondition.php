<?php

namespace SiteNow\Plan;

/**
 * A single validation check result.
 */
class Precondition {

  const PASS = 'PASS';
  const WARN = 'WARN';
  const FAIL = 'FAIL';

  public function __construct(
    public readonly string $name,
    public readonly string $status,
    public readonly string $message = '',
    public readonly array $context = [],
  ) {}

  public static function pass(string $name, array $context = []): static {
    return new static($name, self::PASS, '', $context);
  }

  public static function warn(string $name, string $message, array $context = []): static {
    return new static($name, self::WARN, $message, $context);
  }

  public static function fail(string $name, string $message, array $context = []): static {
    return new static($name, self::FAIL, $message, $context);
  }

  public function isPass(): bool {
    return $this->status === self::PASS;
  }

  public function isWarn(): bool {
    return $this->status === self::WARN;
  }

  public function isFail(): bool {
    return $this->status === self::FAIL;
  }

}
