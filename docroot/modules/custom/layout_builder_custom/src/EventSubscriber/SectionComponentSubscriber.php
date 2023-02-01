<?php

namespace Drupal\layout_builder_custom\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
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
        case 'inline_block:uiowa_image':
          if (isset($build['#attributes']['class'])) {
            if ($build['#plugin_id'] === 'inline_block:uiowa_card') {
              // Map the layout builder styles to the view mode to be used.
              $media_formats = [
                'media--circle' => 'large__square',
                'media--square' => 'large__square',
                'media--ultrawide' => 'large__ultrawide',
                'media--widescreen' => 'large__widescreen',
              ];
            }
            if ($build['#plugin_id'] === 'inline_block:uiowa_image') {
              // Map the layout builder styles to the view mode to be used.
              $media_formats = [
                'media--circle' => 'full__square',
                'media--square' => 'full__square',
                'media--ultrawide' => 'full__ultrawide',
                'media--widescreen' => 'full__widescreen',
                'media--no-crop' => 'full__no_crop',
              ];
            }
          }

          if (isset($media_formats)) {
            // Loop through the map to check if any of them are being used and
            // adjust the view mode accordingly.
            foreach ($media_formats as $style => $view_mode) {
              if (in_array($style, $build['#attributes']['class'])) {
                // Change the view mode to match the format.
                $build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#view_mode'] = $view_mode;
                // Important: Delete the cache keys to prevent this from being
                // applied to all the instances of the same image.
                if (isset($build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#cache']) && isset($build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#cache']['keys'])) {
                  unset($build['content']['field_' . $build['#derivative_plugin_id'] . '_image'][0]['#cache']['keys']);
                }

                // We only want this to execute once.
                break;
              }
            }
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
      }
    }

    $event->setBuild($build);
  }

}
