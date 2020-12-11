<?php

namespace Drupal\uiowa_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Defines the academic unit entity type.
 *
 * @ConfigEntityType(
 *   id = "uiowa_department",
 *   label = @Translation("Department"),
 *   label_collection = @Translation("Departments"),
 *   label_singular = @Translation("department"),
 *   label_plural = @Translation("departments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count department",
 *     plural = "@count departments",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\uiowa_entities\DepartmentListBuilder",
 *     "form" = {
 *       "add" = "Drupal\uiowa_entities\Form\AcademicUnitForm",
 *       "edit" = "Drupal\uiowa_entities\Form\AcademicUnitForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "department",
 *   admin_permission = "administer uiowa_academic_unit",
 *   links = {
 *     "collection" = "/admin/structure/uiowa-academic-unit",
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
 *     "catalog_url",
 *     "department_id",
 *     "maui_code",
 *     "maui_id",
 *     "organization",
 *     "homepage"
 *   }
 * )
 */
class Department extends ConfigEntityBase implements ConfigEntityInterface {

  /**
   * The department ID.
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
   * The URL for the general catalog page.
   *
   * @var bool
   */
  protected $catalog_url;

  /**
   * The MAUI department ID field value.
   *
   * @var string
   */
  protected $department_id;

  /**
   * The MAUI department code.
   *
   * @var string
   */
  protected $maui_code;

  /**
   * The MAUI ID of department record.
   *
   * @var string
   */
  protected $maui_id;

  /**
   * The MAUI organization ID.
   *
   * @var string
   */
  protected $organization;

  /**
   * A link to the department homepage.
   *
   * @var string
   */
  protected $homepage;

}
