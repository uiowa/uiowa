<?php

namespace Drupal\uiowa_jobs;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\smart_trim\TruncateHTML;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Laminas\Feed\Reader\Reader;
use Laminas\Feed\Reader\Exception\ExceptionInterface;

/**
 * Service for parsing RSS feeds from hris.uiowa.edu.
 */
class RssFeedParser {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new RssFeedParser object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger channel factory.
   */
  public function __construct(ClientInterface $http_client, CacheBackendInterface $cache, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->cache = $cache;
    $this->logger = $logger_factory->get('uiowa_jobs');
  }

  /**
   * Parse RSS feed with caching.
   *
   * @param string $url
   *   The feed URL.
   * @param int $cache_duration
   *   Cache duration in seconds (default 1 hour).
   *
   * @return array
   *   Array of stdClass job objects.
   *
   * @throws \InvalidArgumentException
   *   If the feed URL is not valid.
   */
  public function parse(string $url, int $cache_duration = 3600): array {
    // Validate feed URL first.
    if (!$this->validateFeedUrl($url)) {
      throw new \InvalidArgumentException('Invalid feed URL. Must be from hris.uiowa.edu.');
    }

    // Generate cache ID.
    $cache_id = 'uiowa_jobs:feed:' . md5($url);

    // Check cache first.
    if ($cache = $this->cache->get($cache_id)) {
      $this->logger->info('Returning cached jobs for @url', ['@url' => $url]);
      return $cache->data;
    }

    // Fetch and parse feed.
    try {
      $this->logger->info('Fetching jobs feed from @url', ['@url' => $url]);

      // Fetch feed content.
      $response = $this->httpClient->request('GET', $url, [
        'headers' => [
          'Accept' => 'application/rss+xml, application/xml, text/xml',
        ],
      ]);

      $feed_content = (string) $response->getBody();

      // Parse with Laminas Feed Reader.
      $channel = Reader::importString($feed_content);

      $jobs = [];
      $count = 0;
      foreach ($channel as $item) {
        $job = new \stdClass();
        $job->guid = $item->getId();
        $job->title = $item->getTitle();
        $job->link = $item->getLink();
        // Clean up the HTML and trim it to 300 characters.
        $job->description = $this->cleanHtml($item->getDescription(), 300);

        // Get publication date.
        $job->pubDate = 0;
        if ($date = $item->getDateModified()) {
          $job->pubDate = $date->getTimestamp();
        }

        $jobs[] = $job;
        // Limit processing to a maximum of 50 jobs.
        if (++$count == 50) {
          break;
        }
      }

      // Cache the results.
      $this->cache->set($cache_id, $jobs, time() + $cache_duration, ['uiowa_jobs:feed']);
      $this->logger->info('Cached @count jobs from @url', [
        '@count' => count($jobs),
        '@url' => $url,
      ]);

      return $jobs;
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to fetch feed from @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
    catch (ExceptionInterface $e) {
      $this->logger->error('Failed to parse feed from @url: @error', [
        '@url' => $url,
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Validate that a feed URL is from hris.uiowa.edu.
   *
   * @param string $url
   *   The feed URL to validate.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function validateFeedUrl(string $url): bool {
    $parsed = parse_url($url);

    // Must be HTTPS.
    if (!isset($parsed['scheme']) || $parsed['scheme'] !== 'https') {
      return FALSE;
    }

    // Must be from hris.uiowa.edu.
    if (!isset($parsed['host']) || $parsed['host'] !== 'hris.uiowa.edu') {
      return FALSE;
    }

    // Must be an otac-rss feed.
    if (!isset($parsed['path']) || !str_starts_with($parsed['path'], '/otac-rss/')) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Clean and sanitize HTML from job descriptions, and trim length.
   *
   * @param string $html
   *   The HTML content.
   * @param int $trim_length
   *   The max length before trimming.
   *
   * @return string
   *   Cleaned HTML.
   */
  protected function cleanHtml(string $html, int $trim_length): string {
    // Strip tags but preserve basic formatting.
    $allowed_tags = ['p',
      'br',
      'strong',
      'b',
      'em',
      'ul',
      'ol',
      'li',
    ];
    $cleaned = uiowa_core_html_cleanup($html, $allowed_tags);

    // Use smart_trim's helper to safely truncate without breaking HTML.
    $truncate = new TruncateHTML();
    $cleaned = $truncate->truncateChars($cleaned, $trim_length, '...');

    return $cleaned;
  }

}
