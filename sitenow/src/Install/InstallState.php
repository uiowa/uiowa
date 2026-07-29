<?php

namespace SiteNow\Install;

/**
 * The result of classifying one site's installation.
 *
 * Carries the status, a human-readable reason for it, and — for a partial
 * install only — how much content the site holds. The content counts exist for
 * one decision: whether reinstalling would destroy anything.
 */
class InstallState {

  /**
   * Constructs an InstallState.
   *
   * @param \SiteNow\Install\InstallStatus $status
   *   The classification.
   * @param string $detail
   *   Why the site landed in that status, shown in reports. Empty when the
   *   status speaks for itself.
   * @param int|null $nodes
   *   Nodes authored by someone past uid 1, counted only for a partial install.
   *   NULL when not counted.
   * @param int|null $users
   *   Users past uid 1, counted only for a partial install. NULL when not
   *   counted.
   */
  public function __construct(
    public readonly InstallStatus $status,
    public readonly string $detail = '',
    public readonly ?int $nodes = NULL,
    public readonly ?int $users = NULL,
  ) {}

  /**
   * Whether the site holds content a reinstall would destroy.
   *
   * An install that never finished should hold nothing a person put there, so
   * anything found here means the assumption is wrong for this site and someone
   * should look before it is wiped. The profile's own default content does not
   * count: it belongs to the install, not to anyone using the site.
   *
   * @return bool
   *   TRUE when any node or user exists that the installer did not create.
   */
  public function hasContent(): bool {
    return ($this->nodes ?? 0) > 0 || ($this->users ?? 0) > 0;
  }

  /**
   * Describe the state as one line, for a report or a progress line.
   *
   * @return string
   *   The status, its reason, and the content it holds when that is the thing
   *   standing in the way.
   */
  public function describe(): string {
    $line = $this->status->value;

    if ($this->detail !== '') {
      $line .= ": {$this->detail}";
    }
    if ($this->hasContent()) {
      $line .= ', but holds ' . $this->contentSummary();
    }

    return $line;
  }

  /**
   * Summarize the content found in a partial install.
   *
   * @return string
   *   A counts phrase, e.g. "43 nodes, 12 users".
   */
  public function contentSummary(): string {
    return sprintf('%d nodes, %d users', $this->nodes ?? 0, $this->users ?? 0);
  }

}
