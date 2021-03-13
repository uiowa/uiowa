<?php

namespace Drupal\admissions_migrate\Plugin\migrate\process;

use Drupal\migrate\Annotation\MigrateProcessPlugin;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
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

  private function createParagraphItem($item, $row) {
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

    /** @var Paragraph $paragraph */
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
