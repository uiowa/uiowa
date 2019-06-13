<?php

namespace Drupal\uiowa_events\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\uiowa_events\EventsInstanceInterface;

/**
 * Defines the event feed entity class.
 *
 * @ContentEntityType(
 *   id = "uievents",
 *   label = @Translation("Event Feed"),
 *   label_collection = @Translation("Event Feeds"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\uiowa_events\EventsInstanceListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\uiowa_events\EventsInstanceAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\uiowa_events\Form\EventsInstanceForm",
 *       "edit" = "Drupal\uiowa_events\Form\EventsInstanceForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "uievents",
 *   data_table = "uievents_field_data",
 *   admin_permission = "administer event feed",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/content/events/add",
 *     "canonical" = "/events/{uievents}",
 *     "edit-form" = "/admin/content/events/{uievents}/edit",
 *     "delete-form" = "/admin/content/events/{uievents}/delete",
 *     "collection" = "/admin/content/events"
 *   },
 *   field_ui_base_route = "entity.uievents.settings"
 * )
 */
class EventsInstance extends ContentEntityBase implements EventsInstanceInterface {

  /**
   * {@inheritdoc}
   *
   * When a new event feed entity is created, set the uid entity reference to
   * the current user as the creator of the entity.
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += ['uid' => \Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Administrative Title'))
      ->setDescription(t('The administrative title of the event feed.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
