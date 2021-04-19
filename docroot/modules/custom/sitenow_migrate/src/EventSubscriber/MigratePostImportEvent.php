<?php

namespace Drupal\sitenow_migrate\EventSubscriber;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\Connection;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\sitenow_migrate\Plugin\migrate\source\LinkReplaceTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for post-import migrate event.
 *
 * @package Drupal\sitenow_migrate\EventSubscriber
 */
class MigratePostImportEvent implements EventSubscriberInterface {
  use StringTranslationTrait;
  use LinkReplaceTrait;

  /**
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Indexed array for tracking source nids to destination nids.
   *
   * @var array
   */
  protected $sourceToDestIds;

  /**
   * Array for converting between D7 nids and their associated aliases.
   *
   * @var array
   */
  protected $d7Aliases;

  /**
   * Array for converting between D8 nids and their associated aliases.
   *
   * @var array
   */
  protected $d8Aliases;

  /**
   * Base path of the source website for checking absolute URLs.
   *
   * @var string
   */
  protected $basePath;

  /**
   * MigratePostImportEvent constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The EntityTypeManager service.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection object.
   */
  public function __construct(EntityTypeManager $entityTypeManager, LoggerInterface $logger, Connection $connection) {
    $this->entityTypeManager = $entityTypeManager;
    $this->logger = $logger;
    $this->connection = $connection;

    // Switch to the D7 database.
    // @todo Use shared configuration base URL for this.
    Database::setActiveConnection('drupal_7');
    $connection = Database::getConnection();
    $query = $connection->select('variable', 'v');
    $query->fields('v', ['value'])
      ->condition('v.name', 'file_public_path', '=');
    $result = $query->execute();
    // Switch back to the D8 database.
    Database::setActiveConnection();
    // Get path from public filepath; we don't have the settings file.
    $this->basePath = explode('/', $result->fetchField())[1];
    // If it's a subdomain site, replace '.' with '/'.
    if (substr($this->basePath, 0, 10) == 'uiowa.edu.') {
      substr_replace($this->basePath, '/', 9, 1);
    }
  }

  /**
   * Get subscribed events.
   *
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Calls for additional processing after each migration has completed.
   *
   * {@inheritdoc}
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $source = $event->getMigration()->getSourcePlugin();

    if (method_exists($source, 'postImportProcess')) {
      $source->postImportProcess($event);
    }
  }

}
