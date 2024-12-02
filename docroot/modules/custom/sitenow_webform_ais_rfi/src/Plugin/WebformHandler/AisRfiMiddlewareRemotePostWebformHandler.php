<?php

namespace Drupal\sitenow_webform_ais_rfi\Plugin\WebformHandler;

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
 *   id = "ais_rfi_middleware_remote_post",
 *   label = @Translation("AIS RFI Middleware Remote Post"),
 *   category = @Translation("External"),
 *   description = @Translation("Posts webform submissions to AIS RFI middleware."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class AisRfiMiddlewareRemotePostWebformHandler extends WebformHandlerBase {

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
  public function defaultConfiguration() {
    return [
      'excluded_data' => [],
      'interaction_uuid' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $webform = $this->getWebform();

    // Load configuration.
    $config = $this->configFactory->get('sitenow_webform_ais_rfi.settings');

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
    // Flatten the form tree for simplicity.
    $form['#tree'] = FALSE;

    // Interaction UUID field.
    $form['interaction_uuid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interaction UUID'),
      '#default_value' => $config->get('interaction_uuid'),
      '#description' => $this->t('The middleware interaction UUID.'),
    ];

    // We're using the webform_excluded_elements element type to generate the
    // list of elements to exclude from the submission data. It works as an
    // exclusion list behind the scenes, but we use it to include elements in
    // the data that gets sent.
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
    // Convert form state values to configuration.
    $this->applyFormStateToConfiguration($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // Load configuration.
    $config = $this->configFactory->get('sitenow_webform_ais_rfi.settings');

    // We need a site UUID to proceed.
    $interaction_uuid = $this->configuration['interaction_uuid'];
    if (!$interaction_uuid) {
      // Log that the site UUID is missing.
      $this->getLogger()->error('AIS RFI Middleware Remote Post: Interaction UUID is missing.');
      return;
    }

    // We need an endpoint URL to proceed.
    $endpoint_url = $config->get('prospector.endpoint_url');
    if (!$endpoint_url) {
      // Log that the endpoint URL is missing.
      $this->getLogger()->error($this->t('AIS RFI Middleware Remote Post: Endpoint URL is missing.'));
      return;
    }

    // Load basic auth credentials from config.
    $auth = $config->get('prospector.auth');
    // We need auth credentials to proceed.
    if (!isset($auth['user']) || !isset($auth['pass'])) {
      // Log that the auth credentials are missing.
      $this->getLogger()->error($this->t('AIS RFI Middleware Remote Post: Authentication credentials are missing. Please contact the SiteNow team for assistance.'));
      return;
    }

    $options = [
      'auth' => array_values($auth),
    ];

    // Add data from the webform submission elements.
    $data = $this->getRequestData($webform_submission);
    $data['siteInteractionUuid'] = $interaction_uuid;
    $data['clientKey'] = 'prospector';
    $options['json'] = $data;

    // Send http request.
    try {
      $response = $this->httpClient->request('POST', $endpoint_url, $options);
      $this->getLogger()->notice($response->getBody()->getContents());
    }
    catch (GuzzleException $e) {
      // Log the exception.
      $this->getLogger()->error('AIS RFI Middleware Remote Post: An error occurred while posting the webform submission to the middleware. Error: @error', [
        '@error' => $e->getMessage(),
      ]);
      // Print error message.
      $this->messenger()->addError($this->t('AIS RFI Middleware Remote Post: An error occurred while posting the webform submission to the middleware.'));
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
