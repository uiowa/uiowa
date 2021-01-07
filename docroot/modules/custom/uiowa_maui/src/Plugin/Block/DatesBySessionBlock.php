<?php

namespace Drupal\uiowa_maui\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block that lists academic dates by session with exposed filters.
 *
 * @Block(
 *   id = "uiowa_maui_dates_by_session",
 *   admin_label = @Translation("Academic dates by session"),
 *   category = @Translation("MAUI")
 * )
 */
class DatesBySessionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var MauiApi
   */
  protected $mauiApi;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, MauiApi $maui) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->mauiApi = $maui;
  }

  /**
   * {@inheritDoc}
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
   * {@inheritdoc}
   */
  public function build() {
    $session = $this->mauiApi->getCurrentSession();

    $build['content'] = [
      '#markup' => $this->t('The current session is @current.', [
        '@current' => $session->shortDescription,
      ]),
    ];

    return $build;
  }

}
