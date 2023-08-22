<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sitenow_pages\Entity\Page;

/**
 * A helper class for P2LB.
 */
class P2LbHelper {

  use StringTranslationTrait;

  /**
   * Compare a string of text using two different formats.
   *
   * @param string $text
   *   The text being tested.
   * @param string $format_one
   *   The first format.
   * @param string $format_two
   *   The second format.
   *
   * @return bool
   *   If the tests match.
   */
  public static function formattedTextIsSame(string $text, string $format_one, string $format_two): bool {
    return check_markup($text, $format_one) == check_markup($text, $format_two);
  }

  /**
   * Analyze a node to identify conversion issues.
   *
   * @param \Drupal\sitenow_pages\Entity\Page $page
   *   The page node being analyzed.
   *
   * @return array
   *   The list of issues.
   */
  public static function analyzeNode(Page $page) {
    // Check the cache first.
    $cid = "sitenow_p2lb_node_status:{$page->id()}";
    if ($item = \Drupal::cache()->get($cid)) {
      return $item->data;
    }
    $issues = [];
    // Add the node cache tags for invalidation.
    $cache_tags = $page->getCacheTags();

    // Check if node has menu children.
    $menu_defaults = menu_ui_get_menu_link_defaults($page);
    $menu_children = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['parent' => $menu_defaults['id']]);

    if (!empty($menu_children)) {
      static::addIssue($issues, 'The page displays a menu and the content may look different after conversion.');
    }

    /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $section_field */
    $section_field = $page->field_page_content_block;
    /** @var \Drupal\paragraphs\ParagraphInterface[] $sections */
    $sections = $section_field?->referencedEntities();
    if (!empty($sections)) {
      foreach ($sections as $section) {
        /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $components_field */
        $components_field = $section->field_section_content_block;
        /** @var \Drupal\paragraphs\ParagraphInterface[] $components */
        $components = $components_field->referencedEntities();

        if (empty($components)) {
          continue;
        }

        // If the section has a background image.
        if (!is_null($section?->field_section_image?->target_id)) {
          if (count($components) > 1 || reset($components)->getType() !== 'text') {
            static::addIssue($issues, 'Section contains a background image and multiple components or a single component that is not a text area. Affected sections will display an image followed by their components.');
          }
        }

        foreach ($components as $component) {
          switch ($component->getType()) {
            case 'card':
              // Check if card has a title.
              $label = $component->field_card_title?->value;
              if (!$label) {
                static::addIssue($issues, 'Contains cards with no label, which is required for V3. Affected cards will be converted to text areas.');
              }
              // Card body isn't required.
              // Check or set to array with empty value.
              $excerpt = $component->field_card_body?->value;
              if ($excerpt && !static::formattedTextIsSame($excerpt, 'filtered_html', 'minimal')) {
                static::addIssue($issues, 'Contains cards with content that uses markup not allowed in V3. Affected cards will be converted to text areas.');
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
                  static::addIssue($issues, 'Contains carousel items with a caption. The caption will not be converted.');
                }
                $html_id = $carousel_item->field_uip_id?->value;
                if ($html_id) {
                  static::addIssue($issues, 'Contains a carousel items with an ID. The ID will not be converted.');
                }
                // Add the paragraph cache tags for invalidation.
                $cache_tags = Cache::mergeTags($cache_tags, $component->getCacheTags());
              }
              break;

          }
        }
      }
    }

    \Drupal::cache()->set($cid, $issues, Cache::PERMANENT, $cache_tags);

    return $issues;
  }

  /**
   * Add an issue to the issues array.
   *
   * @param array $issues
   *   The issues array.
   * @param string $issue
   *   The issue being added.
   */
  protected static function addIssue(array &$issues, string $issue) {
    if (!isset($issues[$issue])) {
      $issues[$issue] = 0;
    }
    $issues[$issue]++;
  }

  /**
   * Helper function to provide a consistent block definition.
   */
  public static function defaultBlockDefinition($type, array $fields): array {
    $block_definition = [
      'type' => $type,
      'langcode' => 'en',
      'reusable' => 0,
      'default_langcode' => 1,
      'status' => 1,
    ];
    return array_merge($block_definition, $fields);
  }

}
