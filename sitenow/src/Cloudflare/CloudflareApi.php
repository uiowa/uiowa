<?php

namespace SiteNow\Cloudflare;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * Reads and writes the Cloudflare zones and DNS records SiteNow sites use.
 */
class CloudflareApi {

  /**
   * The HTTP client, authenticated against the Cloudflare API.
   */
  private Client $http;

  /**
   * Every zone the token can see, keyed by name. Loaded once.
   *
   * @var array|null
   */
  private ?array $zones = NULL;

  /**
   * Constructs the client wrapper.
   *
   * @param string $token
   *   A Cloudflare API token.
   */
  public function __construct(string $token) {
    $this->http = new Client([
      'base_uri' => 'https://api.cloudflare.com/client/v4/',
      'headers' => ['Authorization' => "Bearer {$token}"],
      'timeout' => 30,
    ]);
  }

  /**
   * The zone a host belongs to: the longest zone name the host ends with.
   *
   * @param string $host
   *   A hostname, e.g. "foo.sites.uiowa.edu".
   *
   * @return array|null
   *   The zone, or NULL when no zone the token can see contains the host.
   */
  public function zoneFor(string $host): ?array {
    $this->zones ??= array_column($this->getAll('zones'), NULL, 'name');

    $matches = array_filter(
      array_keys($this->zones),
      fn($zone) => $host === $zone || str_ends_with($host, ".{$zone}")
    );
    if (!$matches) {
      return NULL;
    }

    usort($matches, fn($a, $b) => strlen($b) <=> strlen($a));
    return $this->zones[$matches[0]];
  }

  /**
   * The DNS records with a given name.
   *
   * @param string $zoneId
   *   The zone ID.
   * @param string $name
   *   The record name, e.g. "foo.sites.uiowa.edu".
   *
   * @return array
   *   The matching records, of any type.
   */
  public function records(string $zoneId, string $name): array {
    return $this->getAll("zones/{$zoneId}/dns_records", ['name' => $name]);
  }

  /**
   * Turn on proxying for a DNS record.
   *
   * @param string $zoneId
   *   The zone ID.
   * @param string $recordId
   *   The record ID.
   */
  public function proxy(string $zoneId, string $recordId): void {
    $this->request('PATCH', "zones/{$zoneId}/dns_records/{$recordId}", ['json' => ['proxied' => TRUE]]);
  }

  /**
   * The status of the edge certificate covering a host.
   *
   * @param string $zoneId
   *   The zone ID.
   * @param string $host
   *   The hostname.
   *
   * @return string|null
   *   The certificate pack status, e.g. "active" or "pending_validation", or
   *   NULL when no pack covers the host.
   */
  public function certificateStatus(string $zoneId, string $host): ?string {
    $wildcard = '*.' . substr($host, strpos($host, '.') + 1);

    foreach ($this->getAll("zones/{$zoneId}/ssl/certificate_packs", ['status' => 'all']) as $pack) {
      $hosts = $pack['hosts'] ?? [];
      if (in_array($host, $hosts, TRUE) || in_array($wildcard, $hosts, TRUE)) {
        return $pack['status'];
      }
    }

    return NULL;
  }

  /**
   * The IP ranges Cloudflare serves proxied traffic from.
   *
   * @return string[]
   *   IPv4 and IPv6 CIDR ranges.
   */
  public function edgeRanges(): array {
    $ips = $this->request('GET', 'ips')['result'];
    return array_merge($ips['ipv4_cidrs'], $ips['ipv6_cidrs']);
  }

  /**
   * Fetch every page of a list endpoint.
   *
   * @param string $path
   *   The endpoint, relative to the API root.
   * @param array $query
   *   Query parameters.
   *
   * @return array
   *   The results from all pages.
   */
  private function getAll(string $path, array $query = []): array {
    $results = [];
    $page = 1;

    do {
      $response = $this->request('GET', $path, ['query' => $query + ['per_page' => 50, 'page' => $page]]);
      $results = array_merge($results, $response['result']);
      $pages = $response['result_info']['total_pages'] ?? 1;
    } while ($page++ < $pages);

    return $results;
  }

  /**
   * Send a request and decode the response.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $path
   *   The endpoint, relative to the API root.
   * @param array $options
   *   Guzzle request options.
   *
   * @return array
   *   The decoded response body.
   *
   * @throws \RuntimeException
   *   If the request fails or Cloudflare reports an error.
   */
  private function request(string $method, string $path, array $options = []): array {
    try {
      $body = (string) $this->http->request($method, $path, $options)->getBody();
    }
    catch (RequestException $e) {
      $body = $e->hasResponse() ? (string) $e->getResponse()->getBody() : '';
      $errors = json_decode($body, TRUE)['errors'] ?? [];
      $message = $errors ? implode('; ', array_column($errors, 'message')) : $e->getMessage();
      throw new \RuntimeException("Cloudflare {$method} {$path} failed: {$message}", 0, $e);
    }
    catch (GuzzleException $e) {
      throw new \RuntimeException("Cloudflare {$method} {$path} failed: {$e->getMessage()}", 0, $e);
    }

    return json_decode($body, TRUE);
  }

}
