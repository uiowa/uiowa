<?php

namespace Drupal\sitenow_webform_ais_rfi\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform handler for AIS RFI MAUI.
 *
 * @WebformHandler(
 *   id = "ais_rfi_middleware_maui",
 *   label = @Translation("AIS RFI MAUI"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to AIS RFI middleware for MAUI."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class AisRfiMiddlewareMauiRemotePostWebform extends AisRfiMiddlewareBaseWebformHandler {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'ref_code' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Referral Code field.
    $form['ref_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Referral Code'),
      '#default_value' => $this->configuration['ref_code'],
      '#description' => $this->t('The middleware refCode. Without this, no data will be sent to the middleware.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Save the refCode config.
    $this->configuration['ref_code'] = $form_state->getValue('ref_code');
  }

  /**
   * {@inheritdoc}
   */
  protected function getRequestData(WebformSubmissionInterface $webform_submission): array {
    $data = parent::getRequestData($webform_submission);

    // We need refCode to proceed.
    $ref_code = $this->configuration['ref_code'];
    if (!$ref_code) {
      // Log that the refCode is missing.
      $this->getLogger()->error('AIS RFI Middleware: refCode is missing. No data was sent to the middleware.');
      return [];
    }
    $data['refCode'] = $ref_code;

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function getClientKey(): string {
    return 'maui';
  }

}
