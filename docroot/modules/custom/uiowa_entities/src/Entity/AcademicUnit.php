<?php

namespace Drupal\uiowa_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\uiowa_entities\UnitInterface;

/**
 * Defines the academic unit entity type.
 *
 * @ConfigEntityType(
 *   id = "uiowa_academic_unit",
 *   label = @Translation("Academic Unit"),
 *   label_collection = @Translation("Academic Units"),
 *   label_singular = @Translation("academic unit"),
 *   label_plural = @Translation("academic units"),
 *   label_count = @PluralTranslation(
 *     singular = "@count academic unit",
 *     plural = "@count academic units",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\uiowa_entities\UnitListBuilder",
 *     "form" = {
 *       "add" = "Drupal\uiowa_entities\Form\AcademicUnitForm",
 *       "edit" = "Drupal\uiowa_entities\Form\AcademicUnitForm",
 *       "delete" = "Drupal\Core\Entity\EntityAcademicUnitForm"
 *     }
 *   },
 *   config_prefix = "uiowa_academic_unit",
 *   admin_permission = "administer uiowa_academic_unit",
 *   links = {
 *     "collection" = "/admin/structure/uiowa-acadmeic-unit",
 *     "add-form" = "/admin/structure/uiowa-academic-unit/add",
 *     "edit-form" = "/admin/structure/uiowa-academic-unit/{uiowa_academic_unit}",
 *     "delete-form" = "/admin/structure/uiowa-academic-unit/{uiowa_academic_unit}/delete"
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
class AcademicUnit extends ConfigEntityBase implements UnitInterface {

  /**
   * The academic unit ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The academic unit label.
   *
   * @var string
   */
  protected $label;

  /**
   * The academic unit status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The academic unit description.
   *
   * @var string
   */
  protected $description;

  /**
   * A link to the academic unit homepage.
   *
   * @var string
   */
  protected $homepage;

}
