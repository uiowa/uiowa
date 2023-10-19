<?php

namespace Drupal\sitenow_dispatch;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Repository for database-related helper methods for the table.
 *
 * This repository is a service named
 * 'sitenow_dispatch.message_log_repository'.
 *
 * This repository includes basic CRUD behaviors.
 *
 * @ingroup sitenow_dispatch_messages_log
 */
class MessageLogRepository {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * Construct a repository object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(Connection $connection, TranslationInterface $translation, MessengerInterface $messenger) {
    $this->connection = $connection;
    $this->setStringTranslation($translation);
    $this->setMessenger($messenger);
  }

  /**
   * Save an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int
   *   The number of updated rows.
   *
   * @throws \Exception
   *   When the database insert fails.
   */
  public function insert(array $entry): int {
    try {
      $return_value = $this->connection->insert('sitenow_dispatch_messages_log')
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t('Insert failed. Message = %message', [
        '%message' => $e->getMessage(),
      ]), 'error');
    }
    return $return_value ?? NULL;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   */
  public function update(array $entry): int {
    try {
      // Connection->update()...->execute() returns the number of rows updated.
      $count = $this->connection->update('sitenow_dispatch_messages_log')
        ->fields($entry)
        ->condition('lid', $entry['lid'])
        ->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage($this->t('Update failed. Message = %message, query= %query', [
        '%message' => $e->getMessage(),
        '%query' => $e->query_string,
      ]
      ), 'error');
    }
    return $count ?? 0;
  }

  /**
   * Delete an entry from the database.
   *
   * @param array $entry
   *   An array containing at least the 'entity_id' element of the
   *   entry to delete.
   *
   * @see Drupal\Core\Database\Connection::delete()
   */
  public function delete(array $entry): void {
    $this->connection->delete('sitenow_dispatch_messages_log')
      ->condition('entity_id', $entry['entity_id'])
      ->execute();
  }

  /**
   * Read from the database using a filter array.
   *
   * @param array $entry
   *   An array containing all the fields used to search the entries in the
   *   table.
   *
   * @return array
   *   An object containing the loaded entries if found.
   *
   * @see Drupal\Core\Database\Connection::select()
   */
  public function load(array $entry = []): array {
    // Read all the fields from the sitenow_dispatch_messages_log table.
    $select = $this->connection
      ->select('sitenow_dispatch_messages_log')
      // Add all the fields into our select query.
      ->fields('sitenow_dispatch_messages_log');

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in object format.
    return $select->execute()->fetchAll();
  }

}
