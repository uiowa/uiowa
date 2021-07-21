<?php

namespace Drupal\uiowa_core\Plugin\Filter;

use Drupal\Component\Utility\Html;
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
    $html = Html::load($text);

    $iframes = $html->getElementsByTagName('iframe');
    $disallowed = [];

    /** @var \DOMElement $iframe */
    foreach ($iframes as $iframe) {
      if ($iframe->hasAttribute('src')) {
        $allowed = explode(PHP_EOL, $this->settings['allowed_sources']);
        $allowed = array_filter($allowed);

        $allowed = array_map(function ($v) {
          return parse_url($v, PHP_URL_HOST);
        }, $allowed);

        $src = parse_url($iframe->getAttribute('src'), PHP_URL_HOST);

        if (!in_array($src, $allowed)) {
          $disallowed[] = $iframe;
        }
      }
    }

    foreach ($disallowed as $node) {
      $node->parentNode->removeChild($node);
    }

    $text = Html::serialize($html);
    return new FilterProcessResult($text);
  }

}
