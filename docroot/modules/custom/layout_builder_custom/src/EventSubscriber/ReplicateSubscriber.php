<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\layout_builder\Field\LayoutSectionItemList;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;
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
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The replicant node's field item list.
   *
   * @var \Drupal\layout_builder\Field\LayoutSectionItemList
   */
  protected $replicantFieldItemList = NULL;

  /**
   * ReplicateSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   UUID.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, UuidInterface $uuid, CurrentPathStack $currentPath, AccountProxyInterface $currentUser, TimeInterface $time) {
    $this->entityTypeManager = $entityTypeManager;
    $this->uuid = $uuid;
    $this->currentPath = $currentPath;
    $this->currentUser = $currentUser;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [];
    if (class_exists('\Drupal\replicate\Events\ReplicatorEvents')) {
      $events[ReplicatorEvents::AFTER_SAVE] = 'onReplicateAfterSave';
    }
    return $events;
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
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $field_item_list */
    $field_item_list = $entity->get(OverridesSectionStorage::FIELD_NAME);
    foreach ($field_item_list->getSections() as $section_delta => $section) {
      $components = $section->getComponents();
      $replicant_components = FALSE;
      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        if ($plugin instanceof ViewsBlock) {
          // Create a copy of the original component, and generate
          // a new uuid. Non-thirdparty settings are protected,
          // so we'll copy it as an array, update it, and create
          // from an array in order to set the unique identifier.
          $new_component = $component->toArray();
          $new_component['uuid'] = $this->uuid->generate();
          $new_component = $component->fromArray($new_component);

          $old_uuid = $component->getUuid();
          // If the view block is the only component,
          // we can just set the new block and remove the old.
          if (count($components) === 1) {
            $section->insertAfterComponent($old_uuid, $new_component);
            $section->removeComponent($old_uuid);
          }
          else {
            // The original entity's weight information is lost
            // during replication, so if the component was not on its own,
            // we need to re-load it to retrieve this information.
            if (!$replicant_components) {
              $replicant_components = $this->getSortedReplicantSectionComponents($section_delta);
            }
            // Find the delta of the component in our sorted copy of the
            // original entity's components array, to see in which place
            // it should sit. Since we know the old uuid is in there,
            // we can do a keys, flip, direct index instead of a full search.
            $index = array_flip(array_keys($replicant_components))[$old_uuid];
            // If it's the first component, then insert the new
            // and remove the old, similar to if it was alone.
            if ($index === 0) {
              $section->insertAfterComponent($old_uuid, $new_component);
              $section->removeComponent($old_uuid);
            }
            else {
              // Remove the original component.
              $section->removeComponent($old_uuid);
              // Get the uuid of the component at the adjusted index.
              $uuid = array_keys($components)[$index];
              // Add the new component to the section, directly after
              // the existing component so that it will be in the right order.
              $section->insertAfterComponent($uuid, $new_component);
            }
          }
        }
      }
    }
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
    $replicant = $this->getClonedNid();
    if (!empty($replicant)) {
      $message = 'Replicated <a href="/node/' . $replicant . '">node/' . $replicant . '</a>.';
    }
    else {
      $message = 'Replicated a node.';
    }
    $entity->setRevisionLogMessage($message);
    $entity->setRevisionUserId($this->currentUser->id());
    $entity->setRevisionCreationTime($this->time->getRequestTime());
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
    if ($entity->getEntityTypeId() === 'node') {
      $node_storage_manager = $this->entityTypeManager->getStorage('node');
      $vids = $node_storage_manager->revisionIds($entity);
      $current = $entity->getRevisionId();
      foreach ($vids as $vid) {
        // Skip deleting if it is the current revision.
        if ((int) $vid === (int) $current) {
          continue;
        }
        $node_storage_manager->deleteRevision($vid);
      }
    }
  }

  /**
   * Fetch the replicant node's field item list.
   *
   * @return \Drupal\layout_builder\Field\LayoutSectionItemList|false
   *   The replicant's section list or false if it could not be retrieved.
   */
  protected function getReplicantFieldItemList(): bool|LayoutSectionItemList {
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $replicant_field_item_list */
    $replicant_field_item_list = $this->entityTypeManager
      ->getStorage('node')
      ?->load($this->getClonedNid())
      ?->get(OverridesSectionStorage::FIELD_NAME);
    return $replicant_field_item_list ?? FALSE;
  }

  /**
   * Get a replicant's section's components, sorted by their designated weight.
   *
   * @param int $section_delta
   *   The specific section to retrieve.
   *
   * @return array|SectionComponent[]
   *   A sorted array of the section's components.
   */
  protected function getSortedReplicantSectionComponents(int $section_delta): array {
    if (is_null($this->replicantFieldItemList)) {
      $this->replicantFieldItemList = $this->getReplicantFieldItemList();
    }
    if ($this->replicantFieldItemList === FALSE) {
      return [];
    }
    $replicant_components = $this->replicantFieldItemList
      ?->getSection($section_delta)
      ?->getComponents();
    if (is_array($replicant_components)) {
      // Sort the components array by the components' weights
      // so that we can use it for proper ordering.
      uasort($replicant_components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });
    }
    return $replicant_components ?? [];
  }

}
