<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A BuildUI API client interface.
 */
interface BuildUiApiClientInterface extends ApiClientInterface {

  /**
   * Returns a list of project related to the building.
   *
   * @param $building_number
   *   The building number
   *
   * @return false|mixed
   *   The data or false.
   */
  public function getProjectsByBuilding(string $building_number): mixed;

  /**
   * Get all featured projects.
   *
   * @return array|false
   *   The featured projects object.
   */
  public function getFeaturedProjects(): array|bool;

  /**
   * Get all capital projects.
   *
   * @return array|false
   *   The capital projects object.
   */
  public function getCapitalProjects(): array|bool;

  /**
   * Return details about a project.
   *
   * @param string $project_id
   *   The project ID.
   *
   * @return false|mixed
   *   The project info or false.
   */
  public function getProjectInfo(string $project_id): mixed;

}
