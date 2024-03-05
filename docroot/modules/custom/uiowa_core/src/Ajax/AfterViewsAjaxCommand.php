<?php

namespace Drupal\uiowa_core\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Adds in the custom after Ajax Command.
 */
class AfterViewsAjaxCommand implements CommandInterface {

    /**
     * @inheritDoc
     */
    public function render()
    {
      return [
        'command' => 'afterViewsAjaxCall',
//        'clicked' => $_COOKIE["STYXKEY_Checkbox_Clicked"] ?? NULL,
      ];
    }
}
