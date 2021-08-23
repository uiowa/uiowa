<?php

namespace Drupal\uiowa_covid\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for UIowa COVID routes.
 */
class DataController extends ControllerBase {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The controller constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ClientInterface $client, ConfigFactoryInterface $configFactory) {
    $this->client = $client;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('config.factory')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    // @todo Limit release of new data until 10am central.
    $data = [];
    $endpoint = $this->configFactory->get('uiowa.covid')->get('endpoint');
    $user = $this->configFactory->get('uiowa.covid')->get('user');
    $key = $this->configFactory->get('uiowa.covid')->get('key');

    try {
      $response = $this->client->request('GET', $endpoint, [
        'auth' => [
          $user,
          $key,
        ],
      ]);

      // @todo Verify status/messages/JSON.
      $data = json_decode($response->getBody()->getContents());
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      watchdog_exception('uiowa_covid', $e);
    }

    return new JsonResponse($data);
  }

}
