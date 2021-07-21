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
          $url = trim(UrlHelper::parse($v)['path']);
        }, $allowed);

        $src = trim(UrlHelper::parse($iframe->getAttribute('src'))['path']);

        if (!in_array($src, $allowed)) {
          $iframe->parentNode->removeChild($iframe);
        }
        else {
          $wrapper = $dom->createElement('div');
          $wrapper->setAttribute('class', 'media--type-remote-video');
          $iframe->parentNode->replaceChild($wrapper, $iframe);
          $wrapper->appendChild($iframe);
        }
      }

      $i--;
    }

    $text = Html::serialize($dom);
    return new FilterProcessResult($text);
  }

}
