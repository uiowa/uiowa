<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\PreviewFallbackInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Drupal\uiowa_core\Element\Card;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add an HTML class to blocks that are displaying placeholder text.
 */
class SectionComponentSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * BlockComponentRenderArraySubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Access configuration.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[LayoutBuilderEvents::SECTION_COMPONENT_BUILD_RENDER_ARRAY] = [
      'onBuildRender',
      50,
    ];
    return $events;
  }

  /**
   * Builds render arrays for block plugins and sets it on the event.
   *
   * @param \Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent $event
   *   The section component render event.
   */
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event) {
    $block = $event->getPlugin();
    if (!$block instanceof BlockPluginInterface) {
      return;
    }

    $build = $event->getBuild();

    if ($event->inPreview()) {
      if ($block instanceof PreviewFallbackInterface && isset($build['content']['#markup'])) {
        $is_placeholder = (str_starts_with($build['content']['#markup'], 'Placeholder for the '));

        if ($is_placeholder) {
          $build['#attributes']['class'][] = 'layout-builder-block--placeholder';
        }
      }
    }

    // Set an ID for this block if the third-party key is set.
    if ($unique_id = $event->getComponent()->getThirdPartySetting('layout_builder_custom', 'unique_id')) {
      $build['#attributes']['id'] = $unique_id;
    }

    if ($block instanceof FieldBlock && $block->getPluginId() === 'field_block:node:page:title') {

      $contexts = $event->getContexts();
      if (isset($contexts['layout_builder.entity'])) {
        /** @var \Drupal\node\Entity\Node $node */
        if ($node = $contexts['layout_builder.entity']->getContextValue()) {
          $credentials = $node->hasField('field_person_credential') ? $node->field_person_credential->value : NULL;
          if ($credentials) {
            $node->setTitle("{$node->getTitle()}, $credentials");
            $content = $block->build();

            // @todo Remove the duplicate section of code below once the
            //   following issue is resolved:
            //   https://github.com/uiowa/uiowa/issues/4993
            $build = [
              '#theme' => 'block',
              '#configuration' => $block->getConfiguration(),
              '#plugin_id' => $block->getPluginId(),
              '#base_plugin_id' => $block->getBaseId(),
              '#derivative_plugin_id' => $block->getDerivativeId(),
              '#weight' => $event->getComponent()->getWeight(),
              'content' => $content,
            ];
          }
        }
      }
    }

    // For cards or other blocks, we are going to programmatically set the
    // view mode for the image field. This is necessary to allow selection
    // of different image formats.
    if (isset($build['#plugin_id'])) {
      switch ($build['#plugin_id']) {
        case 'inline_block:uiowa_card':
          // Use the card render element.
          $build['#type'] = 'card';
          unset($build['#theme']);

          $build['#attributes']['class'][] = 'block';

          $content = $build['content'];
          $mapping = [
            '#media' => 'field_uiowa_card_image',
            '#subtitle' => 'field_uiowa_card_author',
          ];

          // Map fields to the card parts.
          foreach ($mapping as $prop => $fields) {
            if (!is_array($fields)) {
              $fields = [$fields];
            }
            if (!isset($build[$prop])) {
              $build[$prop] = [];
            }
            foreach ($fields as $field_name) {
              // @todo Refine this to remove fields if they are empty.
              if (isset($content[$field_name]) && count(Element::children($content[$field_name])) > 0) {
                $build[$prop][$field_name] = $content[$field_name];
                unset($content[$field_name]);
              }
            }
          }

          // @todo Capture the parts of the URL. This isn't working with
          //   caching.
          foreach ([
            'url' => 'url',
            'title' => 'link_text',
          ] as $field_link_prop => $link_prop) {
            if (isset($content['field_uiowa_card_link'][0]["#$field_link_prop"])) {
              $build["#$link_prop"] = $content['field_uiowa_card_link'][0]["#$field_link_prop"];
            }
          }
          unset($content['field_uiowa_card_link']);

          // Pull the button display value directly from the block content since
          // the field is hidden.
          if (isset($build['content']['#block_content'])) {
            $link_indicator = $build['content']['#block_content']
              ?->field_uiowa_card_button_display
              ?->value;

            // Check if it is site default.
            // Covering default_content with `Use site default` check.
            // @todo https://github.com/uiowa/uiowa/issues/5416
            // Consider removing additional check if default_content field null is
            // captured or the field is refactored to not repurpose null as option.
            if ($link_indicator === NULL || $link_indicator === 'Use site default') {
              // Set boolean to site default value.
              $link_indicator = \Drupal::config('sitenow_pages.settings')->get('card_link_indicator_display');
            }

            if ($link_indicator === 'Show' || $link_indicator === TRUE) {
              $build['#link_indicator'] = TRUE;
            }
          }

          // Handle the title field.
          if (isset($content['field_uiowa_card_title']) && count(Element::children($content['field_uiowa_card_title'])) > 0) {
            $build['#title'] = $content['field_uiowa_card_title'][0]['#text'];
            $build['#title_heading_size'] = $content['field_uiowa_card_title'][0]['#size'];
            unset($content['field_uiowa_card_title']);
          }

          $build['#content'] = $content;
          unset($build['content']);

          // Map the layout builder styles to the view mode to be used.
          if (!empty($build['#media']) && isset($build['#attributes']['class'])) {
            $this->setMediaViewModeFromStyle($build['#media']['field_uiowa_card_image'], 'large', $build['#attributes']['class']);
          }
          break;

        case 'inline_block:uiowa_image':
          // Map the layout builder styles to the view mode to be used.
          if (isset($build['#attributes']['class'])) {
            $this->setMediaViewModeFromStyle($build['content']['field_uiowa_image_image'], 'full', $build['#attributes']['class']);
          }

          break;

        case 'menu_block:main':
          // @phpstan-ignore-next-line
          $selectedStyles = $event->getComponent()->get('layout_builder_styles_style');
          // Check that horizontal menu is select in LBS.
          if (in_array('block_menu_horizontal', $selectedStyles)) {
            // Attach accessible-menu library.
            $build['#attached']['library'][] = 'uids_base/accessible-menu';
          }
          break;

        case 'inline_block:uiowa_events':
        case 'views_block:article_list_block-list_article':
        case 'views_block:events_list_block-card_list':
        case 'views_block:people_list_block-list_card':
          // If fields can be hidden, build the list.
          $hide_fields = [];

          if (isset($block->getConfiguration()['fields'])) {
            foreach ($block->getConfiguration()['fields'] as $field_name => $hide_field) {
              if ((int) $hide_field['hide'] === 1) {
                $hide_fields[] = $field_name;
              }
            }
          }

          // Get LB styles from the component.
          $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
          // Convert the style list into a map that can be used for overriding
          // style defaults later.
          $style_map = $this->getListBlockStyleMap($selected_styles);
          // Filter the style map to just classes related to the card.
          $style_map = Card::filterCardStyles($style_map);
          // Work-around for stacked card option meaning that
          // card_media_position is not set.
          if (!isset($style_map['card_media_position'])) {
            $style_map['card_media_position'] = '';
          }

          // Check if there are view rows to act upon.
          if (isset($build['content']['view_build']['#rows'][0]['#rows'])) {
            // Loop through the filtered card style map and remove those classes
            // from the block.
            foreach ($style_map as $class) {
              if (FALSE !== $key = array_search($class, $build['#attributes']['class'])) {
                unset($build['#attributes']['class'][$key]);
              }
            }
            // Loop through view rows and set styles to override and hidden
            // fields.
            foreach ($build['content']['view_build']['#rows'][0]['#rows'] as &$row_build) {

              $row_build['#override_styles'] = $style_map;
              $row_build['#hide_fields'] = $hide_fields;
              if (isset($row_build['#cache']['keys'])) {
                unset($row_build['#cache']['keys']);
              }
            }
          }

          break;

      }
    }

    $event->setBuild($build);
  }

  /**
   * Change the media view mode based on the selected format.
   */
  private function setMediaViewModeFromStyle(array &$build, $size, array $classes = []) {
    $media_formats = [
      'media--circle' => 'square',
      'media--square' => 'square',
      'media--ultrawide' => 'ultrawide',
      'media--widescreen' => 'widescreen',
    ];

    // Loop through the map to check if any of them are being used and
    // adjust the view mode accordingly.
    foreach ($media_formats as $style => $shape) {
      $view_mode = "{$size}__$shape";
      if (in_array($style, $classes)) {
        // Change the view mode to match the format.
        $build[0]['#view_mode'] = $view_mode;
        // Important: Delete the cache keys to prevent this from being
        // applied to all the instances of the same image.
        if (isset($build[0]['#cache']['keys'])) {
          unset($build[0]['#cache']['keys']);
        }

        // We only want this to execute once.
        break;
      }
    }
  }

  /**
   * Helper method to provide a key-value map of styles for list blocks.
   *
   * @param array $styles
   *   The styles to provide a map for.
   *
   * @return array
   *   The style map.
   */
  private function getListBlockStyleMap(array $styles): array {
    $style_map = [];
    foreach ($styles as $style_id) {
      // Account for incorrectly configured component configuration which may
      // have a NULL style ID. We cannot pass NULL to the storage handler, or
      // it will throw an exception.
      if (empty($style_id)) {
        continue;
      }
      /** @var \Drupal\layout_builder_styles\LayoutBuilderStyleInterface $style */
      $style = $this
        ?->entityTypeManager
        ?->getStorage('layout_builder_style')
        ?->load($style_id);
      if ($style) {
        $style_map[$style->getGroup()] = implode(' ', \preg_split('(\r\n|\r|\n)', $style->getClasses()));
      }
    }

    return $style_map;
  }

}
