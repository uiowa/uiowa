<?php

namespace Drupal\uiowa_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\uiowa_entities\UnitInterface;

/**
 * Defines the person type entity type.
 *
 * @ConfigEntityType(
 *   id = "uiowa_college",
 *   label = @Translation("College"),
 *   label_collection = @Translation("Colleges"),
 *   label_singular = @Translation("college"),
 *   label_plural = @Translation("colleges"),
 *   label_count = @PluralTranslation(
 *     singular = "@count college",
 *     plural = "@count colleges",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\uiowa_entities\UnitListBuilder",
 *     "form" = {
 *       "add" = "Drupal\uiowa_entities\Form\CollegeForm",
 *       "edit" = "Drupal\uiowa_entities\Form\CollegeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "uiowa_college",
 *   admin_permission = "administer uiowa_college",
 *   links = {
 *     "collection" = "/admin/structure/uiowa-college",
 *     "add-form" = "/admin/structure/uiowa-college/add",
 *     "edit-form" = "/admin/structure/uiowa-college/{uiowa_college}",
 *     "delete-form" = "/admin/structure/uiowa-college/{uiowa_college}/delete"
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
class College extends ConfigEntityBase implements UnitInterface {

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
