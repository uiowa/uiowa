<?php

namespace Drupal\sitenow_people\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\sitenow_people\PersonTypeInterface;

/**
 * Defines the person type entity type.
 *
 * @ConfigEntityType(
 *   id = "person_type",
 *   label = @Translation("Person Type"),
 *   label_collection = @Translation("Person Types"),
 *   label_singular = @Translation("person type"),
 *   label_plural = @Translation("person types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count person type",
 *     plural = "@count person types",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\sitenow_people\PersonTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\sitenow_people\Form\PersonTypeForm",
 *       "edit" = "Drupal\sitenow_people\Form\PersonTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "person_type",
 *   admin_permission = "administer person_type",
 *   links = {
 *     "collection" = "/admin/structure/person-type",
 *     "add-form" = "/admin/structure/person-type/add",
 *     "edit-form" = "/admin/structure/person-type/{person_type}",
 *     "delete-form" = "/admin/structure/person-type/{person_type}/delete"
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
 *     "allowed_fields"
 *   }
 * )
 */
class PersonType extends ConfigEntityBase implements PersonTypeInterface {

  /**
   * The person type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The person type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The person type status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The person_type description.
   *
   * @var string
   */
  protected $description;

  /**
   * A list of person fields to show for this type.
   *
   * @var array
   */
  protected $allowed_fields;

  /**
   * {@inheritdoc}
   */
  public function getAllowedFields() {
    return isset($this->allowed_fields) ? $this->allowed_fields : [];
  }

}
