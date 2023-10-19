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
use Drupal\layout_builder_custom\LayoutBuilderStylesHelper;
use Drupal\uiowa_core\Element\Card;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add an HTML class to blocks that are displaying placeholder text.
 */
class SectionComponentSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   */
  protected ConfigFactoryInterface $configFactory;

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
  public function onBuildRender(SectionComponentBuildRenderArrayEvent $event): void {
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

    if ($block instanceof FieldBlock) {
      $content = NULL;
      switch ($block->getDerivativeId()) {
        case 'node:person:title':
          $contexts = $event->getContexts();
          if (isset($contexts['layout_builder.entity'])) {
            /** @var \Drupal\node\Entity\Node $node */
            if ($node = $contexts['layout_builder.entity']->getContextValue()) {
              $credentials = $node->hasField('field_person_credential') ? $node->field_person_credential->value : NULL;
              if ($credentials) {
                $node->setTitle("{$node->getTitle()}, $credentials");
                $content = $block->build();
              }
            }
          }

          break;

        case 'node:person:field_image':
          // If there is no image, use the empty image.
          if (empty($build)) {
            $content = [
              '#type' => 'image_empty_person',
            ];
            $contexts = $event->getContexts();
            if (isset($contexts['layout_builder.entity'])) {
              /** @var \Drupal\node\Entity\Node $node */
              if ($node = $contexts['layout_builder.entity']->getContextValue()) {
                $content['#alt'] = $node->getTitle();
              }
            }
          }
          break;

      }

      // If an alteration has been made, re-build the block.
      if (!is_null($content)) {
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

    // For cards or other blocks, we are going to programmatically set the
    // view mode for the image field. This is necessary to allow selection
    // of different image formats.
    if (isset($build['#plugin_id'])) {
      switch ($build['#plugin_id']) {
        case 'inline_block:featured_content':
          // Get LB styles from the component.
          // @phpstan-ignore-next-line
          $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
          // Convert the style list into a map that can be used for overriding
          // style defaults later.
          $style_map = LayoutBuilderStylesHelper::getLayoutBuilderStylesMap($selected_styles);
          if (isset($style_map['list_format']) && str_contains($style_map['list_format'], 'grid')) {
            $style_map['card_media_position'] = 'card--stacked';
          }
          // Filter the style map to just classes related to the card.
          $style_map = Card::filterCardStyles($style_map);

          LayoutBuilderStylesHelper::removeStylesFromAttributes($build['#attributes'], $style_map);

          // Pass override styles through to the aggregator items.
          $build['#override_styles'] = $style_map;
          break;

        case 'inline_block:uiowa_card':

          unset($build['content']['#theme']);

          // @phpstan-ignore-next-line
          $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
          // Convert the style list into a map that can be used for overriding
          // style defaults later.
          $style_map = LayoutBuilderStylesHelper::getLayoutBuilderStylesMap($selected_styles);
          // Filter the style map to just classes related to the card.
          $style_map = Card::filterCardStyles($style_map);

          LayoutBuilderStylesHelper::removeStylesFromAttributes($build['#attributes'], $style_map);

          $build['content']['#override_styles'] = $style_map;

          // Map the layout builder styles to the view mode to be used.
          if (count(Element::children($build['content']['field_uiowa_card_image'])) > 0 && isset($style_map['media_format'])) {
            LayoutBuilderStylesHelper::setMediaViewModeFromStyle($build['content']['field_uiowa_card_image'][0], 'large', $style_map['media_format']);
          }
          break;

        case 'inline_block:uiowa_event':
          unset($build['content']['#theme']);

          break;

        case 'inline_block:uiowa_image':
          // @phpstan-ignore-next-line
          $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
          // Convert the style list into a map that can be used for overriding
          // style defaults later.
          $style_map = LayoutBuilderStylesHelper::getLayoutBuilderStylesMap($selected_styles);
          // Map the layout builder styles to the view mode to be used.
          if (count(Element::children($build['content']['field_uiowa_image_image'])) > 0 && isset($style_map['media_format'])) {
            LayoutBuilderStylesHelper::setMediaViewModeFromStyle($build['content']['field_uiowa_image_image'][0], 'full', $style_map['media_format']);
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
        case 'inline_block:uiowa_aggregator':
          // Get LB styles from the component.
          // @phpstan-ignore-next-line
          $selected_styles = $event->getComponent()->get('layout_builder_styles_style');
          // Convert the style list into a map that can be used for overriding
          // style defaults later.
          $style_map = LayoutBuilderStylesHelper::getLayoutBuilderStylesMap($selected_styles);
          // Filter the style map to just classes related to the card.
          $style_map = Card::filterCardStyles($style_map);

          LayoutBuilderStylesHelper::processGridClasses($build['#attributes']);

          LayoutBuilderStylesHelper::removeStylesFromAttributes($build['#attributes'], $style_map);

          // Pass override styles through to the aggregator items.
          $build['#override_styles'] = $style_map;
          break;

      }
    }

    $event->setBuild($build);
  }

}
