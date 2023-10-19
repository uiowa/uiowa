<?php

namespace Drupal\grad_core\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * SectionComponent overrides for grad_core.
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

    if ($block instanceof FieldBlock) {
      $content = NULL;
      switch ($block->getDerivativeId()) {
        case 'node:mentor:title':
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

    $event->setBuild($build);
  }

}
