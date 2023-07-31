<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitenow_pages\Entity\Page;

class P2LbHelper {

  use StringTranslationTrait;
  public static function formattedTextIsEquivalent($text, $format_one, $format_two) {
    return check_markup($text, $format_one) === check_markup($text, $format_two);
  }

  public static function analyzeNode(Page $page) {
    $issues = [];
    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $section_field */
    $section_field = $page->field_page_content_block;
    $sections = $section_field?->referencedEntities();
    if (!is_null($sections)) {
      /**
       * @var int $section_delta
       * @var \Drupal\paragraphs\ParagraphInterface $section
       */
      foreach ($sections as $section_delta => $section) {
        /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $components_field */
        $components_field = $section->field_section_content_block;
        $components = $components_field->referencedEntities();
        /**
         * @var int $component_delta
         * @var \Drupal\paragraphs\ParagraphInterface $component
         */
        foreach ($components as $component_delta => $component) {
          switch ($component->getType()) {
            case 'card':
              // @todo Check if card has a title.
              $label = $component->field_card_title?->value;
              if (!$label) {
                $issues[] = t('Contains a card with no label. Label is required for V3.');
              }
              // Card body isn't required. Check or set to array with empty value.
              $excerpt = $component->field_card_body?->value;
              if ($excerpt && !static::formattedTextIsEquivalent($excerpt, 'filtered_html', 'minimal')) {
                $issues[] = t('Contains a card with a body that uses markup that is not allowed in V3.');
              }
              break;
          }
        }
      }
    }

    return $issues;
  }
}
