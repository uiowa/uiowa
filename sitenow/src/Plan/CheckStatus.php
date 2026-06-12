<?php

namespace SiteNow\Plan;

/**
 * The outcome status of a validation check.
 */
enum CheckStatus: string {

  case Pass = 'PASS';
  case Warn = 'WARN';
  case Fail = 'FAIL';

}
