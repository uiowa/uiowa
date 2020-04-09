<?php

namespace Drupal\uiowa_core\Services;

use Drupal\Core\Session\AccountProxy;

/**
 * Uiowa Core module helper functions.
 */
class Helpers {

  /**
   * Helper function to determine if the current user is an admin.
   *
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user account.
   *
   * @return bool
   *   Boolean indicating whether or not current user is an admin.
   */
  public function isAdmin(AccountProxy $current_user) {
    if ($current_user->id() == 1 || in_array('administrator', $current_user->getRoles())) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}
