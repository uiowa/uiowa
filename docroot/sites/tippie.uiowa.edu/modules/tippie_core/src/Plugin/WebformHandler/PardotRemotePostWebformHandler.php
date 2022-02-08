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
    $form['pardot'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pardot settings'),
    ];
    $form['pardot']['handler_guidelines'] = [
      '#markup' => $this->t('This handler assumes the form confirmation
setting is set to "<em>Page</em>" and that <em>firstname</em>, <em>lastname</em>
and <em>email</em> form element keys exist.'),
    ];
    $form['pardot']['endpoint_url'] = [
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
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $variables['webform_submission'];
    $endpoint_url = $this->getSetting('endpoint_url');
    $elements = ['firstname', 'lastname', 'email'];
    $data = [];
    foreach ($elements as $element) {
      if ($value = $webform_submission->getElementData($element)) {
        $data[$element] = $value;
      }
    }
    $params = UrlHelper::buildQuery([$data]);
    if ($endpoint_url && count($elements) == count($data)) {
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
    else {
      // Log error message.
      $context = [
        '@form' => $this->getWebform()->label(),
      ];
      $this->getLogger('webform_submission')
        ->error('@form webform Pardot handler requirements not met.', $context);
    }
  }

}
