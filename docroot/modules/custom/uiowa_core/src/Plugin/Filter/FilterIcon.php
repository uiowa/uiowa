<?php

namespace Drupal\uiowa_core\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to provide missing role attribute to icons.
 *
 * If an icon is being placed, make sure it has role="presentation".
 *
 * @Filter(
 *   id = "filter_icon",
 *   title = @Translation("Add role attribute to icons"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = -30
 * )
 */
class FilterIcon extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    // Set role attribute for icon spans. Overrides existing role attribute.
    $html_dom = Html::load($text);
    $spans = $html_dom->getElementsByTagName('span');
    foreach ($spans as $span) {
      if ($span->hasAttribute('class')) {
        $class = $span->getAttribute('class');
        $classes = explode(' ', $class);
        $icon_classes = ['fa', 'fab', 'fas'];
        foreach ($icon_classes as $icon_class) {
          if (in_array($icon_class, $classes)) {
            $span->setAttribute('role', 'presentation');
            break;
          }
        }
      }
    }

    $text = Html::serialize($html_dom);
    return new FilterProcessResult($text);
  }

}
