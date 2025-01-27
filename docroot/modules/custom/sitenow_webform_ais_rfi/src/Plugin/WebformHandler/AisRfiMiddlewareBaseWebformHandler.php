<?php

namespace Drupal\sitenow_webform_ais_rfi\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for AIS RFI Middleware Webform Handlers.
 */
abstract class AisRfiMiddlewareBaseWebformHandler extends WebformHandlerBase {

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
      'included_data' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $webform = $this->getWebform();

    // Load configuration.
    $config = $this->configFactory->get('sitenow_webform_ais_rfi.settings');

    // Load basic auth credentials from config.
    $auth = $config->get('middleware.auth');
    // We need auth credentials to proceed.
    if (!isset($auth['user']) || !isset($auth['pass'])) {
      $form['missing_auth'] = [
        '#markup' => $this->t('<strong>Warning:</strong> The AIS RFI Middleware authentication credentials are missing. Please contact the SiteNow team for assistance.'),
        '#weight' => -100,
      ];
    }
    // Flatten the form tree for simplicity.
    $form['#tree'] = FALSE;
    $form['submission_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Data submitted to the middleware'),
      '#weight' => 99,
    ];

    // Get webform elements.
    // Inspired by WebformExcludedColumns.php without the extra bloat and
    // without the auto selection of newly added elements.
    $elements = $webform->getElementsInitializedFlattenedAndHasValue('view');

    // Reduce the returned array to key/value pairs.
    $options = array_combine(
      array_keys($elements),
      array_map(fn($item) => $item['#title'], $elements)
    );

    // Included webform elements field.
    $form['submission_data']['included_data'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Included data'),
      '#options' => $options,
      '#default_value' => $this->configuration['included_data'],

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

    // Load basic auth credentials from config.
    $auth = $config->get('middleware.auth');
    $endpoint_url = $config->get('middleware.endpoint_url');

    // Use an override URL if set.
    $endpoint_url = $config->get('middleware.endpoint_url');
    // If not set, use the default URL based on environment.
    if (!$endpoint_url) {
      $env = getenv('AH_SITE_ENVIRONMENT') ?: 'local';
      $endpoint_url = ($env === 'prod') ? 'https://app.its.uiowa.edu/prospect-api/api/prospect/submit' : 'https://test.its.uiowa.edu/prospect-api/api/prospect/submit';
    }

    if (!$auth) {
      $this->getLogger()->error($this->t('AIS RFI Middleware configuration is incomplete. No data was sent to the middleware.'));
      return;
    }

    $data = $this->getRequestData($webform_submission);

    $data = $this->getRequestData($webform_submission);
    if (empty($data)) {
      // If the data is empty, skip the remote post.
      return;
    }

    $data['clientKey'] = $this->getClientKey();

    $options = [
      'auth' => array_values($auth),
      'json' => $data,
    ];

    try {
      $response = $this->httpClient->request('POST', $endpoint_url, $options);
      $this->getLogger()->notice($this->t('AIS RFI Middleware: Success: @response_message', [
        '@response_message' => $response->getBody()->getContents(),
      ]));
    }
    catch (GuzzleException $e) {
      // Log the exception.
      $this->getLogger()->error('AIS RFI Middleware: An error occurred while posting the webform submission to the middleware. Error: @error', [
        '@error' => $e->getMessage(),
      ]);
      // Print error message.
      $this->messenger()->addError($this->t('AIS RFI Middleware: An error occurred while posting the webform submission to the middleware.'));
      return;
    }
  }

  /**
   * Get the client key for the middleware request.
   *
   * @return string
   *   The client key.
   */
  abstract protected function getClientKey(): string;

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

    // Flatten data and separate element data.
    $element_data = $data['data'];
    unset($data['data']);

    // Default included data per ITS AIS' request.
    // Additional data, "hostIp", "clientIp", and "postDate" are
    // passed separately with the remote post.
    $default_included_data = [
      'webform_id',
      'remote_addr',
      'uri',
    ];

    // Remove any data not in the default included data.
    $data = array_intersect_key($data, array_flip($default_included_data));

    // Included selected submission data.
    $element_data = array_intersect_key($element_data, array_flip($this->configuration['included_data']));

    // Merge element data with submission data, keeping it flat.
    $data = $element_data + $data;

    // Replace tokens.
    return $this->replaceTokens($data, $webform_submission);
  }

}
