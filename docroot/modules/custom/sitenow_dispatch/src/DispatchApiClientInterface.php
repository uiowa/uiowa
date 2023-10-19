<?php

namespace Drupal\sitenow_dispatch;

/**
 * A Dispatch API client interface.
 */
interface DispatchApiClientInterface {

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCampaigns(): void;

  /**
   * Return a list of campaigns keyed by endpoint.
   */
  public function getCommunications($campaign): void;

  /**
   * Return details about a communication.
   */
  public function getCommunication($id): void;

  /**
   * Return a list of populations keyed by endpoint.
   */
  public function getPopulations(): void;

  /**
   * Return a list of suppression lists keyed by endpoint.
   */
  public function getSuppressionLists(): void;

  /**
   * Return a list of templates keyed by endpoint.
   */
  public function getTemplates(): void;

}
