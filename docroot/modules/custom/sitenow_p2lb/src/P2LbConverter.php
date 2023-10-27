<?php

namespace Drupal\sitenow_p2lb;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Section;
use Drupal\sitenow_pages\Entity\Page;

/**
 * A class for performing conversions.
 */
class P2LbConverter {

  /**
   * The page being converted.
   *
   * @var \Drupal\sitenow_pages\Entity\Page
   */
  protected Page $page;

  /**
   * Sections that have been converted.
   *
   * @var array
   */
  protected $convertedSections = [];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\sitenow_pages\Entity\Page $page
   *   The page being processed.
   */
  public function __construct(Page $page) {
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
    $this->page = $this->getLatestRevision($page);
  }

  /**
   * Helper to load the most recent revision.
   *
   * @param \Drupal\sitenow_pages\Entity\Page $page
   *   The page being converted.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestRevision(Page $page): Page {
    $node_storage = $this->entityTypeManager
      ->getStorage('node');

    // Get latest revision ID.
    $latest_vid = $node_storage
      ->getLatestRevisionId($page->id());

    // Load latest revision.
    return $node_storage
      ->loadRevision($latest_vid);
  }

  /**
   * Convert a page from V2 to V3.
   */
  public function convert(): void {
    $this->processSections()
      ->processMenu()
      ->addLockedSections()
      ->createNewRevision();

    // Finally, clear the tempstore.
    sitenow_p2lb_clear_tempstore($this->page);
  }

  /**
   * Process all the sections.
   */
  protected function processSections() {
    // Get sections from the page.
    $section_ids = sitenow_p2lb_fetch_child_ids($this->page);

    // Process all the individual sections and update the layout.
    foreach ($section_ids as $section_id) {
      $this->processSection($section_id);
    }

    return $this;
  }

  /**
   * Process a section.
   *
   * @param string|int $revision_id
   *   The section paragraph ID.
   */
  protected function processSection($revision_id) {
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    $paragraph_section = $paragraph_storage->loadRevision($revision_id);

    // Stop processing if there is no section found.
    if (!$paragraph_section) {
      return FALSE;
    }

    $paragraph_section_unique_id = $paragraph_section->field_uip_id->value ?: NULL;

    // Grab section title and styles.
    $section_title = ($paragraph_section->field_section_title) ?
      $paragraph_section->field_section_title->value : '';
    $section_styles = sitenow_p2lb_section_styles($paragraph_section);

    // Get all paragraphs attached to this section.
    $pvids = sitenow_p2lb_fetch_child_ids($paragraph_section);
    $paragraphs = $paragraph_storage->loadMultipleRevisions($pvids);

    // Check for a section image, and if so, handle it.
    $section_image_fid = $paragraph_section->field_section_image?->target_id;
    $banner_text = NULL;

    if ($section_image_fid) {
      // If the first (or only) paragraph is text, use it for the created
      // banner.
      if (!empty($paragraphs) && reset($paragraphs)->getType() === 'text') {
        $banner_text = array_shift($paragraphs);
      }

      // Append our new background image section.
      $section_array = sitenow_p2lb_section_image($section_image_fid, $banner_text, $this->page);
      if (!empty($section_array)) {
        $this->convertedSections[] = Section::fromArray($section_array);
        // If that was the only paragraph, we're done with this p_section.
        if (empty($paragraphs)) {
          return TRUE;
        }
      }
    }

    // Widths are set at the paragraph level. Grab our column widths.
    $col_widths = [];
    foreach ($paragraphs as $paragraph) {
      $col_str = $paragraph->field_uip_colwidth->getString();
      // 'Fluid' paragraph will be a col_width of 0.
      $col_widths[] = (int) preg_replace('|[^0-9]|', '', $col_str);
    }

    $sections_infos = sitenow_p2lb_determine_columns($col_widths);

    // Helpers for iterating through paragraphs later.
    $paragraph_iter = 0;
    $paragraph_keys = array_keys($paragraphs);

    // Helpers for section config settings later.
    $regional = [
      'first',
      'second',
      'third',
      'fourth',
    ];
    $w2d = [
      1 => 'one',
      2 => 'two',
      3 => 'three',
      4 => 'four',
    ];

    // Create and append sections to our $layout (section list).
    foreach ($sections_infos as $delta => $section_info) {
      // Catch broken section infos and put into a 'blank slate' section.
      $num_cols = (isset($section_info['num_columns'])) ? $section_info['num_columns'] : 1;

      // Determine column layout and create the text string.
      $formatter = 'one';
      if (isset($w2d[$num_cols])) {
        $formatter = $w2d[$num_cols];
      }

      $layout_id = "layout_{$formatter}col";

      $col_width_str = sitenow_p2lb_multicol_settings($section_info['col_widths']);
      $layout_settings = [
        'label' => $section_title,
        'column_widths' => $col_width_str,
        'layout_builder_styles_style' => $section_styles,
      ];

      // If this is the first section and there is a unique ID, add it to the
      // layout settings.
      if ($delta === 0 && $paragraph_section_unique_id) {
        $layout_settings['layout_builder_custom_unique_id'] = $paragraph_section_unique_id;
      }

      $section_array = sitenow_p2lb_create_section_array($layout_id, $layout_settings);

      // Iterate through the columns and attach the next paragraph in the list.
      for ($i = 0; $i < $num_cols; $i++) {
        // We might have more columns available than remaining paragraphs.
        if ($paragraph_iter >= count($paragraphs)) {
          break;
        }
        $key = $paragraph_keys[$paragraph_iter++];

        // This block_config will be empty
        // if the paragraph didn't process correctly.
        // And in some cases, like webforms, we will
        // receive multiple block configs.
        $block_configs = sitenow_p2lb_process_paragraph($paragraphs[$key], $this->page);
        foreach ($block_configs as $block_config) {
          // Onecol uses 'content', rest use region for column placement.
          $region = ($num_cols === 1) ? 'content' : $regional[$i];
          $uuid = $block_config['uuid'];
          $config = $block_config['configuration'];
          $styles = $block_config['styles'];

          $section_array['components'][$uuid] = [
            'uuid' => $uuid,
            'region' => $region,
            'configuration' => $config,
            'additional' => [
              'layout_builder_styles_style' => $styles,
            ],
            'weight' => 0,
          ];
          // If we had a user-provided unique id,
          // add it to the block's third-party settings.
          if (!empty($block_config['id'])) {
            $section_array['components'][$uuid]['third_party_settings'] = [
              'layout_builder_custom' => [
                'unique_id' => $block_config['id'],
              ],
            ];
          }
        }
      }
      if (!empty($section_array)) {
        $this->convertedSections[] = Section::fromArray($section_array);
      }
    }

    return TRUE;
  }

  /**
   * Checks if a menu block should display and makes appropriate adjustments.
   */
  protected function processMenu() {
    if (!in_array('no_sidebars', array_column($this->page->get('field_publish_options')
      ->getValue(), 'value'))) {
      // Check if node has menu children.
      $menu_defaults = menu_ui_get_menu_link_defaults($this->page);
      $menu_children = $this->entityTypeManager->getStorage('menu_link_content')->loadByProperties(['parent' => $menu_defaults['id']]);

      if (!empty($menu_children)) {
        // Define a menu block component.
        $menu_block_uuid = \Drupal::service('uuid')->generate();
        $components = [
          $menu_block_uuid => [
            'uuid' => $menu_block_uuid,
            'region' => 'sidebar',
            'configuration' => [
              'id' => 'menu_block:main',
              'label' => 'Main navigation',
              'label_display' => NULL,
              'provider' => 'menu_block',
              'follow' => TRUE,
              'follow_parent' => 'child',
              'level' => 2,
              'depth' => 1,
              'expand_all_items' => FALSE,
              'parent' => 'main:',
              'suggestion' => 'main',
              'label_type' => 'block',
              'label_link' => FALSE,
              'context_mapping' => [],
            ],
            'additional' => [
              'layout_builder_styles_style' => [
                'block_menu_vertical',
              ],
            ],
            'weight' => 0,
          ],
        ];

        // If the next section is a one column_layout, remove it from the
        // converted sections and copy its components to the new section.
        if (isset($this->convertedSections[0]) && $this->convertedSections[0]->getLayoutId() === 'layout_onecol') {
          /** @var \Drupal\layout_builder\Section $first_section */
          $first_section = array_shift($this->convertedSections);
          foreach ($first_section->getComponents() as $uuid => $component) {
            $components[$uuid] = $component->toArray();
          }
        }

        // Create a section with the "Page w/ sidebar" layout.
        $layout_settings = [
          'label' => 'Menu',
          'layout_builder_styles_style' => [
            'section_margin_fixed_width_container',
          ],
        ];
        // Create the section array.
        $section_array = sitenow_p2lb_create_section_array('layout_page', $layout_settings, $components);

        array_unshift($this->convertedSections, Section::fromArray($section_array));
      }
    }

    return $this;
  }

  /**
   * Add default sections that are locked in V3.
   */
  protected function addLockedSections() {
    // Get default page sections config.
    $default_sections = _sitenow_p2lb_get_page_lb_sections_config();

    // Append content moderation and header sections
    // from default config.
    foreach (['content_moderation', 'header'] as $i => $default_section) {
      array_unshift($this->convertedSections, Section::fromArray($default_sections[$i]));
    }

    return $this;
  }

  /**
   * Create a new revision and update the relevant fields and properties.
   */
  protected function createNewRevision(): void {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');

    // Create a new revision from node storage.
    $new_revision = $node_storage->createRevision($this->page);

    // Set the layout section list field to the V3 version.
    $new_revision->set('layout_builder__layout', $this->convertedSections);

    // Unset the paragraphs block.
    $new_revision->set('field_page_content_block', NULL);

    // Set value of field_v3_conversion_revision_id to current revision ID.
    $new_revision->field_v3_conversion_revision_id->value = $this->page->getLoadedRevisionId();

    // Set the new revision as a "Draft".
    $new_revision->set('moderation_state', 'draft');

    // Add a message to the revision log.
    $new_revision->revision_log = 'Converted page to v3.';

    // Set the user ID to the current user's ID for the revision.
    $new_revision->setRevisionUserId(\Drupal::currentUser()->id());

    // Set the relevant revision timestamps.
    $new_revision->setRevisionCreationTime(\Drupal::time()->getRequestTime());
    $new_revision->setChangedTime(\Drupal::time()->getRequestTime());

    // Save the new revision.
    $new_revision->save();
  }

}
