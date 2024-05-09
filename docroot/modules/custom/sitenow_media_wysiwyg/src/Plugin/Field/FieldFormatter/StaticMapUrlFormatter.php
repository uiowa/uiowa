<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * StaticMap URL field formatter.
 *
 * @FieldFormatter(
 *   id = "static_map_url_formatter",
 *   label = @Translation("Static Map"),
 *   description = @Translation("Display the static map instance."),
 *   field_types = {
 *     "static_map_url"
 *   }
 * )
 */
class StaticMapUrlFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();
    foreach ($elements as $delta => $entity) {
      $regex = \Drupal::config('sitenow_media_wysiwyg.settings')
        ->get('sitenow_media_wysiwyg.static_map_regex');
      if (!$regex) {
        break;
      }
      else {
        preg_match($regex, parse_url($values[0]['uri'], PHP_URL_FRAGMENT), $regex_matches);
        $location = $regex_matches[1];
        $alt = $values[0]['alt'];
        $zoom = $values[0]['zoom'];

        // Original/Default is medium square, else change view mode.
        $view_mode = 'medium__square';
        if ($this->viewMode && $this->viewMode !== 'default') {
          $view_mode = $this->viewMode;
        }

        $elements[$delta] = [
          'map' => [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => [
              'href' => $values[0]['uri'],
              'title' => $alt,
              'aria-label' => 'View on maps.uiowa.edu',
            ],
            'static' => [
              '#theme' => 'imagecache_external_responsive',
              '#uri' => urldecode("https://staticmap.concept3d.com/map/static-map/?map=1890&loc=" . $location . "&scale=2&zoom=" . $zoom),
              '#responsive_image_style_id' => $view_mode,
              '#attributes' => [
                'loading' => 'lazy',
                'alt' => $alt,
                'class' => 'static-map',
              ],
            ],
          ],
        ];
      }
    }
    return $elements;
  }

}
