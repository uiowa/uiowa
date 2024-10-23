<?php

namespace Drupal\sitenow_advanced_webform\Plugin\WebformHandler;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
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
 *   description = @Translation("Posts webform submissions to a URL."),
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
    $site_uuid = $config->get('prospector.site_uuid');
    // We need a site UUID to proceed.
    if (!$site_uuid) {
      $this->messenger()->addError($this->t('Prospector Site UUID is missing.'));
      return;
    }

    // Load basic auth credentials from config.
    $auth = $config->get('prospector.auth');
    // We need auth credentials to proceed.
    if (!isset($auth['user']) || !isset($auth['pass'])) {
      $this->messenger()->addError($this->t('Prospector authentication credentials are missing.'));
      return;
    }
    $options = [
      'auth' => [$auth['user'], $auth['pass']],
    ];

    // Add data from first_name, last_name, email, and phone fields.
    $elements = ['first_name', 'last_name', 'email', 'phone'];
    $data = [];
    foreach ($elements as $element) {
      if ($value = $webform_submission->getElementData($element)) {
        $data[$element] = $value;
      }
    }
    $data['siteInteractionUuid'] = $site_uuid;

    // Build http request.
    $query = UrlHelper::buildQuery([$data]);
    $request_url = Url::fromUri('https://test.its.uiowa.edu/prospect-api/api/prospect/submit', ['query' => $query])->toString();

    // Send http request.
    try {
      $response = $this->httpClient->request('POST', $request_url, $options);
      // @todo Log response?
      // Print message confirming submission.
      $this->messenger()->addStatus($this->t('Webform submission posted to Prospector. Data: <pre>@data</pre>', [
        '@response_data' => $response->getBody()->getContents(),
      ]));
    }
    catch (GuzzleException $e) {
      // Handle exceptions.
      $this->messenger()->addError($this->t('An error occurred while posting the webform submission to Prospector.'));
      return;
    }
  }

}
