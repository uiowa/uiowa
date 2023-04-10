<?php

namespace Drupal\sitenow_people\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\uiowa_core\Entity\NodeBundleBase;
use Drupal\uiowa_core\Entity\RendersAsCardInterface;

/**
 * Provides an interface for person entries.
 */
class Person extends NodeBundleBase implements RendersAsCardInterface {

  use StringTranslationTrait;

  /**
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $sourceLinkDirect = 'field_person_website_link_direct';

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $sourceLink = 'field_person_website';

  /**
   * {@inheritdoc}
   */
  protected $configSettings = 'sitenow_people.settings';

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    // If the image field is empty, replace it with a placeholder image. This
    // needs to be done prior to parent::buildCard() since that uses
    // field_image.
    if ($this->get('field_image')->isEmpty()) {

      // Handle the case when there is no image.
      $build['field_image'] = [
        // Using a nested array to get around the Element::children() check in
        // mapFieldsToCardBuild().
        [
          '#type' => 'image_empty_person',
          '#alt' => $this->getTitle(),
        ],
      ];
    }

    parent::buildCard($build);

    // Add the person library.
    $build['#attached']['library'][] = 'uids_base/person';
    // Add the media library.
    $build['#attached']['library'][] = 'uids_base/media';

    // Process additional card mappings.
    $this->mapFieldsToCardBuild($build, [
      '#subtitle' => 'field_person_position',
      '#meta' => [
        'field_person_email',
        'field_person_phone',
      ],
    ]);

    // Handle link directly to source functionality.
    $build['#url'] = $this->getNodeUrl();

    // Append person credentials to the node label in the teaser view mode.
    if (!is_null($creds = $this->get('field_person_credential')?->getString())) {
      if (!empty($creds)) {
        $title = $this->getTitle();
        $build['#title'] = $this->t('@title, @creds', [
          '@title' => $title,
          '@creds' => $creds,
        ]);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultCardStyles(): array {
    return [
      ...parent::getDefaultCardStyles(),
      'card_media_position' => 'card--layout-left',
      'media_format' => 'media--circle media--border',
    ];
  }

}
