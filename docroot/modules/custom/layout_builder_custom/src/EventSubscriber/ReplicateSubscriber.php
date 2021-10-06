<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\replicate\Events\ReplicatorEvents;
use Drupal\views\Plugin\Block\ViewsBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters replication events.
 */
class ReplicateSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * UUID.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * ReplicateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   UUID.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, UuidInterface $uuid) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->uuid = $uuid;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ReplicatorEvents::AFTER_SAVE => 'onReplicateAfterSave',
    ];
  }

  /**
   * React to replicated entity save.
   *
   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   After save event.
   */
  public function onReplicateAfterSave(AfterSaveEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }
    if (!$entity->hasField(OverridesSectionStorage::FIELD_NAME)) {
      return;
    }
    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $translation_language) {
        /** @var \Drupal\Core\Entity\FieldableEntityInterface $translation */
        $translation = $entity->getTranslation($translation_language->getId());
        $this->additionalHandling($translation);
        $translation->save();
      }
    }
    else {
      $this->additionalHandling($entity);
      $entity->save();
    }
  }

  /**
   * Check for unhandled instances, like paragraphs and views blocks.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The replicated entity.
   */
  protected function additionalHandling(FieldableEntityInterface $entity) {
    $fields_to_check = $this->getReferenceFields();
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $field_item_list */
    $field_item_list = $entity->get(OverridesSectionStorage::FIELD_NAME);
    foreach ($field_item_list as $field_item) {
      foreach ($field_item->section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof ViewsBlock) {
          // Create a copy of the original component, and generate
          // a new uuid.
          $new_component = clone $component;
          $new_component->set('uuid', $this->uuid->generate());
          // Remove the original component.
          $field_item->section->removeComponent($component->getUuid());
          // Add the new component to the section.
          $field_item->section->appendComponent($new_component);
        }
        if (empty($plugin->getConfiguration()['block_revision_id'])) {
          continue;
        }
        // Check if we are either a collection or slider,
        // which are our blocks which contain paragraphs.
        if ($plugin instanceof InlineBlock && in_array($plugin->getDerivativeId(), array_keys($fields_to_check))) {
          $field_names = $fields_to_check[$plugin->getDerivativeId()];
          // Parse the component and load the
          // referenced block by its specific revision.
          $component_array = $component->toArray();
          $configuration = $component_array['configuration'];
          $referenced_entity = $this->entityTypeManager
            ->getStorage('block_content')
            ->loadRevision($configuration['block_revision_id']);
          // Create a duplicate of each of its referenced paragraphs.
          foreach ($field_names as $field_name) {
            foreach ($referenced_entity->$field_name as $field) {
              $field->entity = $field->entity->createDuplicate();
            }
          }
          // Save the block with its updated references.
          $referenced_entity->save();
        }
      }
    }
  }

  /**
   * Fetch the blocks and fields we'll need to check.
   *
   * @return array
   *   Associative array of bundle => fields we should check.
   */
  protected function getReferenceFields() {
    $map = $this->entityFieldManager->getFieldMapByFieldType('entity_reference_revisions');
    $fields = [];
    foreach ($map['block_content'] as $field => $details) {
      foreach ($details['bundles'] as $bundle) {
        $fields[$bundle][] = $field;
      }
    }
    return $fields;
  }

}
