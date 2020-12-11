<?php

namespace Drupal\uiowa_entities;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Academic Units.
 */
class DepartmentListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['catalog_url'] = $this->t('General Catalog URL');
    $header['maui_code'] = $this->t('MAUI Code');
    $header['homepage'] = $this->t('Homepage');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\uiowa_entities\UnitInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['catalog_url'] = $entity->get('catalog_url');
    $row['maui_code'] = $entity->get('maui_code');
    $row['homepage'] = $entity->get('homepage');
    return $row + parent::buildRow($entity);
  }

}
