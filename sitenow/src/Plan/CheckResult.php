<?php

namespace SiteNow\Plan;

/**
 * The result of evaluating a Check.
 *
 * Created via the static pass/warn/fail factories, typically inside a Check
 * closure. The check's name lives on the Check, not here.
 */
class CheckResult {

  public function __construct(
    public readonly CheckStatus $status,
    public readonly string $message = '',
    public readonly array $context = [],
  ) {}

  public static function pass(array $context = []): static {
    return new static(CheckStatus::Pass, '', $context);
  }

  public static function warn(string $message, array $context = []): static {
    return new static(CheckStatus::Warn, $message, $context);
  }

  public static function fail(string $message, array $context = []): static {
    return new static(CheckStatus::Fail, $message, $context);
  }

}
