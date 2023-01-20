<?php

namespace Drupal\classrooms_core\Plugin\Block;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockBase;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * A 'Powered by SiteNow' block.
 *
 * This is really to establish 'Custom' category for config management purposes.
 *
 * @Block(
 *   id = "todayschedule_block",
 *   admin_label = @Translation("Today's Schedule"),
 *   category = @Translation("Site custom")
 * )
 */
class TodaySchedule extends BlockBase {
  private $cache;
  private $client;
  private $logger;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['label_display' => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' =>
      '<span>' . $this->t("Today's Schedule") . '</span>',
    ];
  }

  const BASE = 'https://api.maui.uiowa.edu/maui/api/';

  /**
   * Make a MAUI API request and return data.
   *
   * @param string $method
   *   The HTTP method to use.
   * @param string $path
   *   The API path to use. Do not include the base URL.
   * @param array $params
   *   Optional request parameters.
   * @param array $options
   *   Optional request options. All requests expect JSON response data.
   *
   * @return mixed
   *   The API response data.
   */
  public function request($method, $path, array $params = [], array $options = []) {
    // Encode any special characters and trim duplicate slash.
    $path = UrlHelper::encodePath($path);
    $uri = self::BASE . ltrim($path, '/');

    // Append any query string parameters.
    if (!empty($params)) {
      $query = UrlHelper::buildQuery($params);
      $uri .= "?{$query}";
    }

    // Merge additional options with default but allow overriding.
    $options = array_merge([
      'headers' => [
        'Accept' => 'application/json',
      ],
    ], $options);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_maui:request:{$hash}";
    // Default $data to FALSE in case of API fetch failure.
    $data = FALSE;

    if ($cache = $this->cache->get($cid)) {
      $data = $cache->data;
    }
    else {
      try {
        $response = $this->client->request($method, $uri, $options);
      }
      catch (RequestException | GuzzleException $e) {
        $this->logger->error('Error encountered getting data from @endpoint: @code @error', [
          '@endpoint' => $uri,
          '@code' => $e->getCode(),
          '@error' => $e->getMessage(),
        ]);
      }

      if (isset($response)) {
        $contents = $response->getBody()->getContents();

        /** @var object $data */
        $data = json_decode($contents);

        // Cache for 15 minutes.
        $this->cache->set($cid, $data, time() + 900);
      }
    }

    return $data;
  }

  /**
   * Return the schedule for a classroom for a date range.
   *
   * GET /pub/registrar/courses/AstraRoomSchedule/{startDate}/{endDate}/{bldgCode}/{roomNumber}.
   *
   * @param string $startdate
   *   Date formated as YYYY-MM-DD.
   * @param string $enddate
   *   Date formated as YYYY-MM-DD.
   * @param string $building_id
   *   The building code needs to match the code as it is entered in Astra.
   * @param string $room_id
   *   The room number needs to match the code as it is entered in Astra.
   *
   * @return array
   *   JSON decoded array of response data.
   */
  public function getRoomSchedule($startdate, $enddate, $building_id, $room_id) {
    return $this->request('GET', '/pub/registrar/courses/AstraRoomSchedule/' . $startdate . '/' . $enddate . '/' . $building_id . "/" . $room_id);
  }

}
