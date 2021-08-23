<?php

namespace Drupal\uiowa_covid\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateHelper;
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
   * @param \GuzzleHttp\ClientInterface $client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
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
    $date = $this->getDataDate();
    $data = [];

    $endpoint = $this->configFactory->get('uiowa.covid')->get('endpoint');
    $user = $this->configFactory->get('uiowa.covid')->get('user');
    $key = $this->configFactory->get('uiowa.covid')->get('key');

    try {
      $response = $this->client->request('GET', "$endpoint/$date", [
        'auth' => [
          $user,
          $key,
        ],
      ]);

      $data = ['date' => $date] + json_decode($response->getBody()->getContents(), TRUE);
      ;
    }
    catch (RequestException | GuzzleException | ClientException $e) {
      watchdog_exception('uiowa_covid', $e);
    }

    return new JsonResponse($data);
  }

  /**
   * Calculate the day to get data for based on the day of the week.
   */
  private function getDataDate() {
    $weekdays = DateHelper::weekDaysUntranslated();
    $dow = $weekdays[date('w')];
    $time = date('G');

    // If Monday, Wednesday or Friday past 10am.
    if ($dow == 'Monday' || $dow == 'Wednesday' || $dow == 'Friday' && $time >= 10) {
      $date = date('m-d-Y');
    }
    else {
      // Anything else should go back to the previous reporting date.
      switch ($dow) {
        case 'Sunday':
        case 'Monday':
          $previous = 'last Friday';
          break;

        case 'Tuesday':
        case 'Thursday':
        case 'Saturday':
          $previous = 'yesterday';
          break;

        case 'Wednesday':
        case 'Friday':
          $previous = 'two days ago';
          break;
      }

      $date = date('m-d-Y', strtotime($previous));
    }

    return $date;
  }

}
