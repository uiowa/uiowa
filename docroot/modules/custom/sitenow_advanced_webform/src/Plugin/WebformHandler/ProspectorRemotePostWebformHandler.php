<?php

namespace Drupal\sitenow_advanced_webform\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Element\WebformMessage;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Webform submission remote post handler.
 *
 * @WebformHandler(
 *   id = "prospector_remote_post",
 *   label = @Translation("Prospector Remote Post"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to ITS-AIS' Prospector application."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class ProspectorRemotePostWebformHandler extends WebformHandlerBase {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $webform = $this->getWebform();

    // Load configuration.
    $config = $this->configFactory->get('sitenow_advanced_webform.settings');

    // We need a site UUID to proceed.
    $site_uuid = $config->get('prospector.site_uuid');
    if (!$site_uuid) {
      // Generate a URL for the sitenow_advanced_webform settings form.
      $url = Url::fromRoute('sitenow_advanced_webform.settings_form')->toString();
      // Print a message directing the user to the settings form.
      $form['missing_uuid'] = [
        '#markup' => $this->t('<strong>Warning:</strong> The website UUID required for this handler is missing. <a href="@url">Please configure it at here</a>.', [
          '@url' => $url,
        ]),
        '#weight' => -100,
      ];
    }

    // We need an endpoint URL to proceed.
    $endpoint_url = $config->get('prospector.endpoint_url');
    if (!$endpoint_url) {
      // Print a message letting the user know they need to contact
      // the SiteNow team.
      $form['missing_uuid'] = [
        '#markup' => $this->t('<strong>Warning:</strong> The Prospector endpoint URL is missing. Please contact the SiteNow team for assistance.'),
        '#weight' => -100,
      ];
    }

    // Load basic auth credentials from config.
    $auth = $config->get('prospector.auth');
    // We need auth credentials to proceed.
    if (!isset($auth['user']) || !isset($auth['pass'])) {
      $form['missing_auth'] = [
        '#markup' => $this->t('<strong>Warning:</strong> Prospector authentication credentials are missing. Please contact the SiteNow team for assistance.'),
        '#weight' => -100,
      ];
    }
    // Submission data.
    $form['submission_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Submission data'),
    ];
    $form['submission_data']['excluded_data'] = [
      '#type' => 'webform_excluded_elements',
      '#title' => $this->t('Posted data'),
      '#title_display' => 'invisible',
      '#webform_id' => $webform->id(),
      '#required' => TRUE,
      '#default_value' => $this->configuration['excluded_data'],
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
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Load configuration.
    $config = $this->configFactory->get('sitenow_advanced_webform.settings');

    // We need a site UUID to proceed.
    $site_uuid = $config->get('prospector.site_uuid');
    if (!$site_uuid) {
      // Log that the site UUID is missing.
      $this->getLogger()->error('Prospector Site UUID is missing.');
      return;
    }

    // We need an endpoint URL to proceed.
    $endpoint_url = $config->get('prospector.endpoint_url');
    if (!$endpoint_url) {
      // Log that the endpoint URL is missing.
      $this->getLogger()->error($this->t('Prospector Endpoint URL is missing.'));
      return;
    }

    // Load basic auth credentials from config.
    $auth = $config->get('prospector.auth');
    // We need auth credentials to proceed.
    if (!isset($auth['user']) || !isset($auth['pass'])) {
      // Log that the auth credentials are missing.
      $this->getLogger()->error($this->t('Prospector authentication credentials are missing. Please contact the SiteNow team for assistance.'));
      return;
    }

    $options = [
      'auth' => array_values($auth),
    ];

    // Add data from the webform submission.
    $data = $this->getRequestData($webform_submission);
    $data['siteInteractionUuid'] = $site_uuid;
    $options['json'] = $data;

    // Send http request.
    try {
      $response = $this->httpClient->request('POST', $endpoint_url, $options);
      $this->getLogger()->notice($response->getBody()->getContents());
    }
    catch (GuzzleException $e) {
      // Log the exception.
      $this->getLogger()->error('An error occurred while posting the webform submission to Prospector. Error: @error', [
        '@error' => $e->getMessage(),
      ]);
      // Print error message.
      $this->messenger()->addError($this->t('An error occurred while posting the webform submission to Prospector.'));
      return;
    }
  }

  /**
   * Get a webform submission's request data.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission to be posted.
   *
   * @return array
   *   A webform submission converted to an associative array.
   */
  protected function getRequestData(WebformSubmissionInterface $webform_submission): array {
    // Get submission and elements data.
    $data = $webform_submission->toArray(TRUE);

    // Flatten data and prioritize the element data over the
    // webform submission data.
    $element_data = $data['data'];
    unset($data['data']);
    $data = $element_data + $data;

    // Excluded selected submission data.
    $data = array_diff_key($data, $this->configuration['excluded_data']);

    // Replace tokens.
    return $this->replaceTokens($data, $webform_submission);
  }

}
