<?php

namespace Drupal\cevalidationsr;

use Drupal\Core\Url;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

if (!defined('CURL_SSLVERSION_TLSV1_2')) {
  // Define TLS 1.2 if not defined.
  define('CURL_SSLVERSION_TLSV1_2', 6);
}

/**
 * Handles the connection to the cevalidationsr API.
 *
 * This class manages the communication with the cevalidationsr API, including
 * sending requests and processing responses.
 */
class CevalidationsrConnection {

  /**
   * The HTTP method for the API request.
   *
   * @var string
   */
  protected $method = 'GET';

  /**
   * The configuration for the cevalidationsr connection.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config = NULL;

  /**
   * Sensitive configuration data.
   *
   * @var array
   */
  protected $sensitiveConfig = [];

  /**
   * Constructs a new CevalidationsrConnection object.
   */
  public function __construct() {
    $this->config = \Drupal::config('cevalidationsr.settings');
  }

  /**
   * Retrieves a configuration value for this integration module.
   *
   * @param string $name
   *   The name of the configuration setting.
   *
   * @return string
   *   The configuration value.
   */
  protected function getConfig($name) {
    return $this->config->get('cevalidationsr.' . $name);
  }

  /**
   * Generates a neutral response message.
   *
   * @param string $neutralresponseemail
   *   The email address to be included in the response.
   *
   * @return string
   *   The neutral response message.
   */
  protected function neutralResponse($neutralresponseemail) {
    return "<div class='border bg--gray block-padding__all--minimal block-margin__top'><p>" .
      "We cannot validate the Credential at this time.&nbsp;" .
      "The information provided does not match the information on record, or there was a connection error.&nbsp;" .
      "Please contact <a href='mailto:" . $neutralresponseemail . "?subject=CeDiploma Information Request' data-rel='external' target='_blank'>" . $neutralresponseemail . "</a> for assistance. When you do, please provide the student name and CeDiD." .
      "</p></div>";
  }

  /**
   * Queries the cevalidationsr API for data.
   *
   * This method sends a request to the cevalidationsr API and processes the
   * response.
   *
   * @return string
   *   The JSON-encoded API response.
   */
  public function queryEndpoint() {
    // Get Neutral Response Email from configuration.
    $neutralresponseemail = $this->getConfig('neutralresponseemail');

    // Set default and init parameters.
    $output = [
      'result_table' => "",
      'successfail_result' => $this->neutralResponse($neutralresponseemail),
      'scholarrecord_result' => "",
    ];

    try {
      $result = $this->callEndpoint();
      if ($result->getStatusCode() === 200) {
        $json = (string) $result->getBody()->getContents();
        $item = json_decode($json);
        if ($item[0]->ValidStatus === "VALID") {
          $utcDateTime = gmdate("Y-m-d H:i:s");

          // Define table data.
          $tableData = [
            'CeDiD' => $item[0]->CeDiplomaID,
            'Name' => $item[0]->Name,
            'School' => $item[0]->SchoolName,
            'Credential' => $item[0]->Degree1,
            'Distinction' => $item[0]->Honor1,
            'Major' => $item[0]->Major1,
            'Honors' => $item[0]->Option1,
            'Conferral Date' => $item[0]->ConferralDate,
          ];

          // Generate table HTML.
          $tableHtml = "
                <caption class='element-invisible'>Credential Details</caption>
                <tbody>";

          foreach ($tableData as $label => $value) {
            if (!empty($value)) {
              $tableHtml .= "<tr>
                        <th scope='row'>{$label}</th>
                        <td>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</td>
                    </tr>";
            }
          }

          $tableHtml .= "</tbody>";

          // Remove excess whitespace.
          $tableHtml = preg_replace('/\s+/', ' ', $tableHtml);

          $output['result_table'] = $tableHtml;
          $output['successfail_result'] = "<b>This is a Valid Credential</b><br />Validated: " . $utcDateTime;

          $hostedvalidationurl = $item[0]->HostedValidationUrl ?? '';
          if ($hostedvalidationurl != "") {
            $output['scholarrecord_result'] = "<a class='bttn bttn--secondary' href='" . $hostedvalidationurl . "' target='_blank'><b>Scholar</b>Record</a><br /><small>By selecting ScholarRecord™, you will be taken to CeCredential Trust, a trusted partner of the University, to provide you with more detail of the learner's credential.<br /><br />";
          }
        }
      }
      // JSON_UNESCAPED_SLASHES Available since PHP 5.4.
      return json_encode($output, JSON_UNESCAPED_SLASHES);
    }
    catch (\Exception $e) {
      // JSON_UNESCAPED_SLASHES Available since PHP 5.4.
      return json_encode($output, JSON_UNESCAPED_SLASHES);
    }
  }

  /**
   * Calls the cevalidationsr API endpoint using GuzzleClient.
   *
   * This method prepares and sends an HTTP request to the cevalidationsr API
   * endpoint and returns the response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The API response.
   */
  public function callEndpoint() {
    $headers = [
      'Accept' => 'application/json',
      'Content-type' => 'application/json',
    ];

    $endpoint = $this->getConfig('url');
    $url = $this->requestUrl($endpoint)->toString();
    $client = new GuzzleClient([
      'defaults' => [
        'config' => [
          'curl' => [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSV1_2,
          ],
        ],
      ],
    ]);
    $request = new GuzzleRequest($this->method, $url, $headers);
    return $client->send($request, ['timeout' => 30]);
  }

  /**
   * Builds a Url object of the URL data to query the cevalidationsr API.
   *
   * This method constructs a Drupal Url object based on the provided API
   * endpoint and additional URI parameters.
   *
   * @param string $endpoint
   *   The API endpoint.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  protected function requestUrl($endpoint) {
    $request_uri = $this->requestUri($endpoint);
    return Url::fromUri($endpoint . $request_uri);
  }

  /**
   * Builds the URI part of the URL based on the endpoint and configuration.
   *
   * This method constructs the URI part of the URL by appending the client ID
   * and credential ID to the endpoint.
   *
   * @param string $endpoint
   *   The API endpoint.
   *
   * @return string
   *   The URI string.
   */
  protected function requestUri($endpoint) {
    $clientid = $this->getConfig('clientid');
    $cedid = \Drupal::state()->get('cevalidationsr.' . 'credentialId');
    return '/' . $clientid . '/' . $cedid;
  }

  /**
   * Utility function to replace the last occurrence of a string.
   *
   * This method searches for the last occurrence of a string and replaces it
   * with another string within the source string.
   *
   * @param string $search
   *   The string to search for.
   * @param string $replace
   *   The string to replace with.
   * @param string $source
   *   The source string.
   *
   * @return string
   *   The source string with the last occurrence of search replaced with replace.
   */
  protected function replaceLast($search, $replace, $source) {
    $pos = strrpos($source, $search);
    if ($pos !== FALSE) {
      $source = substr_replace($source, $replace, $pos, strlen($search));
    }
    return $source;
  }

}
