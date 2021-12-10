<?php

namespace Drupal\uiowa_hours;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

/**
 * Hours API service.
 */
class HoursApi {
  use StringTranslationTrait;

  const BASE = 'https://hours.iowa.uiowa.edu/api/Hours/';

  /**
   * The uiowa_hours logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The uiowa_hours cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $client;

  /**
   * The resource group to use in the URL of API requests.
   *
   * @var string
   */
  protected $group;

  /**
   * Constructs a Hours object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The uiowa_hours logger channel.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The uiowa_hours cache.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(LoggerInterface $logger, CacheBackendInterface $cache, ClientInterface $http_client, ConfigFactoryInterface $configFactory) {
    $this->logger = $logger;
    $this->cache = $cache;
    $this->client = $http_client;
    $this->group = $configFactory->get('uiowa_hours.settings')->get('group');
  }

  /**
   * Make a Hours API request and return data.
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

    // Merge additional options with default.
    $options = array_merge($options, [
      'headers' => [
        'Content-type' => 'application/json',
      ],
    ]);

    // Create a hash for the CID. Can always be decoded for debugging purposes.
    $hash = base64_encode($uri . serialize($options));
    $cid = "uiowa_hours:request:{$hash}";
    $data = [];

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

        // Return an array, so we can more easily unset the unused $id property.
        $data = json_decode($contents, TRUE);
        unset($data['$id']);

        // Dates are already sorted but the times within them are not.
        foreach ($data as $key => $date) {
          uasort($date, function ($a, $b) {
            return strtotime($a['start']) <=> strtotime($b['start']);
          });

          $data[$key] = $date;
        }

        // Cache for 5 minutes.
        $this->cache->set($cid, $data, time() + 300);
      }
    }

    return $data;
  }

  /**
   * Get resource groups.
   *
   * @return array
   *   The array of groups.
   */
  public function getGroups() {
    $groups = $this->request('GET', '');
    sort($groups);
    return $groups;
  }

  /**
   * Get resources for a group.
   *
   * @param string $group
   *   The group name.
   *
   * @return array
   *   The array of resources.
   */
  public function getResources($group) {
    $resources = $this->request('GET', $group);

    // Capitalize all resources before sorting because some are already caps.
    $resources = array_map('strtoupper', $resources);
    sort($resources);
    return $resources;
  }

  /**
   * Get hours based on resource and start date.
   *
   * @return array
   *   An array of hours.
   */
  public function getHours($resource, $start = 'today', $end = 'today') {
    $data = $this->request('GET', "$this->group/$resource", [
      'start' => date('m/d/Y', strtotime($start)),
      'end' => ($end == $start) ? '' : date('m/d/Y', strtotime($end)),
    ]);

    $render = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'uiowa-hours-container',
        ],
      ],
    ];

    if (empty($data)) {
      $render['closed'] = [
        '#type' => 'tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => [
            'uiowa-hours-status',
            'badge',
            'badge--orange',
          ],
        ],
        '#markup' => $this->t('Closed Today'),
      ];
    }
    else {
      // The v2 API indexes events by a string in Ymd format, e.g. 20211209.
      // @todo: Get the categories and add them here as badges.
      foreach ($data as $key => $date) {
        $render['hours'][$key] = [
          '#theme' => 'hours_card',
          '#attributes' => [
            'class' => [
              'uiowa-hours',
              'card--enclosed',
              'card--media-left',
            ],
          ],
          '#data' => [
            'date' => date('F d, Y', strtotime($key)),
            'times' => [
              '#theme' => 'item_list',
              '#items' => [],
              '#attributes' => [
                'class' => 'element--list-none',
              ],
            ],
          ],
        ];

        foreach ($date as $time) {
          $render['hours'][$key]['#data']['times']['#items'][] = [
            '#markup' => $this->t('<span class="badge badge--green">Open</span> @start - @end', [
              '@start' => date('g:ia', strtotime($time['startHour'])),
              '@end' => date('g:ia', '00:00:00' ? strtotime($time['endHour'] . ', +1 day') : strtotime($time['endHour'])),
            ]),
          ];
        }
      }
    }

    return $render;
  }

}
