<?php

namespace Drupal\sitenow_articles\Entity;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for article entries.
 */
class Article extends NodeBundleBase implements ArticleInterface, RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  protected $sourceLinkDirect = 'field_article_source_link_direct';

  /**
   * {@inheritdoc}
   */
  protected $sourceLink = 'field_article_source_link';

  /**
   * {@inheritdoc}
   */
  protected $configSettings = 'sitenow_articles.settings';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_article_author',
      ],
    ]);

    // Add a byline
    // Check if hidden field have been provided.
    $hide_fields = $build['#hide_fields'] ?? [];
    // If the source link is hidden or not set, its value is NULL.
    $source_link = !in_array('field_article_source_link', $hide_fields) ? $this->get('field_article_source_link')->uri : NULL;
    // If the source org is hidden or not set, its value is NULL.
    $source_org = !in_array('field_article_source_org', $hide_fields) ? $this->get('field_article_source_org')->value : NULL;
    // If the source author is hidden or not set, its value is NULL.
    $source_author = in_array('field_article_author', $hide_fields) ? NULL : !$this->get('field_article_author')->isEmpty();

    // Add a wrapper div with class "fa-field-item"
    // if author field is not empty or if it's hidden in hide_fields.
    if ((!$this->get('field_article_author')->isEmpty() && $source_author)) {
      $build['#meta']['#prefix'] = '<div class="fa-field-item">';
      $build['#meta']['#suffix'] = '</div>';
    }

    // The link text should be the org, if set, or the source link, if set. It
    // will be NULL otherwise.
    $source_output = $source_org ?? $source_link;

    // If there is a source link, then turn the link text into a link.
    // Otherwise, it will just be rendered as text.
    if ($source_output) {
      if ($source_link) {
        $source_output = Link::fromTextAndUrl($source_output, Url::fromUri($source_link))
          ->toString();
      }
      // Add the output for whatever we've generated up to this point.
      $build['#meta']['byline'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#weight' => 99,
        '#attributes' => [
          'class' => [
            'field--article-byline',
          ],
        ],
        'source' => [
          '#markup' => $source_output,
        ],
      ];
    }

    // Add the published date if it has not been hidden.
    if (!in_array('created', $hide_fields)) {
      $created = $this->get('created')->value;
      $date = \Drupal::service('date.formatter')->format($created, 'medium');
      $build['#subtitle'] = $date;
    }
  }

}
