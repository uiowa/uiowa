<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\PreviewFallbackInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add an HTML class to blocks that are displaying placeholder text.
 */
class SectionComponentSubscriber implements EventSubscriberInterface {

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
      if ($block instanceof PreviewFallbackInterface && isset($build['content']) && isset($build['content']['#markup'])) {
        $is_placeholder = (strpos($build['content']['#markup'], 'Placeholder for the ') === 0);

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

        case 'views_block:article_list_block-list_article':
        case 'views_block:events_list_block-card_list':
        case 'views_block:people_list_block-list_card':
          $row_classes = [];
          $build['#attributes']['class'] = array_unique($build['#attributes']['class']);
          foreach ($build['#attributes']['class'] as $key => $style) {
            foreach ([
              'bg',
              'card',
              'media',
              'borderless',
            ] as $check) {
              if (str_starts_with($style, $check)) {
                $row_classes[] = $style;
                // Removes class so that wrapper is not affected.
                // This includes lb preview things like contextual links.
                unset($build['#attributes']['class'][$key]);
              }
            }
          }
          if (isset($block->getConfiguration()['fields'])) {
            $hide_fields = [];
            foreach ($block->getConfiguration()['fields'] as $field_name => $hide_field) {
              if ((int) $hide_field['hide'] === 1) {
                $hide_fields[] = $field_name;
              }
            }

            if (isset($build['content']['view_build']['#rows'][0]['#rows'])) {
              foreach ($build['content']['view_build']['#rows'][0]['#rows'] as &$row) {
                $row['#hide_fields'] = $hide_fields;
                $row['#attributes']['class'] = $row_classes;
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

}
