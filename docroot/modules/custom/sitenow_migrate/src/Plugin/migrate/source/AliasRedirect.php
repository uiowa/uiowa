<?php

namespace Drupal\sitenow_migrate\Plugin\migrate\source;

/**
 * Basic implementation of the source plugin.
 *
 * @MigrateSource(
 *  id = "sitenow_alias_redirect",
 *  source_module = "node"
 * )
 */
class AliasRedirect extends BaseNodeSource {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = parent::query();
    $query->join('url_alias', 'alias', "alias.source = CONCAT('node/', n.nid)");
    $query->fields('alias', ['alias']);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = parent::fields();
    $fields['alias'] = $this->t('The URL alias for this node.');
    return $fields;
  }

}
