<?php

namespace Drupal\uiowa_core\Entity;

use Drupal\Core\Render\Element;
use Drupal\node\Entity\Node;
use Drupal\uiowa_core\Element\Card;

/**
 * Bundle-specific subclass of Node.
 */
abstract class NodeBundleBase extends Node implements TeaserCardInterface {

  /**
   * {@inheritdoc}
   */
  public function addCardBuildInfo(array &$build): void {
    $build['#type'] = 'card';
    unset($build['#theme']);
    $build['#pre_render'] = [
      [
        Card::class,
        'preRenderCard',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildCard(array &$build) {
    foreach ($this->getDefaultStyles() as $style) {
      $build['#attributes']['class'][] = $style;
    }
    // Add shared fields to card.
    $this->mapFieldsToCardBuild($build, [
      '#media' => 'field_image',
      '#title' => 'title',
      '#content' => 'field_teaser',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultStyles(): array {
    return [
      'card_media_position' => 'card--layout-right',
      'media_size' => 'media--small',
      'media_format' => 'media--widescreen',
    ];
  }

  /**
   * Map build fields to card properties.
   *
   * @param array $build
   *   A renderable array representing the entity content.
   * @param array $mapping
   *   Array of field names.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function mapFieldsToCardBuild(array &$build, array $mapping): void {
    $hide_fields = $build['#hide_fields'] ?? [];

    // Map fields to the card parts.
    foreach ($mapping as $prop => $fields) {
      if (!is_array($fields)) {
        $fields = [$fields];
      }
      if (!isset($build[$prop])) {
        $build[$prop] = [];
      }
      foreach ($fields as $field_name) {
        // @todo Refine this to remove fields if they are empty.
        if (isset($build[$field_name]) && count(Element::children($build[$field_name])) > 0) {
          if (!in_array($field_name, $hide_fields)) {
            $build[$prop][$field_name] = $build[$field_name];
          }
          unset($build[$field_name]);
        }
      }
    }

    // @todo Move this to someplace it will only run once. Possibly in a
    //   preprocess function.
    $build['#url'] = !$this->isNew() ? $this->toUrl()->toString() : NULL;
  }

  /**
   * Helper function to construct link directly to source functionality.
   *
   * @param string $source_link_direct
   *   The field name of the boolean field controlling the functionality.
   * @param string $source_link
   *   The field name of the link field providing the source link.
   *
   * @return string
   *   The url used to link the view mode.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException|\Drupal\Core\Entity\EntityMalformedException
   */
  public function generateNodeLink(string $source_link_direct, string $source_link): string {
    $link_direct = (int) $this->get($source_link_direct)->value;
    $link = $this->get($source_link)->uri;
    if ($link_direct === 1 && isset($link) && !empty($link)) {
      $url = $this->get($source_link)->get(0)->getUrl()->toString();
    }
    else {
      $url = !$this->isNew() ? $this->toUrl('canonical')->toString() : NULL;
    }
    return $url;
  }

}
