<?php

namespace Drupal\uiowa_core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Uiowa specific access checker.
 */
class UiowaCoreAccess implements AccessInterface {

  /**
   * A custom access uiowa.
   *
   * @param  \Drupal\Core\Session\AccountInterface|null  $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account = NULL) {
    // Check the current account if no account was passed.
    if (is_null($account)) {
      $account = \Drupal::currentUser()->getAccount();
    }
    // Is this user an admin or user 1?
    return ((int) $account->id() === 1 || in_array('administrator', $account->getRoles())) ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
