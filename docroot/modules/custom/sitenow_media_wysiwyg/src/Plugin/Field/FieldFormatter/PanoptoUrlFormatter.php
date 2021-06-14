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
    $host = \Drupal::request()->getHost();

    foreach ($elements as $delta => $entity) {
      $parsed_url = UrlHelper::parse($values[$delta]['uri']);

      $unique_id = Html::getUniqueId('panopto-media');
      $elements[$delta] = [
        '#type' => 'markup',
        '#markup' => '
          <div
            data-link="' . $parsed_url['query']['id'] . '"
            data-width="1920"
            data-height="1080"
            id=' . $unique_id . '
            class="panopto-player"
          >
          <iframe
            src="https://uicapture.hosted.panopto.com/Panopto/Pages/Embed.aspx?id=' . $parsed_url['query']['id'] . '&remoteEmbed=true&remoteHost=https://' . $host . ';embedApiId=' . $unique_id . '&interactivity=none&showtitle=false"
            width="1920"
            height="1080"
            allow="autoplay; fullscreen"
            frameborder="0"
            class=""
          >
          </iframe>
          </div>
        ',
        '#allowed_tags' => ['div', 'iframe'],
      ];
    }

    return $elements;
  }

}
