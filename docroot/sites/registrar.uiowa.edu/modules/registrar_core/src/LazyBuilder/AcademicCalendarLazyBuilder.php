<?php

namespace Drupal\registrar_core\LazyBuilder;

use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\uiowa_maui\MauiApi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a service for a academic calendar #lazy_builder callback.
 */
class AcademicCalendarLazyBuilder implements TrustedCallbackInterface {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The MAUI API service.
   *
   * @var \Drupal\uiowa_maui\MauiApi
   */
  protected $maui;

  /**
   * AcademicCalendarLazyBuilder constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\uiowa_maui\MauiApi $maui
   *   The MAUI API service.
   */
  public function __construct(RendererInterface $renderer, MauiApi $maui) {
    $this->renderer = $renderer;
    $this->maui = $maui;
  }

  /**
   * Creates an instance of AcademicCalendarLazyBuilder.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('uiowa_maui.api')
    );
  }

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks() {
    return ['loadAcademicCalendarContent'];
  }

  /**
   * Builds the academic calendar content for lazy loading.
   *
   * @return array
   *   A renderable array representing the academic calendar content.
   */
  public function loadAcademicCalendarContent() {
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['academic-calendar content']],
    ];

    $build['content'] = [
      '#markup' => '<span class="fa-solid fa-spinner fa-spin"></span>',
    ];

    return $build;
  }

}
