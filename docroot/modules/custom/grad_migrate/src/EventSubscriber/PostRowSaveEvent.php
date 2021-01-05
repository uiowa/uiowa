<?php

namespace Drupal\grad_migrate\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for post-row save migrate event.
 *
 * @package Drupal\grad_migrate\EventSubscriber
 */
class PostRowSaveEvent implements EventSubscriberInterface {

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * PostRowSaveEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   */
  public function __construct(EntityTypeManager $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Get subscribed events.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE][] = ['onPostRowSave'];
    return $events;
  }

  /**
   * Calls for additional processing after migration import.
   *
   * {@inheritdoc}
   */
  public function onPostRowSave($event) {
    $migration = $event->getMigration();
    switch ($migration->id()) {

      // Create a redirect to match the old thesis defense paths.
      case 'd7_grad_thesis_defense':
        $nids = $event->getDestinationIdValues();
        $nid = $nids[0];

        /** @var \Drupal\node\NodeInterface $node */
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
        $url_parts = explode('/', $node->toUrl()->toString());
        $suffix = end($url_parts);
        $source_url = 'thesis-defense/' . $suffix;
        $dest_uri = 'internal:/node/' . $nid;
        // @todo Create a MigratePreRollbackEvent to undo this on rollback.
        Redirect::create([
          'redirect_source' => $source_url,
          'redirect_redirect' => $dest_uri,
          'language' => 'en',
          'status_code' => '301',
        ])->save();
    }
  }

}
