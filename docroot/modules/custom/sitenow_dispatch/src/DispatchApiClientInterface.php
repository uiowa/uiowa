<?php

namespace Drupal\sitenow_dispatch;

use Psr\Http\Message\ResponseInterface;

/**
 * A Dispatch API client interface.
 */
interface DispatchApiClientInterface {

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCampaigns();

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCommunications($campaign);

  /**
   * Return details about a communication.
   */
  public function getCommunication($id);

  /**
   * Return a list of populations keyed by endpoint.
   */
  public function getPopulations();

  /**
   * Return a list of suppression lists keyed by endpoint.
   */
  public function getSuppressionLists();

  /**
   * Return a list of templates keyed by endpoint.
   */
  public function getTemplates();

}
