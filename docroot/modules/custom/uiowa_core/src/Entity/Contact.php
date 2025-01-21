<?php

namespace Drupal\uiowa_core\Entity;

/**
 * Provides an interface for contact entries.
 */
class Contact extends NodeBundleBase implements RendersAsCardInterface {

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    parent::buildCard($build);

    $this->mapFieldsToCardBuild($build, [
      '#meta' => [
        'field_contact_email',
        'field_contact_phone_number',
        'field_contact_fax',
        'field_contact_address',
      ],
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return array_merge(
      parent::getDefaultCardStyles(),
      [
        'media_format' => 'media--square',
        'border' => 'borderless',
      ]
    );
  }

}
