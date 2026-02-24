<?php

namespace Drupal\grad_thesis_defense;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\uiowa_core\EntityProcessorBase;

/**
 * Process thesis defense data from MAUI API.
 */
class ThesisDefenseProcessor extends EntityProcessorBase {

  /**
   * {@inheritdoc}
   */
  public string $bundle = 'thesis_defense';

  /**
   * {@inheritdoc}
   */
  protected $fieldSyncKey = 'field_thesis_defense_sync_id';

  /**
   * {@inheritdoc}
   */
  protected $apiRecordSyncKey = 'id';

  /**
   * {@inheritdoc}
   */
  protected $skipDelete = TRUE;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $mauiApi;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    parent::__construct();
    $this->mauiApi = \Drupal::service('uiowa_maui.api');
  }

  /**
   * {@inheritdoc}
   */
  protected function getData() {
    // Get the list of university IDs.
    $university_ids = $this->mauiApi->getThesisDefenseIds();

    $data = [];
    foreach ($university_ids as $university_id) {
      // Get thesis defense info for each university ID.
      $defense_info = $this->mauiApi->getThesisDefenseInfo($university_id);

      if (empty($defense_info) || !is_array($defense_info)) {
        continue;
      }

      foreach ($defense_info as $item) {
        // Only process final exams that have thesis data.
        if (($item->examType->value ?? NULL) !== 'FINAL' || !isset($item->spos->thesis)) {
          continue;
        }

        $processed_item = new \stdClass();
        $processed_item->id = $item->id;
        $processed_item->title = substr(strip_tags($item->spos->thesis), 0, 255);
        $processed_item->examTimestamp = $item->examTimestamp;
        $processed_item->examType = $item->examType;

        // Use subprogramKey if present, otherwise fall back to programKey.
        $processed_item->programKey = !empty($item->spos->programOfStudyDTO->subprogramKey)
          ? $item->spos->programOfStudyDTO->subprogramKey
          : ($item->spos->programOfStudyDTO->programKey ?? NULL);

        $data[] = $processed_item;
      }
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(ContentEntityInterface &$entity, $record): bool {
    $changed = FALSE;

    if ($entity->get('field_thesis_defense_sync_id')->isEmpty()) {
      $entity->set('field_thesis_defense_sync_id', $record->id);
      $changed = TRUE;
    }

    if ($entity->get('field_thesis_defense_title')->isEmpty() ||
      $entity->get('field_thesis_defense_title')->value !== $record->title) {
      $entity->set('field_thesis_defense_title', $record->title);
      $changed = TRUE;
    }

    // Set the person's name based on the ID call data since the info call does
    // not return any name data. The first name is set to "Thesis Defense" and
    // the last name is set to the record id to ensure uniqueness.
    // @todo Change to use name from the ID call instead.
    if ($entity->get('field_person_first_name')->isEmpty()) {
      $entity->set('field_person_first_name', 'Thesis Defense');
      $changed = TRUE;
    }

    // @todo Change to use name from the ID call instead.
    if ($entity->get('field_person_last_name')->isEmpty()) {
      $entity->set('field_person_last_name', $record->id);
      $changed = TRUE;
    }

    if (isset($record->programKey)) {
      if ($entity->get('field_grad_program_phd')->isEmpty() ||
        $entity->get('field_grad_program_phd')->value !== $record->programKey) {
        $entity->set('field_grad_program_phd', $record->programKey);
        $changed = TRUE;
      }
    }

    if (isset($record->examTimestamp)) {
      $date = new \DateTime($record->examTimestamp);
      $date->setTimezone(new \DateTimeZone('America/Chicago'));
      $timestamp = $date->getTimestamp();

      $existing_start = $entity->get('field_thesis_defense_date')->value;
      $existing_end = $entity->get('field_thesis_defense_date')->end_value;

      if ($existing_start != $timestamp || $existing_end != $timestamp) {
        $entity->set('field_thesis_defense_date', [
          'value' => $timestamp,
          'end_value' => $timestamp,
        ]);
        $changed = TRUE;
      }
    }

    return $changed;
  }

}
