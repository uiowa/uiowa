<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitenow_pages\Entity\Page;

class P2LbHelper {

  use StringTranslationTrait;
  public static function formattedTextIsEquivalent($text, $format_one, $format_two) {
    return check_markup($text, $format_one) === check_markup($text, $format_two);
  }

  public static function analyzeNode(Page $page) {
    // Check the cache first.
    $cid = "sitenow_p2lb_node_status:{$page->id()}";
    if ($item = \Drupal::cache()->get($cid)) {
      return $item->data;
    }
    $issues = [];
    // Add the node cache tags for invalidation.
    $cache_tags = $page->getCacheTags();
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
              // Check if card has a title.
              $label = $component->field_card_title?->value;
              if (!$label) {
                $issues[] = t('Contains a card with no label. Label is required for V3.');
              }
              // Card body isn't required. Check or set to array with empty value.
              $excerpt = $component->field_card_body?->value;
              if ($excerpt && !static::formattedTextIsEquivalent($excerpt, 'filtered_html', 'minimal')) {
                $issues[] = t('Contains a card with a body that uses markup that is not allowed in V3.');
              }
              // Add the paragraph cache tags for invalidation.
              $cache_tags = Cache::mergeTags($cache_tags, $component->getCacheTags());
              break;
            case 'carousel':
              /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $carousel_items_field */
              $carousel_items_field = $component->field_carousel_item;
              $carousel_items = $carousel_items_field->referencedEntities();
              foreach ($carousel_items as $carousel_item) {
                // Cases for carousel image ID and caption being set.
                $caption = $carousel_item->field_carousel_image_caption?->value;
                if ($caption) {
                  $issues[] = t('Contains a carousel item with a caption. The caption will not be converted.');
                }
                $html_id = $carousel_item->field_uip_id?->value;
                if ($html_id) {
                  $issues[] = t('Contains a carousel item with an ID. The ID will not be converted.');
                }
                // Add the paragraph cache tags for invalidation.
                $cache_tags = Cache::mergeTags($cache_tags, $component->getCacheTags());
              }
          }
        }
      }
    }

    \Drupal::cache()->set($cid, $issues, Cache::PERMANENT, $page->getCacheTags());

    return $issues;
  }
}
