<?php

namespace Drupal\uiowa_maui;

/**
 * Representation of a course returned from the MAUI API.
 *
 * @property $id The internal ID of the session.
 * @property $startDate The start date of the session as a Unix timestamp.
 * @property $endDate The end date of the session as a Unix timestamp.
 * @property $shortDescription The name of session, ex. Winter 2020.
 * @property $legacyCode The legacy code of the session. Used in other API calls.
 */
class MauiSession {
  /**
   * The internal ID of the session.
   *
   * @var int
   */
  protected $id;

  /**
   * The start date of the session as a Unix timestamp.
   *
   * @var int
   */
  protected $startDate;

  /**
   * The end date of the session as a Unix timestamp.
   *
   * @var int
   */
  protected $endDate;

  /**
   * The name of session, ex. Winter 2020.
   *
   * @var string
   */
  protected $shortDescription;

  /**
   * The legacy code of the session. Used in other API calls.
   *
   * @var string
   */
  protected $legacyCode;

  /**
   * Construct a course object from API data.
   */
  public function __construct($data) {
    $this->id = $data->id;
    $this->startDate = strtotime($data->startDate);
    $this->endDate = strtotime($data->endDate);
    $this->shortDescription = $data->shortDescription;
    $this->legacyCode = $data->legacyCode;
  }

  /**
   * Return a protected property.
   */
  public function __get($name) {
    return $this->$name;
  }

}
