<?php

namespace Drupal\sitenow_people;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\sitenow_people\PersonTypeInterface;

/**
 * Provides a listing of person types.
 */
class PersonTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    /** @var \Drupal\sitenow_people\PersonTypeInterface $entity */
    if ($entity->isProtected()) {
      unset($operations['delete']);
    }
    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['status'] = $this->t('Status');
    $header['allow_former'] = $this->t('Allow former');
    $header['protected'] = $this->t('Protected');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\sitenow_people\PersonTypeInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    $row['allow_former'] = $entity->get('allow_former') ? $this->t('Enabled') : $this->t('Disabled');
    $row['protected'] = $entity->isProtected() ? $this->t('Yes') : $this->t('No');
    return $row + parent::buildRow($entity);
  }

}
