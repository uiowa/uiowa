<?php

namespace Drupal\sitenow_advanced_webform\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;

/**
 * Webform submission remote post handler.
 *
 * @WebformHandler(
 *   id = "prospector_remote_post",
 *   label = @Translation("Prospector Remote Post"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to a URL."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class ProspectorRemotePostWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'endpoint_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @todo Add configuration form elements.
    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $settings = $this->getSettings();

    return [
      '#markup' => $this->t('<strong>Endpoint URL:</strong> @endpoint', [
        '@endpoint' => $settings['endpoint_url'],
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    // @todo Add preprocessing code.
  }

}
