<?php

namespace Drupal\uiowa_core\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Adds in the custom before Ajax Command.
 */
class BeforeViewsAjaxCommand implements CommandInterface {

  /**
   * @inheritDoc
   */
  public function render() {
    return [
      'command' => 'beforeViewsAjaxCall',
//        'clicked' => $_COOKIE["STYXKEY_Checkbox_Clicked"] ?? NULL,
    ];
  }
}
