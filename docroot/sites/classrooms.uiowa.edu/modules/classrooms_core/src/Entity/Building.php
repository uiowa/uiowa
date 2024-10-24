<?php

namespace Drupal\classrooms_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\classrooms_core\BuildingInterface;

/**
 * Defines the building entity type.
 *
 * @ConfigEntityType(
 *   id = "building",
 *   label = @Translation("Building"),
 *   label_collection = @Translation("Buildings"),
 *   label_singular = @Translation("building"),
 *   label_plural = @Translation("buildings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count building",
 *     plural = "@count buildings",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\classrooms_core\BuildingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\classrooms_core\Form\BuildingForm",
 *       "edit" = "Drupal\classrooms_core\Form\BuildingForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "building",
 *   admin_permission = "administer building",
 *   links = {
 *     "collection" = "/admin/structure/building",
 *     "add-form" = "/admin/structure/building/add",
 *     "edit-form" = "/admin/structure/building/{building}",
 *     "delete-form" = "/admin/structure/building/{building}/delete"
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
 *     "number"
 *   }
 * )
 */
class Building extends ConfigEntityBase implements BuildingInterface {

  /**
   * The building ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The building label.
   *
   * @var string
   */
  protected $label;

  /**
   * The building description.
   *
   * @var string
   */
  protected $description;

  /**
   * The building number.
   *
   * @var string
   */
  protected $number;

}
