<?php

namespace Drupal\sitenow_articles\Entity;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\TeaserCardInterface;

/**
 * Provides an interface for article entries.
 */
class Article extends NodeBundleBase implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    // Handle link directly to source functionality.
    $build['#url'] = $this->generateNodeLink('field_article_source_link_direct', 'field_article_source_link');

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_article_author',
      ],
    ]);

    // Construct remaining byline.
    $build['#meta']['byline'] = $this->getByline($build);

    // Add the published date.
    $created = $this->get('created')->value;
    $date = \Drupal::service('date.formatter')->format($created, 'medium');
    $build['#subtitle'] = $date;

    // Add view specific classes.
    if (isset($this->view)) {
      if ($this->view->id() === 'articles') {
        $media_attributes = [
          'card--list',
        ];
        $build['#attributes']->addClass($media_attributes);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStyles(): array {
    return [
      ...parent::getDefaultStyles(),
      'media_size' => 'media--small',
      'media_format' => 'media--widescreen',
    ];
  }

  /**
   * Constructs the byline for an article based on several fields.
   *
   * If there is an organization and not hidden, include it.
   * If there is an organization and not hidden, while there is a source link,
   * but it is hidden, wrap the org in the source link.
   * If there is a source link and not hidden, include it.
   *
   * @param array $build
   *   A renderable array representing the entity content.
   *
   * @return array
   *   The appropriate byline sent as a render array.
   */
  public function getByline(array $build): array {
    // Need to get these values regardless of whether they are hidden or not.
    $source_link = $this->get('field_article_source_link')->uri;
    $org = $this->get('field_article_source_org')->value;
    $hide_fields = $build['#hide_fields'] ?? [];

    $byline = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#weight' => 99,
      '#attributes' => [
        'class' => [
          'field--name-field-article-source-link',
        ],
      ],
    ];

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

    if (isset($byline['org']) || isset($byline['source_link'])) {
      return $byline;
    }

    return [];
  }

}
