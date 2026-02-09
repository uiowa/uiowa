<?php

namespace Drupal\sitenow\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\Core\Form\FormStateInterface;

/**
 * Overrides the default email handler to restrict access to attachments.
 *
 * @WebformHandler(
 *   id = "email",
 *   label = @Translation("Email Override"),
 *   category = @Translation("Notification"),
 *   description = @Translation("Overrides the default email handler to restrict access to attachments."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class EmailOverrideWebformHandler extends EmailWebformHandler {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    /** @var Drupal\uiowa_core\Access\UiowaCoreAccess $check */
    $check = \Drupal::service('uiowa_core.access_checker');

    /** @var Drupal\Core\Access\AccessResultInterface $access */
    $access = $check->access(\Drupal::currentUser()->getAccount());

    // Restrict access to attachments as they can cause runaway resource issues.
    if (isset($form['attachments']) && $access->isForbidden()) {
      $form['attachments']['#access'] = FALSE;
    }
    return $form;
  }

}
