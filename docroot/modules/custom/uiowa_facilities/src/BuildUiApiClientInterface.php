<?php

namespace Drupal\uiowa_facilities;

use Drupal\uiowa_core\ApiClientInterface;

/**
 * A BuildUI API client interface.
 */
interface BuildUiApiClientInterface extends ApiClientInterface {

  /**
   * Return a list of featured projects.
   */
  public function getFeaturedProjects();

  /**
   * Return a list of capital projects.
   */
  public function getCapitalProjects();

  /**
   * Return details about a project.
   */
  public function getProjectInfo($project_id);

}
