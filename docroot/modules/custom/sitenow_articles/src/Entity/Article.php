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
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $sourceLinkDirect = 'field_article_source_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $sourceLink = 'field_article_source_link';

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

    // If there is an organization and not hidden, include it.
    // If there is an organization and not hidden, while there is a source link,
    // but it is hidden, wrap the org in the source link.
    // If there is a source link and not hidden, include it.
    // Need to get these values regardless of whether they are hidden or not.
    $source_link = $this->get('field_article_source_link')->uri;
    $org = $this->get('field_article_source_org')->value;
    $hide_fields = $build['#hide_fields'] ?? [];
    $byline = [];

    if ($org && !in_array('field_article_source_org', $hide_fields)) {
      if (in_array('field_article_source_link', $hide_fields)) {
        if ($source_link) {
          // Wrap org in the source link.
          $org = Link::fromTextAndUrl($org, Url::fromUri($source_link))
            ->toString();
        }
      }
      $byline['org'] = [
        '#prefix' => '<span class="views-field-article-source-link">',
        '#markup' => $org,
        '#suffix' => '</span>',
      ];
    }

    if ($source_link && !in_array('field_article_source_link', $hide_fields)) {
      $link = Link::fromTextAndUrl($source_link, Url::fromUri($source_link))
        ->toString();
      $byline['source_link'] = [
        '#prefix' => '<span class="views-field-article-source-link">',
        '#markup' => $link,
        '#suffix' => '</span>',
      ];
    }

    if (!empty($byline)) {
      $build['#meta']['byline'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#weight' => 99,
        '#attributes' => [
          'class' => [
            'field--name-field-article-source-link',
          ],
        ],
        ...$byline,
      ];
    }

    // Add the published date.
    $created = $this->get('created')->value;
    $date = \Drupal::service('date.formatter')->format($created, 'medium');
    $build['#subtitle'] = $date;

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    $default_classes = [
      ...parent::getDefaultCardStyles(),
      'media_size' => 'media--small',
      'media_format' => 'media--widescreen',
    ];

    return $default_classes;
  }

}
