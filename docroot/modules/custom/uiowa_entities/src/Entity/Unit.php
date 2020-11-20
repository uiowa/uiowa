<?php

namespace Drupal\uiowa_entities\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\uiowa_entities\UnitInterface;

/**
 * Defines the uiowa academic unit entity type.
 *
 * @ConfigEntityType(
 *   id = "uiowa_unit",
 *   label = @Translation("Academic Unit"),
 *   label_collection = @Translation("Academic Units"),
 *   label_singular = @Translation("academic unit"),
 *   label_plural = @Translation("academic units"),
 *   label_count = @PluralTranslation(
 *     singular = "@count academic unit",
 *     plural = "@count academic units",
 *   ),
 *   config_prefix = "unit.*",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "homepage"
 *   }
 * )
 */
class Unit extends ConfigEntityBase implements UnitInterface {

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
   * A link to the academic unit homepage.
   *
   * @var string
   */
  protected $homepage;

}
