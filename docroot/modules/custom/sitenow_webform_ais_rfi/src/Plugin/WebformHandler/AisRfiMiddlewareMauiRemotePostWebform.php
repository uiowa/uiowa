<?php

namespace Drupal\sitenow_webform_ais_rfi\Plugin\WebformHandler;

/**
 * Webform handler for AIS RFI Maui.
 *
 * @WebformHandler(
 *   id = "ais_rfi_middleware_maui",
 *   label = @Translation("AIS RFI Maui"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to AIS RFI middleware for Maui."),
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
  protected function getClientKey(): string {
    return 'maui';
  }

}
