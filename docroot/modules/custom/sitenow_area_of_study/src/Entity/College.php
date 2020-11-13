<?php

namespace Drupal\sitenow_area_of_study\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\sitenow_area_of_study\CollegeInterface;

/**
 * Defines the person type entity type.
 *
 * @ConfigEntityType(
 *   id = "area_of_study_college",
 *   label = @Translation("College"),
 *   label_collection = @Translation("Colleges"),
 *   label_singular = @Translation("college"),
 *   label_plural = @Translation("colleges"),
 *   label_count = @PluralTranslation(
 *     singular = "@count college",
 *     plural = "@count colleges",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sitenow_area_of_study\CollegeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sitenow_area_of_study\Form\CollegeForm",
 *       "edit" = "Drupal\sitenow_area_of_study\Form\CollegeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "area_of_study_college",
 *   admin_permission = "administer area_of_study_college",
 *   links = {
 *     "collection" = "/admin/structure/area-of-study-college",
 *     "add-form" = "/admin/structure/area-of-study-college/add",
 *     "edit-form" = "/admin/structure/area-of-study-college/{area_of_study_college}",
 *     "delete-form" = "/admin/structure/area-of-study-college/{area_of_study_college}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "homepage"
 *   }
 * )
 */
class College extends ConfigEntityBase implements CollegeInterface {

  /**
   * The college ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The college label.
   *
   * @var string
   */
  protected $label;

  /**
   * The college status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The college description.
   *
   * @var string
   */
  protected $description;

  /**
   * A link to the college homepage.
   *
   * @var string
   */
  protected $homepage;

}
