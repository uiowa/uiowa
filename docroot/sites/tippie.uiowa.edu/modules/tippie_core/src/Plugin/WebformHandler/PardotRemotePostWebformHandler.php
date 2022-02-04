<?php

namespace Drupal\tippie_core\Plugin\WebformHandler;

use Drupal\Component\Utility\UrlHelper;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Webform submission remote post handler.
 *
 * @WebformHandler(
 *   id = "pardot_remote_post",
 *   label = @Translation("Pardot Remote post"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to a URL."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class PardotRemotePostWebformHandler extends WebformHandlerBase {
  /**
   * {@inheritdoc}
   */
  public function getSummary() {

  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $variables['webform_submission'];
    $params = UrlHelper::buildQuery([
      'firstname' => $webform_submission->getElementData('firstname'),
      'lastname' => $webform_submission->getElementData('lastname'),
      'email' => $webform_submission->getElementData('email'),
    ]);
    $variables['message']['iframe'] = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
      'src' => "https://go.tippie.uiowa.edu/l/683163/2019-08-29/3nqkk?$params",
      'width' => 1,
      'height' => 1,
      'frameborder' => 0,
      'style' => 'position: absolute;',
      ],
    ];
      // Remove submission ID from privateTempStore, so we only submit once.
      // This privateTempStore system prevents re-submission to Pardot on the
      // off-chance the confirmation message is loaded more than once.

 }
}


