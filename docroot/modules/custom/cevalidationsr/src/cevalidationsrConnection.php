<?php

namespace Drupal\cevalidationsr;

use Drupal\Core\Url;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

if (!defined('CURL_SSLVERSION_TLSv1_2')) {
  define('CURL_SSLVERSION_TLSv1_2', 6); // 6 = TLS 1.2
}

/**
 * Class cevalidationsrConnection
 *
 * @package Drupal\cevalidationsr
 */
class cevalidationsrConnection
{

  /**
   * @var string API querying method
   */
  protected $method  = 'GET';

  /**
   * @var \Drupal\Core\Config\Config cevalidationsr settings
   */
  protected $config  = NULL;

  /**
   * @var array Store sensitive API info such as the private_key & password
   */
  protected $sensitiveConfig = [];

  /**
   * cevalidationsrConnection constructor
   */
  public function __construct()
  {
    $this->config = \Drupal::config('cevalidationsr.settings');
  }

  /**
   * Get configuration or state setting for this integration module.
   *
   * @param string $name this module's config or state.
   *
   * @return string
   */
  protected function getConfig($name)
  {
    return $this->config->get('cevalidationsr.' . $name);
  }

  protected function neutralReponse($neutralresponseemail)
  {
    return "<div class='well'><ul>" .
      "<li>We cannot validate the Credential at this time.</li>" .
      "<li>The information provided does not match the information on record, or there was a connection error.</li>" .
      "<li>Please contact <a href='mailto:" . $neutralresponseemail . "?subject=CeDiploma Information Request' data-rel='external' target='_blank'>" . $neutralresponseemail . "</a> for assistance. When you do, please provide the student name and CeDiD.</li>" .
      "</ul></div>";
  }
  /**
   * Query the cevalidationsr API for data
   *
   * @return object
   */
  public function queryEndpoint()
  {

    // Get Neutral Response Email from configuration
    $neutralresponseemail = $this->getConfig('neutralresponseemail');

    // Set default and init parameters
    $output['result_table'] = "";
    $output['successfail_result'] = $this->neutralReponse($neutralresponseemail);
    $output['scholarrecord_result'] = "";

    try {
      $result = $this->callEndpoint();
      if ($result->getStatusCode() === 200) {
        $json = (string)$result->getBody()->getContents();
        $item = json_decode($json);
        if ($item[0]->ValidStatus === "VALID") {
          $utcDateTime = gmdate("Y-m-d H:i:s");
          $schoolName = $item[0]->SchoolName == "" ? "" :"<tr><td>" . "<b>School:</b>" . "</td><td>" . $item[0]->SchoolName . "</td></tr>";
          $degree = $item[0]->Degree1 == "" ? "" : $item[0]->Degree1 . "<br />";
          $major = $item[0]->Major1 == "" ? "" : "<tr><td>" . "&nbsp;" . "</td><td>" . $item[0]->Major1 . "</td></tr>";
          $honor = $item[0]->Honor1 == "" ? "" : "<tr><td>" . "&nbsp;" . "</td><td>" . $item[0]->Honor1 . "</td></tr>";
          $credential = $this->replaceLast("<br />", "", $degree . $major . $honor);
          $hostedvalidationurl = $item[0]->HostedValidationUrl == ""? "": $item[0]->HostedValidationUrl;
          $tbody = "<tbody>" .
                  "<tr><td style='width:22%'>" . "<b>CeDiD:</b>" . "</td><td style='width:78%'>" . $item[0]->CeDiplomaID . "</td></tr>" .
                  $schoolName .
                  "<tr><td>" . "<b>Name:</b>" . "</td><td>" . $item[0]->Name . "</td></tr>" .
                  "<tr><td>" . "<b>Date:</b>" . "</td><td>" . $item[0]->ConferralDate . "</td></tr>" .
                  "<tr><td>" . "<b>Credential:</b>" . "</td><td>" . $credential . "</td></tr>" .
                  "</tbody>"
          ;
          $tbodyHtml = preg_replace('/\s+/', ' ', $tbody);
          $output['result_table'] = $tbodyHtml;
          $output['successfail_result'] = "<br /><b>This is a Valid Credential</b><br />Validated: " . $utcDateTime;

          if ($hostedvalidationurl != "") {
            $output['scholarrecord_result'] = "<a class='btn btn-success btn-lg' href='" .
            $hostedvalidationurl .
                "' target='_blank'><b>Scholar</b>Record</a>" .
                "<br />" .
                "<small>By selecting ScholarRecord™, you will be taken to CeCredential Trust, a trusted partner of the University, to provide you with more detail of the learner's credential." .
                "<br /><br />";
          } else {
              $output['scholarrecord_result'] = "";
              //$output->scholarrecord_result = "";
          }

        }
      }
      return json_encode($output, JSON_UNESCAPED_SLASHES); //JSON_UNESCAPED_SLASHES Available since PHP 5.4
    } catch (\Exception $e) {
      $logger = \Drupal::logger('update');
      return json_encode($output, JSON_UNESCAPED_SLASHES); //JSON_UNESCAPED_SLASHES Available since PHP 5.4
    }
  }

  /**
   * Call the cevalidationsr API endpoint using GuzzleClient.
   *
   * @return \Psr\Http\Message\ResponseInterface
   */
  public function callEndpoint()
  {
    $headers = [
      'Accept' => 'application/json',
      'Content-type' => 'application/json'
    ];

    $endpoint = $this->getConfig('url');
    $url     = $this->requestUrl($endpoint)->toString();
    $client  = new GuzzleClient([
      'defaults' => [
        'config' => [
          'curl' => [
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2
          ]
        ]
      ]
    ]);
    $request = new GuzzleRequest($this->method, $url, $headers);
    return $client->send($request, ['timeout' => 30]);
  }

  /**
   * Build a Url object of the URL data to query the cevalidationsr API.
   *
   * @param string $endpoint to the API data
   *
   * @return \Drupal\Core\Url
   */
  protected function requestUrl($endpoint)
  {
    $request_uri = $this->requestUri($endpoint);
    return Url::fromUri($endpoint . $request_uri);
  }

  /**
   * Build the URI part of the URL based on the endpoint and configuration.
   *
   * @param string $endpoint to the API data
   *
   * @return string
   */
  protected function requestUri($endpoint)
  {
    $clientid = $this->getConfig('clientid');
    $cedid = \Drupal::state()->get('cevalidationsr.' . 'credentialId');
    // $nm2 = \Drupal::state()->get('cevalidationsr.' . 'nameLetter');
    return '/' . $clientid . '/' . $cedid;
  }

  /**
   * utility function to replace last
   *
   * @return object
   */
  protected function replaceLast($search, $replace, $source){
    $pos = strrpos($source, $search);
    if($pos !== false){
        $source = substr_replace($source, $replace, $pos, strlen($search));
    }
    return $source;
  }

}
