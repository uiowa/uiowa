<?php

namespace Drupal\bit\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Bit type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "bit_type",
 *   label = @Translation("Bit type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\bit\Form\BitTypeForm",
 *       "edit" = "Drupal\bit\Form\BitTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\bit\BitTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer bit types",
 *   bundle_of = "bit",
 *   config_prefix = "bit_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/bit_types/add",
 *     "edit-form" = "/admin/structure/bit_types/manage/{bit_type}",
 *     "delete-form" = "/admin/structure/bit_types/manage/{bit_type}/delete",
 *     "collection" = "/admin/structure/bit_types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   }
 * )
 */
class BitType extends ConfigEntityBundleBase {

  /**
   * The machine name of this bit type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the bit type.
   *
   * @var string
   */
  protected $label;

}
