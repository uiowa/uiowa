<?php

namespace Drupal\building\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\building\BuildingInterface;

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
 *     "list_builder" = "Drupal\building\BuildingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\building\Form\BuildingForm",
 *       "edit" = "Drupal\building\Form\BuildingForm",
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
 *     "description"
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
   * The building status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The building description.
   *
   * @var string
   */
  protected $description;

}
