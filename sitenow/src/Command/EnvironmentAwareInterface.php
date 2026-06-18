<?php

namespace SiteNow\Command;

/**
 * A command that declares the environment it must run in.
 *
 * The `list` command reads this to show whether each command runs on the host
 * shell or inside the DDEV container.
 */
interface EnvironmentAwareInterface {

  /**
   * The environment the command must run in.
   *
   * @return string
   *   A short label, e.g. 'host', 'DDEV', or 'DDEV + SSH'.
   */
  public function environment(): string;

}
