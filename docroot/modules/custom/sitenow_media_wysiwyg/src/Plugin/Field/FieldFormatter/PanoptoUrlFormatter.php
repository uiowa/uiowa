<?php

namespace Drupal\sitenow_media_wysiwyg\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Panopto URL field formatter.
 *
 * @FieldFormatter(
 *   id = "panopto_url_formatter",
 *   label = @Translation("Panopto"),
 *   description = @Translation("Display the panopto instance."),
 *   field_types = {
 *     "panopto_url"
 *   }
 * )
 */
class PanoptoUrlFormatter extends LinkFormatter {

  /**
   * Short description message...
   *
   * @inheritDoc
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    $values = $items->getValue();

    foreach ($elements as $delta => $entity) {
      $parsed_url = UrlHelper::parse($values[$delta]['uri']);
      $id = $parsed_url['query']['id'];

      $elements[$delta] = [
        'wrapper' => [
          '#type' => 'container',
          '#attributes' => [
            'data-link' => $id,
            'data-width' => '1920',
            'data-height' => '1080',
            'id' => Html::getUniqueId('panopto-media'),
            'class' => 'panopto-player',
          ],
        ],
        'frame' => [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'src' => "https://uicapture.hosted.panopto.com/Panopto/Pages/Embed.aspx?id={$id}&&autoplay=false&offerviewer=true&showtitle=false&showbrand=false&start=0&interactivity=none",
            'width' => '1920',
            'height' => '1080',
            'allow' => 'autoplay; fullscreen',
            'frameborder' => '0',
          ],
        ],
      ];
    }

    return $elements;
  }

}
