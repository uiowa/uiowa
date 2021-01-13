<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Utility\Html;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a MAUI Calendar block.
 *
 * @Block(
 *   id = "uiowa_maui_academic_calendar",
 *   admin_label = @Translation("Academic calendar"),
 *   category = @Translation("MAUI")
 * )
 */
class AcademicCalendarBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * Override the construction method.
   *
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API class.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->maui = $maui;
  }

  /**
   * Override the create method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The application container.
   * @param array $configuration
   *   The block configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   *
   * @return static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('uiowa_maui.api')
    );
  }

  /**
   * Build the content for mymodule block.
   */
  public function build() {
    // @todo Replace with block configuration, if set.
    $current = $this->maui->getCurrentSession()->id;
    // @todo Replace with block configuration, if set.
    $category = 'GRADUATE_STUDENTS';

    // @todo If session is not set in block configuration, show
    //   the form element.
    if (FALSE) {
      // Get a list of sessions for the select list options.
      $sessions = $this->maui->getSessionsBounded(10, 10);
      $options = [];

      foreach ($sessions as $session) {
        $options[$session->id] = Html::escape($session->shortDescription);
      }

      $form['session'] = [
        '#type' => 'select',
        '#title' => $this->t('Session'),
        '#description' => $this->t('Select a session to show dates for.'),
        '#default_value' => $current,
        '#options' => $options,
        '#ajax' => [
          'callback' => [$this, 'sessionChanged'],
          'wrapper' => 'maui-dates-wrapper',
        ],
      ];
    }

    // @todo If category is not set in block configuration, show
    //   the form element.
    if (FALSE) {
      $form['category'] = [
        '#type' => 'select',
        '#title' => $this->t('Category'),
        '#description' => $this->t('Select a category to filter dates on.'),
        '#default_value' => $category,
        '#empty_value' => NULL,
        '#empty_option' => $this->t('- All -'),
        '#options' => $this->maui->getDateCategories(),
        '#ajax' => [
          'callback' => [$this, 'categoryChanged'],
          'wrapper' => 'maui-dates-wrapper',
        ],
      ];
    }
    $data = $this->maui->getSessionDates($current, $category);

    // This ID needs to be different than the form ID.
    $form['dates-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'maui-dates-wrapper',
        'aria-live' => 'polite',
      ],
      'dates' => [],
    ];

    if (!empty($data)) {
      $form['dates-wrapper']['dates'][] = [
        '#theme' => 'uiowa_maui_session_dates',
        '#data' => $data,
        // @todo Replace with block configuration.
        '#heading_size' => 'h2',
      ];
    }
    else {
      $form['dates-wrapper']['dates'] = [
        '#markup' => $this->t('No dates found.'),
      ];
    }
    return $form;
  }

}
