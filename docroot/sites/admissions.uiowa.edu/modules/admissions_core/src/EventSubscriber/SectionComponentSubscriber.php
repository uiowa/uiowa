<?php

namespace Drupal\admissions_core\EventSubscriber;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\layout_builder\Event\SectionComponentBuildRenderArrayEvent;
use Drupal\layout_builder\LayoutBuilderEvents;
use Drupal\layout_builder\Plugin\Block\FieldBlock;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Add an HTML class to blocks that are displaying placeholder text.
 */
class SectionComponentSubscriber implements EventSubscriberInterface {

  /**
   * The country_manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * Constructor for the event subscriber.
   *
   * @param \Drupal\Core\Locale\CountryManagerInterface $countryManager
   *   The country_manager service.
   */
  public function __construct(CountryManagerInterface $countryManager) {
    $this->countryManager = $countryManager;
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

    // Check if this is an instance of the hometown field.
    if ($block instanceof FieldBlock && $block->getPluginId() === 'field_block:node:student_profile:field_person_hometown') {
      $contexts = $event->getContexts();
      if (isset($contexts['layout_builder.entity'])) {
        if ($node = $contexts['layout_builder.entity']->getContextValue()) {
          $home_location = [];

          // Add hometown, if it exists.
          $hometown = $node->hasField('field_person_hometown') ? $node->field_person_hometown->value : NULL;
          $state = $node->hasField('field_student_profile_state') ? $node->field_student_profile_state->value : NULL;
          if ($hometown) {
            $home_location[] = $hometown;
          }

          // Check the country. Add the state if it exists and the country
          // is the US. Otherwise, add the country.
          $country = $node->hasField('field_student_profile_country') ? $node->field_student_profile_country->value : NULL;
          if ($country) {
            if ($country === 'US') {
              if ($state) {
                $home_location[] = $state;
              }
            }
            else {
              $country_value = $this->countryManager->getList()[$country]->__toString();
              $home_location[] = $country_value;
            }
          }
          else {
            if ($state) {
              $home_location[] = $state;
            }
          }
          if (!empty($home_location)) {
            $node->field_person_hometown->value = implode(', ', $home_location);
            $content = $block->build();

            $build = [
              // @todo Remove the duplicate section of code below once the
              //   following issue is resolved:
              //   https://github.com/uiowa/uiowa/issues/4993
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

    $event->setBuild($build);
  }

}
