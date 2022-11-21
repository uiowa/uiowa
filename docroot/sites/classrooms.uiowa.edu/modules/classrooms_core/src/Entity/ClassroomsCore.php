<?php

namespace Drupal\classrooms_core\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\classrooms_core\ClassroomsCoreInterface;

/**
 * Defines the classrooms core entity type.
 *
 * @ConfigEntityType(
 *   id = "classrooms_core",
 *   label = @Translation("Classrooms core"),
 *   label_collection = @Translation("Classrooms cores"),
 *   label_singular = @Translation("classrooms core"),
 *   label_plural = @Translation("classrooms cores"),
 *   label_count = @PluralTranslation(
 *     singular = "@count classrooms core",
 *     plural = "@count classrooms cores",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\classrooms_core\ClassroomsCoreListBuilder",
 *     "form" = {
 *       "add" = "Drupal\classrooms_core\Form\ClassroomsCoreForm",
 *       "edit" = "Drupal\classrooms_core\Form\ClassroomsCoreForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "classrooms_core",
 *   admin_permission = "administer classrooms_core",
 *   links = {
 *     "collection" = "/admin/structure/classrooms-core",
 *     "add-form" = "/admin/structure/classrooms-core/add",
 *     "edit-form" = "/admin/structure/classrooms-core/{classrooms_core}",
 *     "delete-form" = "/admin/structure/classrooms-core/{classrooms_core}/delete"
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
class ClassroomsCore extends ConfigEntityBase implements ClassroomsCoreInterface {

  /**
   * The classrooms core ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The classrooms core label.
   *
   * @var string
   */
  protected $label;

  /**
   * The classrooms core status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The classrooms_core description.
   *
   * @var string
   */
  protected $description;

}
