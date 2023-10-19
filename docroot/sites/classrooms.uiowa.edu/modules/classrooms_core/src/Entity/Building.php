<?php

namespace Drupal\classrooms_core\Entity;

use Drupal\classrooms_core\BuildingInterface;
use Drupal\Core\Config\Entity\ConfigEntityBase;

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
   */
  protected string $id;

  /**
   * The building label.
   */
  protected string $label;

  /**
   * The building description.
   */
  protected string $description;

  /**
   * The building number.
   */
  protected string $number;

}
