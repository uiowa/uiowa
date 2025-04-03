<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageException;
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

    if (!in_array('no_sidebars', array_column($page->get('field_publish_options')
      ->getValue(), 'value'))) {
      // Check if node has menu children.
      $menu_defaults = menu_ui_get_menu_link_defaults($page);
      $menu_children = \Drupal::entityTypeManager()->getStorage('menu_link_content')->loadByProperties(['parent' => $menu_defaults['id']]);

      if (!empty($menu_children)) {
        static::addIssue($issues, 'The page displays a menu and the content may look different after conversion. After you run the conversion, the menu will not display on unpublished revisions of the page.');
      }
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
                static::addIssue($issues, 'Contains cards with no label, which is required for V3. Affected cards will be converted to text areas or images.');
              }
              else {
                // Card body isn't required.
                // Check or set to array with empty value.
                $excerpt = $component->field_card_body?->value;

                // Link isn't required. Check for one, or set to null.
                $link = $component->field_card_link?->first()?->getValue();

                $test_excerpt = $excerpt;
                $test_link = P2LbHelper::extractLink($test_excerpt);
                if (empty($link) || ($test_link && $test_link['uri'] === $link['uri'])) {
                  $excerpt = $test_excerpt;
                }
                if ($excerpt && !static::formattedTextIsSame($excerpt, 'filtered_html', 'minimal_plus')) {
                  static::addIssue($issues, 'Contains cards with content that uses markup not allowed in V3. Affected cards will be converted to text areas.');
                }
              }
              // Add the paragraph cache tags for invalidation.
              $cache_tags = Cache::mergeTags($cache_tags, $component->getCacheTags());
              break;

            case 'carousel':
              /** @var \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $carousel_items_field */
              $carousel_items_field = $component->field_carousel_item;
              $carousel_items = $carousel_items_field->referencedEntities();
              static::addIssue($issues, 'Contains a carousel which has no exact V3 counterpart. The carousel will be converted to an image gallery.');
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

  /**
   * Extract a link from text.
   *
   * @param string|null $text
   *   The text being checked.
   *
   * @return array|null
   *   The link formatted for a field or null.
   */
  public static function extractLink(?string &$text): ?array {
    if (!empty($text)) {
      $dom = Html::load($text);

      $xpath = new \DOMXPath($dom);
      $buttons = $xpath->query("//a[contains(@class, 'bttn')]");
      if ($buttons->length > 0) {
        /** @var \DOMElement $button */
        $button = $buttons[0];
        $button->parentNode->removeChild($button);
        $uri = $button->getAttribute('href');
        $text = Html::serialize($dom);

        return [
          'uri' => (UrlHelper::isExternal($uri)) ? $uri : "internal:$uri",
          'title' => $button->nodeValue,
          'options' => [],
        ];
      }
    }

    return NULL;
  }

  /**
   * Update outdated classes.
   */
  public static function updateOldClasses() {
    $search_replace = [
      'btn btn-primary' => 'bttn bttn--primary',
      'btn btn-secondary' => 'bttn bttn--secondary',
      'btn btn-success' => 'bttn bttn--tertiary',
      'btn btn-info' => 'bttn bttn--tertiary',
      'btn btn-warning' => 'bttn bttn--tertiary',
      'btn btn-danger' => 'bttn bttn--tertiary',
      'btn btn-sm' => 'bttn bttn--primary bttn--small',
      'btn btn-lg' => 'bttn bttn--primary bttn--large',
      'btn btn-block' => 'bttn bttn--primary bttn--full',
      'lead' => 'element--light-intro',
      'alert-success' => 'alert--success',
      'alert-info' => 'alert--info',
      'alert-warning' => 'alert--warning',
      'alert-danger' => 'alert--danger',
      'uids-component--bold-intro' => 'element--bold-intro',
      'uids-component--light-intro' => 'element--light-intro',
      'w-50' => '',
      'w-100' => '',
      'table-striped' => '',
      'table-bordered' => '',
      'align-items-start' => '',
      'align-items-center' => '',
      'align-items-end' => '',
      'align-items-baseline' => '',
      'align-items-stretch' => '',
      'align-content-start' => '',
      'align-content-end' => '',
      'align-content-between' => '',
      'align-content-around' => '',
      'align-content-stretch' => '',
      'align-self-auto' => '',
      'align-self-start' => '',
      'align-self-end' => '',
      'align-self-center' => '',
      'align-self-baseline' => '',
      'align-self-stretch' => '',
      'bg-dark' => '',
      'bg-light' => '',
      'bg-yellow' => '',
      'bg-black' => '',
      'semi-transparent' => '',
      'text-light' => '',
      'col-sm-4' => '',
      'col-sm-6' => '',
      'col-md-3' => '',
      'col-md-4' => '',
      'col-md-6' => '',
      'col-md-12' => '',
      'col-12' => '',
      'col-xl-6' => '',
      'col-xl-3' => '',
      'col-xl-9' => '',
      'col-xl-12' => '',
      'col-md' => '',
      'col' => '',
      'pt-5' => '',
      'py-5' => '',
      'pb-5' => '',
      'order-first' => '',
      'order-last' => '',
      'justify-content-start' => '',
      'justify-content-center' => '',
      'justify-content-end' => '',
      'justify-content-between' => '',
      'justify-content-around' => '',
      'container-fluid' => '',
      'container-sm' => '',
      'container-md' => '',
      'container-lg' => '',
      'container-xl' => '',
      'container' => '',
      'no-gutters' => '',
      'row' => '',
    ];

    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $text_long = $entity_field_manager->getFieldMapByFieldType('text_long');
    $text_with_summary = $entity_field_manager->getFieldMapByFieldType('text_with_summary');
    $entity_field_map = array_merge($text_long, $text_with_summary);

    foreach ($entity_field_map as $entity_type_id => $field_map) {
      $entity_storage = $entity_type_manager->getStorage($entity_type_id);
      if (!($entity_storage instanceof SqlContentEntityStorage)) {
        continue;
      }

      $entity_type = $entity_type_manager->getDefinition($entity_type_id);
      $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
      $table_mapping = $entity_storage->getTableMapping($field_storage_definitions);
      foreach (array_intersect_key($field_storage_definitions, $field_map) as $field_storage_definition) {
        $tables = [];
        $field_name = $field_storage_definition->getName();
        try {
          $tables[] = $table_mapping->getFieldTableName($field_name);
          $value_column = $table_mapping->getFieldColumnName($field_storage_definition, 'value');
        }
        catch (SqlContentEntityStorageException $e) {
          continue;
        }
        // See if the field has a revision table.
        $revision_table = NULL;
        if ($entity_type->isRevisionable() && $field_storage_definition->isRevisionable()) {
          if ($table_mapping->requiresDedicatedTableStorage($field_storage_definition)) {
            $revision_table = $table_mapping->getDedicatedRevisionTableName($field_storage_definition);
          }
          elseif ($table_mapping->allowsSharedTableStorage($field_storage_definition)) {
            $revision_table = $entity_type->getRevisionDataTable() ?: $entity_type->getRevisionTable();
          }
          $tables[] = $revision_table;
        }
        if (!empty($tables)) {
          $db = \Drupal::database();

          foreach ($tables as $table) {
            $update_count = 0;
            $query = $db->select($table, 't')
              ->fields('t');

            // Build a condition group from our search strings.
            $or_group = $query->orConditionGroup();

            foreach ($search_replace as $needle => $string) {
              $or_group->condition($value_column, '%' . $db->escapeLike($needle) . '%', 'LIKE');
            }

            $results = $query->condition($or_group)
              ->execute();

            foreach ($results as $record) {

              // Load the HTML fragment as a DOMDocument object.
              $document = Html::load($record->{$value_column});

              // Get all elements from the HTML fragment.
              $instances = $document->getElementsByTagName('*');

              // A regressive loop (starting with the end) is used to loop
              // through matching elements so that they don't affect other
              // elements when they are updated.
              $i = $instances->length - 1;

              while ($i >= 0) {
                // The current element.
                $dom_element = $instances->item($i);

                $class = $dom_element->getAttribute('class');

                // If <div> element has class='lead',
                // additional conversion from <div> to <p> is needed.
                if ($dom_element->tagName == 'div') {
                  if (str_contains('lead', $class)) {
                    $new_classes = str_replace('lead', 'element--light-intro', $class);
                    $p = $document->createElement('p', $dom_element->nodeValue);
                    $p->setAttribute('class', $new_classes);
                    $dom_element->parentNode->replaceChild($p, $dom_element);
                  }
                }

                foreach ($search_replace as $needle => $replace) {
                  $class = str_replace($needle, $replace, $class);
                }

                if (trim($class)) {
                  $dom_element->setAttribute('class', $class);
                }
                else {
                  $dom_element->removeAttribute('class');
                }

                $i--;
              }

              // Run database update to store updated $value_column.
              $db->update($table)
                ->fields([
                  $value_column => Html::serialize($document),
                ])
                ->condition('entity_id', $record->entity_id)
                ->condition('revision_id', $record->revision_id)
                ->execute();

              $update_count++;
            }

            if ($update_count) {
              \Drupal::messenger()
                ->addMessage('Processed ' . $update_count . ' update(s) for ' . $table . ' table ' . $value_column . ' column.');
            }
          }
        }
      }
    }
  }

}
