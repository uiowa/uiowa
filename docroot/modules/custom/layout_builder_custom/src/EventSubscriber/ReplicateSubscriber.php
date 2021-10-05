<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
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
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   UUID.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UuidInterface $uuid) {
    $this->entityTypeManager = $entityTypeManager;
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
        $this->checkForUnhandled($translation);
        $translation->save();
      }
    }
    else {
      $this->checkForUnhandled($entity);
      $entity->save();
    }
  }

  /**
   * Check for unhandled instances, like paragraphs.
   *
   * @param FieldableEntityInterface $entity
   */
  protected function checkForUnhandled(FieldableEntityInterface $entity) {
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
        // @todo Check if it's a block that has paragraphs,
        //   and if so, create a new copy of the paragraph(s).
      }
    }
  }
}
