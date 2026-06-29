<?php

namespace Drupal\facilities_core\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Provides the building information tabs block.
 *
 * @Block(
 *   id = "building_tabs_block",
 *   admin_label = @Translation("Building Tabs Block"),
 *   category = @Translation("Site custom"),
 *   context_definitions = {
 *     "node" = @ContextDefinition("entity:node", label = @Translation("Node"))
 *   }
 * )
 */
class BuildingTabsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $node = $this->getContextValue('node');

    $tabs = [];

    // Hours field render. Disclaimer added in preprocess_field.
    $hours_items = $node->get('field_building_hours');
    if (!$hours_items->isEmpty()) {
      $tabs['hours'] = [
        'label' => $this->t('Hours'),
        'content' => $hours_items->view(['type' => 'text_default', 'label' => 'visually_hidden']),
      ];
    }

    // Restrooms tab. Multiple fields.
    $restroom_field_names = [
      'field_building_rr_multi_men',
      'field_building_rr_multi_women',
      'field_building_rr_single_men',
      'field_building_rr_single_women',
      'field_building_rr_single_neutral',
    ];
    $restroom_content = [];
    foreach ($restroom_field_names as $field_name) {
      $items = $node->get($field_name);
      if (!$items->isEmpty()) {
        $restroom_content[] = $items->view(['type' => 'string', 'label' => 'above']);
      }
    }
    if (!empty($restroom_content)) {
      $tabs['restrooms'] = [
        'label' => $this->t('Restrooms'),
        'content' => $restroom_content,
      ];
    }

    // Service resource tabs.
    $service_tabs = [
      'lactation_rooms' => [
        'label' => $this->t('Lactation rooms'),
        'field' => 'field_building_lactation_rooms',
      ],
      'aed' => [
        'label' => $this->t('AED'),
        'field' => 'field_building_aed',
      ],
      'stop_the_bleed' => [
        'label' => $this->t('Stop the Bleed'),
        'field' => 'field_building_stop_the_bleed',
      ],
      'evac_chairs' => [
        'label' => $this->t('EVAC Chairs'),
        'field' => 'field_building_evac_chairs',
      ],
    ];

    foreach ($service_tabs as $key => $config) {
      $paragraphs = $node->get($config['field'])->referencedEntities();
      if (!empty($paragraphs)) {
        $cards = [];
        foreach ($paragraphs as $paragraph) {
          $cards[] = $this->buildServiceResourceCard($paragraph);
        }
        $tabs[$key] = [
          'label' => $config['label'],
          'content' => [
            '#type' => 'container',
            '#attributes' => ['class' => ['grid--threecol--33-34-33']],
            'inner' => [
              '#type' => 'container',
              '#attributes' => ['class' => ['list-container__inner']],
              'cards' => $cards,
            ],
          ],
        ];
      }
    }

    if (empty($tabs)) {
      return [];
    }

    return [
      '#theme' => 'building_tabs_block',
      '#node' => $node,
      '#tabs' => $tabs,
      '#attached' => [
        'library' => ['uids_base/tabs', 'facilities_core/building_tabs'],
      ],
    ];
  }

  /**
   * Builds a card render array for a service_resource paragraph.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The service_resource paragraph entity.
   *
   * @return array
   *   A render array using the card element type.
   */
  protected function buildServiceResourceCard(Paragraph $paragraph): array {
    $floor = $paragraph->get('field_sr_floor')->getString();
    $room = $paragraph->get('field_sr_room')->getString();

    $title = match(TRUE) {
      $floor && $room => $this->t('Floor @floor, room @room', ['@floor' => $floor, '@room' => $room]),
      (bool) $floor => $this->t('Floor @floor', ['@floor' => $floor]),
      (bool) $room => $this->t('Room @room', ['@room' => $room]),
      default => 'Resource',
    };

    $content = [];

    foreach (['field_sr_equipment', 'field_sr_location_guide', 'field_sr_access_info'] as $field_name) {
      $items = $paragraph->get($field_name);
      if (!$items->isEmpty()) {
        $content[] = $items->view(['type' => 'string', 'label' => 'above']);
      }
    }

    // Contact fields as an address element.
    $contact_name = $paragraph->get('field_sr_contact_name')->getString();
    $contact_address = $paragraph->get('field_sr_contact_address')->getString();
    $contact_phone = $paragraph->get('field_sr_contact_phone')->getString();
    $contact_email = $paragraph->get('field_sr_contact_email')->getString();

    if ($contact_name || $contact_address || $contact_phone || $contact_email) {
      $address_parts = [];
      if ($contact_name) {
        $address_parts[] = $contact_name;
      }
      if ($contact_address) {
        $address_parts[] = $contact_address;
      }
      if ($contact_phone) {
        $address_parts[] = '<a href="tel:' . $contact_phone . '">' . $contact_phone . '</a>';
      }
      if ($contact_email) {
        $address_parts[] = '<a href="mailto:' . $contact_email . '">' . $contact_email . '</a>';
      }
      $content[] = [
        '#markup' => '<address>' . implode('<br>', $address_parts) . '</address>',
      ];
    }

    return [
      '#type' => 'card',
      '#title' => $title,
      '#title_heading_size' => 'h3',
      '#content' => $content,
    ];
  }

}
