<?php

namespace Drupal\uiowa_events;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the event feed entity type.
 */
class EventsInstanceAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view event feed');

      case 'update':
        return AccessResult::allowedIfHasPermissions($account, ['edit event feed', 'administer event feed'], 'OR');

      case 'delete':
        return AccessResult::allowedIfHasPermissions($account, ['delete event feed', 'administer event feed'], 'OR');

      default:
        // No opinion.
        return AccessResult::neutral();
    }

  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions($account, ['create event feed', 'administer event feed'], 'OR');
  }

}
