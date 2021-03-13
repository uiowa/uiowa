<?php

namespace Drupal\admissions_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Custom process plugin to create degree paragraph items on AOS nodes.
 *
 * @MigrateProcessPlugin(
 *   id = "create_degrees",
 *   handle_multiples = TRUE
 * )
 */
class CreateDegrees extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraphs = [];

    foreach ($value as $item) {
      $paragraphs[] = $this->createParagraphItem($item, $row);
    }

    return $paragraphs;
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() {
    return TRUE;
  }

  /**
   * Create a paragraph item and return the expected field values.
   *
   * @param array $item
   *   The item to generate the paragraph from.
   * @param \Drupal\migrate\Row $row
   *   The current row.
   *
   * @return array
   *   Array keyed by paragraph target ID and revision ID.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createParagraphItem(array $item, Row $row) {
    if (str_contains($item['value'], '(')) {
      list($label, $abbr) = explode('(', $item['value']);
      $label = trim($label);
      $abbr = rtrim($abbr, ')');
    }
    else {
      $label = $item['value'];

      \Drupal::logger('admissions_migrate')->warning('Cannot split degree on abbreviation for AOS: @aos.', [
        '@aos' => $row->getSourceProperty('title'),
      ]);
    }

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = Paragraph::create([
      'type' => 'degree',
      'field_degree_label' => $label,
      'field_degree_abbreviation' => $abbr ?? NULL,
    ]);

    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
