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
  protected $skipDelete = FALSE;

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
    // Get the list of university IDs with names.
    $university_data = $this->mauiApi->getThesisDefenseIds();

    $data = [];
    foreach ($university_data as $item) {
      $university_id = $item->universityId;
      $name = $item->name;

      // Get thesis defense info for each university ID.
      $defense_info = $this->mauiApi->getThesisDefenseInfo($university_id);

      if (empty($defense_info) || !is_array($defense_info)) {
        continue;
      }

      foreach ($defense_info as $defense_item) {
        // Only process final exams that have thesis data.
        if (($defense_item->examType->value ?? NULL) !== 'FINAL' || !isset($defense_item->spos->thesis)) {
          continue;
        }

        // Only process items that are marked as scheduled and not "PASSED".
        if (($defense_item->examStatus->value ?? NULL) !== 'SCHEDULED') {
          continue;
        }

        $processed_item = new \stdClass();
        $processed_item->id = $defense_item->id;
        $processed_item->thesis = substr(strip_tags($defense_item->spos->thesis), 0, 255);
        $processed_item->examTimestamp = $defense_item->examTimestamp;
        $processed_item->examType = $defense_item->examType;
        $processed_item->examLocation = $defense_item->examLocation;
        $processed_item->universityId = $university_id;
        $processed_item->name = $name ?? 'Thesis Defense - ' . $defense_item->id;

        // Use subprogram if present, otherwise fall back to program.
        $program = $defense_item->spos->programOfStudyDTO->program ?? NULL;
        $subprogram = $defense_item->spos->programOfStudyDTO->subprogram ?? NULL;
        $processed_item->program = !empty($subprogram) ? $program . ' - ' . $subprogram : $program;

        // Process members for CHAIR and CO_CHAIR.
        $processed_item->members = [];
        if (isset($defense_item->members) && is_array($defense_item->members)) {
          foreach ($defense_item->members as $member) {
            if (isset($member->memberType->value) && in_array($member->memberType->value, ['CHAIR', 'CO_CHAIR'])) {
              $formatted_name = $member->member->name;
              if (!empty($member->memberType->label)) {
                $formatted_name .= ', ' . $member->memberType->label;
              }
              $processed_item->members[] = $formatted_name;
            }
          }
        }

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
      $entity->get('field_thesis_defense_title')->value !== $record->thesis) {
      $entity->set('field_thesis_defense_title', $record->thesis);
      $changed = TRUE;
    }

    if ($entity->get('title')->isEmpty() || $entity->get('title')->value !== $record->name) {
      $entity->set('title', $record->name);
      $changed = TRUE;
    }

    if ($entity->get('field_thesis_defense_location')->isEmpty() || $entity->get('field_thesis_defense_location')->value !== $record->examLocation) {
      $entity->set('field_thesis_defense_location', $record->examLocation);
      $changed = TRUE;
    }

    if (isset($record->program)) {
      if ($entity->get('field_thesis_defense_program')->isEmpty() ||
        $entity->get('field_thesis_defense_program')->value !== $record->program) {
        $entity->set('field_thesis_defense_program', $record->program);
        $changed = TRUE;
      }
    }

    // Process members for chairs field.
    if (isset($record->members) && is_array($record->members)) {
      $existing_chairs = $entity->get('field_thesis_defense_chairs')->getValue();
      $existing_chairs_list = array_column($existing_chairs, 'value');

      // Sort both arrays for comparison.
      sort($record->members);
      sort($existing_chairs_list);

      if ($existing_chairs_list !== $record->members) {
        $entity->set('field_thesis_defense_chairs', $record->members);
        $changed = TRUE;
      }
    }
    else {
      // If no members in record but there are existing chairs, clear them.
      if (!$entity->get('field_thesis_defense_chairs')->isEmpty()) {
        $entity->set('field_thesis_defense_chairs', []);
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
