<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\views\Plugin\Block\ViewsBlock;
use Drupal\Core\Path\CurrentPathStack;
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
   * Current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * ReplicateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   UUID.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, UuidInterface $uuid, CurrentPathStack $currentPath, AccountProxyInterface $currentUser) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->uuid = $uuid;
    $this->currentPath = $currentPath;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // @todo Replace this with ReplicatorEvents::AFTER_SAVE
      //   after debugging.
      'replicate__after_save' => 'onReplicateAfterSave',
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
        $this->setRevisionInformation($translation);
        $translation->save();
        // Remove old revisions.
        $this->removeOldRevisions($translation);
      }
    }
    else {
      $this->additionalHandling($entity);
      $entity->setNewRevision(TRUE);
      $this->setRevisionInformation($entity);
      $entity->save();
      // Remove old revisions.
      $this->removeOldRevisions($entity);
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
          $old_uuid = $component->getUuid();
          // Add the new component to the section, directly after
          // the existing component so that it will be in the right order.
          $field_item->section->insertAfterComponent($old_uuid, $new_component);
          // Remove the original component.
          $field_item->section->removeComponent($old_uuid);
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

  /**
   * Fetch the cloned entity's id from the route.
   *
   * @return string
   *   The cloned entity's id.
   */
  protected function getClonedNid() {
    $path = $this->currentPath->getPath();
    $parts = explode('/', $path);
    return (isset($parts[2])) ? $parts[2] : '';
  }

  /**
   * Set the revisioning information.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity for which to set revision information.
   */
  protected function setRevisionInformation(FieldableEntityInterface $entity) {
    $entity->setRevisionLogMessage('Replicated node ' . $this->getClonedNid());
    $entity->setRevisionUserId($this->currentUser->id());
    $entity->setRevisionCreationTime($_SERVER['REQUEST_TIME']);
    // @todo Possibly remove in the future?
    // https://www.drupal.org/project/drupal/issues/2769741
    $entity->setRevisionTranslationAffected(TRUE);
  }

  /**
   * Remove old revisions.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity with revisions to remove.
   */
  protected function removeOldRevisions(FieldableEntityInterface $entity) {
    if ($entity->getEntityTypeId() == 'node') {
      $node_storage_manager = $this->entityTypeManager->getStorage('node');
      $vids = $node_storage_manager->revisionIds($entity);
      $current = $entity->getRevisionId();
      foreach ($vids as $vid) {
        // Skip deleting if it is the current revision.
        if ($vid == $current) {
          continue;
        }
        $node_storage_manager->deleteRevision($vid);
      }
    }
  }

}
