<?php

namespace Drupal\uiowa_core\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to validate iframe elements.
 *
 * @Filter(
 *   id = "filter_iframe",
 *   title = @Translation("Validate iframe elements"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_HTML_RESTRICTOR,
 *   description = @Translation("Limits &lt;iframe&gt; tag sources to those specified in the configuration."),
 *   settings = {
 *    "allowed_sources" = NULL
 *   },
 *   weight = -40
 * )
 */
class FilterIframe extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form['allowed_sources'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed sources'),
      '#description' => $this->t('Enter allowed iframe source URLs. Separate multiple sources with a return.'),
      '#default_value' => $this->settings['allowed_sources'] ?? '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $dom = Html::load($text);
    $iframes = $dom->getElementsByTagName('iframe');

    // Use a regressive loop to count through the elements.
    // See https://www.php.net/manual/en/domnode.replacechild.php#50500.
    $i = $iframes->length - 1;

    while ($i >= 0) {
      /** @var \DOMElement $iframe */
      $iframe = $iframes->item($i);

      if ($iframe && $iframe->hasAttribute('src')) {
        $allowed = explode(PHP_EOL, $this->settings['allowed_sources']);

        $allowed = array_map(function ($v) {
          return trim(UrlHelper::parse($v)['path']);
        }, $allowed);

        $src = trim(UrlHelper::parse($iframe->getAttribute('src'))['path']);

        if (!in_array($src, $allowed)) {
          $iframe->parentNode->removeChild($iframe);
        }
        else {
          // Set attributes for all iFrames for better performance, styling, security.
          // This will overwrite anything that was previously set by the editor.
          $iframe->setAttribute('loading', 'lazy');
          $iframe->setAttribute('seamless', 'seamless');
          $iframe->setAttribute('sandbox', 'allow-same-origin allow-scripts allow-popups');

          // Borrowed from iframe_title_filter module.
          if (!$iframe->hasAttribute('title')) {
            $url_pieces = parse_url($src);
            $host = $url_pieces['host'];
            $title = $this->t("Embedded content from @host", ['@host' => $host]);
            $iframe->setAttribute('title', $title);
          }

          $wrapper = $dom->createElement('div');

          // Try to set responsive styling based on width/height attributes.
          if ($iframe->hasAttribute('width') && $iframe->hasAttribute('height')) {
            $width = $iframe->getAttribute('width');
            $height = $iframe->getAttribute('height');
            $aspect_ratio = round($width / $height, 3);
            if ($aspect_ratio == '1') {
              $wrapper->setAttribute('class', 'embed-responsive embed-responsive-1by1');
            }
            elseif ($aspect_ratio == '1.333') {
              $wrapper->setAttribute('class', 'embed-responsive embed-responsive-4by3');
            }
            elseif ($aspect_ratio == '1.776') {
              $wrapper->setAttribute('class', 'embed-responsive embed-responsive-16by9');
            }
          }
          $iframe->parentNode->replaceChild($wrapper, $iframe);
          $wrapper->appendChild($iframe);
        }
      }
      else {
        // Remove any iframes without a src attribute.
        $iframe->parentNode->removeChild($iframe);
      }

      $i--;
    }

    $text = Html::serialize($dom);
    return new FilterProcessResult($text);
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    $allowed = explode(PHP_EOL, $this->settings['allowed_sources']);
    $allowed_list = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $allowed,
    ];
    if ($long) {
      $markup = '<p>You can embed iFrames from the following sources:</p>' . \Drupal::service('renderer')->render(($allowed_list));
      return $this->t($markup);
    }
    else {
      return $this->t('You can embed certain iFrames.');
    }
  }

}
