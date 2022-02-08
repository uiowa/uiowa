<?php

namespace Drupal\tippie_core\Plugin\WebformHandler;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;

/**
 * Webform submission remote post handler.
 *
 * @WebformHandler(
 *   id = "pardot_remote_post",
 *   label = @Translation("Pardot Remote Post"),
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
  public function defaultConfiguration() {
    return [
      'endpoint_url' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['endpoint_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Submission URL to Pardot'),
      '#description' => $this->t('Campaign Token or Information'),
      '#default_value' => $this->configuration['endpoint_url'],
      '#required' => TRUE,
    ];
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

  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $variables['webform_submission'];
    $endpoint_url = $this->getSetting('endpoint_url');
    $params = UrlHelper::buildQuery([
      'firstname' => $webform_submission->getElementData('firstname'),
      'lastname' => $webform_submission->getElementData('lastname'),
      'email' => $webform_submission->getElementData('email'),
    ]);
    $variables['message']['iframe'] = [
      '#type' => 'html_tag',
      '#tag' => 'iframe',
      '#attributes' => [
        'src' => "$endpoint_url?$params",
        'width' => 1,
        'height' => 1,
        'frameborder' => 0,
        'style' => 'position: absolute;',
      ],
    ];
  }

}
