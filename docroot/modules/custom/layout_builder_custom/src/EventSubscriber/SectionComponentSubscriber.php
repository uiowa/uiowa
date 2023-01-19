<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
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
            'media' => 'field_uiowa_card_image',
            'subtitle' => 'field_uiowa_card_author',
          ];

          // Map fields to the card parts.
          foreach ($mapping as $prop => $fields) {
            if (!is_array($fields)) {
              $fields = [$fields];
            }
            $build["#$prop"] = [];
            foreach ($fields as $field) {
              // @todo Refine this to remove fields if they are empty.
              if (isset($content[$field]) && count(Element::children($content[$field])) > 0) {
                $build["#$prop"][] = $content[$field];
                unset($content[$field]);
              }
            }
          }

          $link_indicator = $block
            ?->field_uiowa_card_button_display
            ?->value;

          if (!is_null($link_indicator)) {
            $build['#link_indicator'] = (bool) $link_indicator;
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

          // Handle the title field.
          if (isset($content['field_uiowa_card_title']) && count(Element::children($content['field_uiowa_card_title'])) > 0) {
            $build['#title'] = $content['field_uiowa_card_title'][0]['#text'];
            $build['#title_heading_size'] = $content['field_uiowa_card_title'][0]['#size'];
            unset($content['field_uiowa_card_title']);
          }

          $build['#content'] = $content;
          unset($build['content']);

          // Map the layout builder styles to the view mode to be used.
          $media_formats = [
            'media--circle' => 'large__square',
            'media--square' => 'large__square',
            'media--ultrawide' => 'large__ultrawide',
            'media--widescreen' => 'large__widescreen',
          ];
          break;

        case 'inline_block:uiowa_image':
          // Map the layout builder styles to the view mode to be used.
          $media_formats = [
            'media--circle' => 'full__square',
            'media--square' => 'full__square',
            'media--ultrawide' => 'full__ultrawide',
            'media--widescreen' => 'full__widescreen',
          ];
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
              }
            }
          }
      }

      if (isset($media_formats) && isset($build['#attributes']['class'])) {
        // Loop through the map to check if any of them are being used and
        // adjust the view mode accordingly.
        foreach ($media_formats as $style => $view_mode) {
          if (in_array($style, $build['#attributes']['class'])) {
            // Change the view mode to match the format.
            $build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#view_mode'] = $view_mode;
            // Important: Delete the cache keys to prevent this from being
            // applied to all the instances of the same image.
            if (isset($build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#cache']['keys'])) {
              unset($build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#cache']['keys']);
            }

            // We only want this to execute once.
            break;
          }
        }
      }
    }

    $event->setBuild($build);
  }

}
