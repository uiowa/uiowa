<?php

namespace Drupal\replicate\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\entity_clone\Event\EntityCloneEvents;
use Drupal\entity_clone\Event\EntityCloneEvent;


/**
 * Class EventCloneSubscriber.
 *
 * @package Drupal\event_clone\EventSubscriber
 */
class EventCloneSubscriber implements EventSubscriberInterface {

  /**
   *   * The entity type manager.
   *   *
   *   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *   * The uuid generator.
   *   *
   *   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   *   * EventCloneSubscriber constructor.
   *   *
   *   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   *   The entity type manager.
   *   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   *   The uuid generator.
   *
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, UuidInterface $uuid) {
    $this->entityTypeManager = $entity_type_manager;
    $this->uuid = $uuid;
  }

  /**
   *   * {@inheritdoc}
   *
   */
  public static function getSubscribedEvents() {
    return [
      EntityCloneEvents::POST_CLONE => 'onPostClone',
    ];
  }

  /**+   * Callback for the replicate after save event.
   *   *
   *   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   *   The event.
   *   *
   *   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   * @throws \Drupal\Core\Entity\EntityStorageException+
   */
  public function onPostClone(EntityCloneEvent $event) {
    $entity = $event->getEntity();
  }

  /**
   *   * Clones layout builder inline block components on entity.
   *   *
   *   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   *   The entity to clone the components for.
   *   *
   *   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   */
  protected function cloneInlineBlocks(FieldableEntityInterface $entity) {
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $field_item_list */
    $field_item_list = $entity->get(OverridesSectionStorage::FIELD_NAME);

    foreach ($field_item_list as $field_item) {
      foreach ($field_item->section->getComponents() as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof InlineBlock) {
          continue;
        }

        if (empty($plugin->getConfiguration()['block_revision_id'])) {
          continue;
        }

        // Create a copy of the original component.
        $new_component = clone $component;
        $new_component->set('uuid', $this->uuid->generate());

        // Remove the original component.
        $field_item->section->removeComponent($component->getUuid());

        // Create a duplicate of the inline block.
        // For now we cannot use the Replicator service.
        // The method "cloneByEntityRevisionId" has to be added.
        $duplicated_block = $this->entityTypeManager->getStorage('block_content')
          ->loadRevision($plugin->getConfiguration()['block_revision_id'])
          ->createDuplicate();
        $duplicated_block->set('langcode', $entity->language()->getId());

        // Add the duplicated block the the new component.
        $configuration = $new_component->get('configuration');
        $configuration['block_serialized'] = serialize($duplicated_block);
        // Make sure that the inline block is added in the usage table.
        // By setting the revision id to NULL.
        $configuration['block_revision_id'] = NULL;
        $new_component->setConfiguration($configuration);

        // Add the new component to the section.
        $field_item->section->appendComponent($new_component);
      }
    }
  }
}

