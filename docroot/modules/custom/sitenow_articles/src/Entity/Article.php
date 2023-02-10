<?php

namespace Drupal\sitenow_articles\Entity;

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
  }

}
