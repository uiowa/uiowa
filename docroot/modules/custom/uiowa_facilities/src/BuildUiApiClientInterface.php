<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A BuildUI API client interface.
 */
interface BuildUiApiClientInterface extends ApiClientInterface {

  /**
   * Get all featured projects.
   *
   * @return array
   *   The featured projects object.
   */
  public function getFeaturedProjects(): array;

  /**
   * Get all capital projects.
   *
   * @return array
   *   The capital projects object.
   */
  public function getCapitalProjects(): array;

  /**
   * Return details about a project.
   *
   * @param $project_id
   *   The project ID.
   *
   * @return false|mixed
   *   The project info or false.
   */
  public function getProjectInfo($project_id): mixed;

}
