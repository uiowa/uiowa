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
   * If entity has link directly to source field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link_direct = NULL;

  /**
   * If entity has source link field.
   *
   * @var string|null
   *   field name or null.
   */
  protected $source_link = NULL;

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
    // @todo Do we still need a '.card--list' class? Or could this be handled
    //   a more generic `.view .card` definition? If we still need it, we need
    //   to figure out how to handle adding it conditionally based on the
    //   card being in a view or other list.
    // @todo How to handle setting the headline size?
    // @todo Do we need any of the '.node' or '.node--*' classes? E.g.:
    //   'node',
    //   'node--type-' ~ node.bundle|clean_class,
    //   node.isPromoted() ? 'node--promoted',
    //   node.isSticky() ? 'node--sticky',
    //   not node.isPublished() ? 'node--unpublished',
    //   view_mode ? 'node--view-mode-' ~ view_mode|clean_class,
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
      'media_format' => 'media--widescreen',
      'media_size' => 'media--small',
      'styles' => 'borderless',
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
   * @return string
   *   The url used to link the view mode.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException|\Drupal\Core\Entity\EntityMalformedException
   */
  public function getNodeUrl(): string {
    $source_link_direct = $this->source_link_direct;
    $source_link = $this->source_link;

    if (is_null($source_link_direct) || is_null($source_link)) {
      $url = !$this->isNew() ? $this->toUrl('canonical')->toString() : NULL;
    }
    else {
      $link_direct = (int) $this->get($source_link_direct)->value;
      $link = $this->get($source_link)->uri;
      if ($link_direct === 1 && isset($link) && !empty($link)) {
        $url = $this->get($source_link)->get(0)->getUrl()->toString();
      }
      else {
        $url = !$this->isNew() ? $this->toUrl('canonical')->toString() : NULL;
      }
    }
    return $url;
  }

}
