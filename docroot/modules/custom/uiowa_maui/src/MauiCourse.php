<?php

namespace Drupal\uiowa_maui;

/**
 * Representation of a course returned from the MAUI API.
 *
 * @property $id The internal ID of the session.
 * @property $shortDescription The name of session, ex. Winter 2020.
 * @property $startDate The start date of the session as a Unix timestamp.
 * @property $endDate The end date of the session as a Unix timestamp.
 * @property $legacyCode The legacy code of the session. Used in other API calls.
 */
class MauiCourse {
  protected $id;
  protected $startDate;
  protected $endDate;
  protected $shortDescription;
  protected $legacyCode;

  public function __construct($data) {
    $this->id = $data->id;
    $this->startDate = strtotime($data->startDate);
    $this->endDate = strtotime($data->endDate);
    $this->shortDescription = $data->shortDescription;
    $this->legacyCode = $data->legacyCode;
  }

  public function __get($name) {
    return $this->$name;
  }
}
