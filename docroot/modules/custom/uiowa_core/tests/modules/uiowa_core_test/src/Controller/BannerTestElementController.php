<?php

namespace Drupal\uiowa_core_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormState;
use Drupal\layout_builder_custom\BannerBlockFormHandler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides route responses for banner test element.
 */
class BannerTestElementController extends ControllerBase {

  /**
   * Returns the banner element test page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array containing a banner.
   */
  public function index(Request $request): array {
    $event = $request->query->get('event', '');
    $show_title = $request->query->getBoolean('title');

    $link_title = 'Apply now';
    $banner_title = $show_title ? 'Banner test title' : '';

    // Build link attributes using
    // the BannerBlockFormHandler form validation.
    $form = [];
    $form_state = new FormState();
    $form_state->setValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
    ], [[
      'uri' => 'https://uiowa.edu',
      'title' => $link_title,
      'options' => [
        'attributes' => [
          'data-sn-event' => $event,
          'data-sn-event-type' => 'click',
          'data-sn-event-component' => 'button',
          'data-sn-event-label' => $link_title,
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
    ], $banner_title);
    BannerBlockFormHandler::validateForm($form, $form_state);

    $attributes = $form_state->getValue([
      'settings',
      'block_form',
      'field_uiowa_banner_link',
      0,
      'options',
      'attributes',
    ]) ?? [];

    $banner = [
      'banner_title' => $banner_title,
      'banner_summary' => 'Banner summary',
      'links' => [
        [
          'link_url' => 'https://uiowa.edu',
          'link_text' => $link_title,
          'link_attributes' => $attributes,
        ],
      ],
    ];

    return [
      'banner_test' => [
        '#type' => 'inline_template',
        '#template' => "{% include '@uids_base/uids/banner.html.twig' with banner only %}",
        '#context' => ['banner' => $banner],
      ],
      '#cache' => [
        'contexts' => ['url.query_args'],
      ],
    ];
  }

}
