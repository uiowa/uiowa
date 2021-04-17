<?php

namespace Uiowa\Blt\Plugin\EnvironmentDetector;

use Acquia\Blt\Robo\Common\EnvironmentDetector;

class LocalDetector extends EnvironmentDetector {
  /**
   * Make an exception for 'local' which is set in our DrupalVM environment.
   *
   * @return bool
   */
  public static function isAhEnv() {
    $env = self::getAhEnv();
    return !($env === 'local') && (bool) $env;
  }

  /**
   * Override the local to use our isAhEnv method.
   * @return bool
   */
  public static function isLocalEnv() {
    return !self::isAhEnv();
  }
}
