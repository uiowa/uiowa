<?php

namespace Drupal\Tests\layout_builder_custom\Unit;

use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\layout_builder_custom\BannerBlockFormHandler;

/**
 * Tests banner form validation behavior.
 *
 * @group layout_builder_custom
 */
class BannerBlockFormHandlerTest extends UnitTestCase {

  /**
   * Tests analytics-related attributes are removed when event name is empty.
   */
  public function testValidateFormRemovesAnalyticsAttributesWhenEventMissing(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
    ], [
      [
        'uri' => 'https://example.com',
        'title' => 'Learn more',
        'options' => [
          'attributes' => [
            'data-sn-event' => '',
            'data-sn-event-type' => 'click',
            'data-sn-event-component' => 'button',
            'data-sn-event-label' => 'Example',
          ],
        ],
      ],
    ]);
    $form_state->setValue([
      'settings',
      'block_form',
      'field_uiowa_banner_title',
      0,
      'container',
      'text',
    ], 'Banner title');

    BannerBlockFormHandler::validateForm($form, $form_state);

    $attributes = $form_state->getValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
      0,
      'options',
      'attributes',
    ]);

    $this->assertArrayNotHasKey('data-sn-event', $attributes);
    $this->assertArrayNotHasKey('data-sn-event-type', $attributes);
    $this->assertArrayNotHasKey('data-sn-event-component', $attributes);
    $this->assertArrayNotHasKey('data-sn-event-label', $attributes);
  }

  /**
   * Tests analytics-related attributes are kept when event name is provided.
   */
  public function testValidateFormKeepsAnalyticsAttributesWhenEventProvided(): void {
    $form = [];
    $form_state = new FormState();
    $form_state->setValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
    ], [
      [
        'uri' => 'https://example.com',
        'title' => 'Apply now',
        'options' => [
          'attributes' => [
            'data-sn-event' => 'Apply Now',
            'data-sn-event-type' => 'click',
            'data-sn-event-component' => 'button',
            'data-sn-event-label' => '',
          ],
        ],
      ],
    ]);

    BannerBlockFormHandler::validateForm($form, $form_state);

    $attributes = $form_state->getValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
      0,
      'options',
      'attributes',
    ]);

    $this->assertSame('apply_now', $attributes['data-sn-event']);
    $this->assertSame('click', $attributes['data-sn-event-type']);
    $this->assertSame('button', $attributes['data-sn-event-component']);
    $this->assertSame('Apply now', $attributes['data-sn-event-label']);
  }

}
