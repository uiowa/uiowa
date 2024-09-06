<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\smart_trim\TruncateHTML;

/**
 * Extract a summary from a compound text field.
 *
 * A truncation length (number of characters) can be specified:
 * @code
 * field_text:
 *   plugin: extract_summary
 *   source: text
 *   length: 400
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "extract_summary"
 * )
 */
class ExtractSummary extends ProcessPluginBase {

  /**
   * The truncation length to use if summary is constructed.
   *
   * @var int
   */
  protected $length = 400;

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if ($this->configuration['length']) {
      $this->length = $this->configuration['length'];
    };
    // If we have a basic string, proceed directly to the extraction.
    if (is_string($value)) {
      return $this->extractSummaryFromText($value, $this->length);
    }
    // If we have an array, we need to do some extra checking.
    return $this->getSummaryFromTextField($value, $this->length);
  }

  /**
   * Return the summary of a text field.
   *
   * @param array $field
   *   A text field array that includes value, format and summary keys.
   * @param int $length
   *   The desired summary length, if new summaries are to be created.
   *
   * @return string
   *   The summary if set or an extraction of the body value if not.
   */
  public function getSummaryFromTextField(array $field, int $length = 400): string {
    // If the compound field's summary is empty,
    // or simply doesn't have a summary, then extract
    // a summary from the value.
    if (!isset($field['summary']) || empty($field['summary'])) {
      return $this->extractSummaryFromText($field['value'], $length);
    }
    else {
      // We have a summary to use, but depending on the D7 setup,
      // it may have still allowed tags and/or we may want to
      // further truncate it still.
      return $this->extractSummaryFromText($field['summary'], $length);
    }
  }

  /**
   * Extract a plain text summary from a block of text.
   *
   * @param string $output
   *   The text to convert to a trimmed plain text version.
   * @param int $length
   *   The desired summary length.
   *
   * @return string
   *   The plain text string.
   */
  protected function extractSummaryFromText(string $output, int $length = 400) {
    // The following is the processing from
    // Drupal\smart_trim\Plugin\Field\FieldFormatter.
    // Strip caption.
    $output = preg_replace('/<figcaption[^>]*>.*?<\/figcaption>/is', ' ', $output);

    // Strip script.
    $output = preg_replace('/<script[^>]*>.*?<\/script>/is', ' ', $output);

    // Strip style.
    $output = preg_replace('/<style[^>]*>.*?<\/style>/is', ' ', $output);

    // Strip tags.
    $output = strip_tags($output);

    // Strip out line breaks.
    $output = preg_replace('/\n|\r|\t/m', ' ', $output);

    // Strip out non-breaking spaces.
    $output = str_replace('&nbsp;', ' ', $output);
    $output = str_replace("\xc2\xa0", ' ', $output);

    // Catch and convert remaining html entities.
    $output = html_entity_decode($output);

    // Strip out extra spaces.
    $output = trim(preg_replace('/\s\s+/', ' ', $output));

    $truncate = new TruncateHTML();

    // Truncate to 400 characters with an ellipses.
    $output = $truncate->truncateChars($output, $length, '...');

    return $output;
  }

}
