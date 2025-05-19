<?php

namespace Drupal\sitenow_webform_ais_rfi\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform handler for AIS RFI Prospector.
 *
 * @WebformHandler(
 *   id = "ais_rfi_middleware_prospector",
 *   label = @Translation("AIS RFI Prospector"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to AIS RFI middleware for Prospector."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class AisRfiMiddlewareRemotePostWebformHandler extends AisRfiMiddlewareBaseWebformHandler {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'interaction_uuid' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Interaction UUID field.
    $form['interaction_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interaction UUID'),
      '#default_value' => $this->configuration['interaction_uuid'],
      '#description' => $this->t('The middleware interaction UUID. Without this, no data will be sent to the middleware. Contact siddharth-sarathe@uiowa.edu for assistance setting up an interaction UUID.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Save the interaction UUID configuration.
    $this->configuration['interaction_uuid'] = $form_state->getValue('interaction_uuid');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestData(WebformSubmissionInterface $webform_submission): array {
    $data = parent::getRequestData($webform_submission);

    // We need an interaction UUID to proceed.
    $interaction_uuid = $this->configuration['interaction_uuid'];
    if (!$interaction_uuid) {
      // Log that the site UUID is missing.
      $this->getLogger()->error('AIS RFI Middleware: Interaction UUID is missing. No data was sent to the middleware.');
      return [];
    }
    $data['siteInteractionUuid'] = $interaction_uuid;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientKey(): string {
    return 'prospector';
  }

}
