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

    if ($block instanceof FieldBlock && $block->getPluginId() === 'field_block:node:page:title') {

      $contexts = $event->getContexts();
      if (isset($contexts['layout_builder.entity'])) {
        /** @var \Drupal\node\Entity\Node $node */
        if ($node = $contexts['layout_builder.entity']->getContextValue()) {
          $credentials = $node->hasField('field_person_credential') ? $node->field_person_credential->value : NULL;
          if ($credentials) {
            $node->setTitle("{$node->getTitle()}, $credentials");
            $content = $block->build();

            $build = [
              // @todo Move this to BlockBase in https://www.drupal.org/node/2931040.
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

    // For cards, event, we are going to programmatically set the view mode for
    // the image field. This is necessary to allow selection of different
    // image formats.
    if (isset($build['#derivative_plugin_id'])) {
      switch ($build['#derivative_plugin_id']) {
        case 'uiowa_card':
        case 'uiowa_event':
          if (isset($build['#layout_builder_style'])) {
            // Map the layout builder styles to the view mode to be used.
            $media_formats = [
              'media--widescreen' => 'large__widescreen',
              'media--square' => 'large__square',
              'media--circle' => 'large__square',
            ];

            // Loop through the map to check if any of them are being used and
            // adjust the view mode accordingly.
            foreach ($media_formats as $style => $view_mode) {
              if (in_array($style, $build['#layout_builder_style'])) {
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
      }
    }

    $event->setBuild($build);
  }

}
