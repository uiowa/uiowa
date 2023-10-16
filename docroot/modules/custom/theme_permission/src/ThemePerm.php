<?php

declare(strict_types=1);

namespace Drupal\theme_permission;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Theme Permission.
 *
 * @package Drupal\theme_permission
 */
class ThemePerm implements ContainerInjectionInterface {
  use StringTranslationTrait;


  /**
   * The theme handler.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected ThemeHandlerInterface $themeHandler;

  /**
   * ThemePerm constructor.
   *
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   */
  public function __construct(ThemeHandlerInterface $theme_handler) {
    $this->themeHandler = $theme_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('theme_handler')
    );
  }

  /**
   * Returns an array of permissions.
   *
   * @return array
   *   The permissions.
   */
  public function dynamicPermissions() :array {
    $perms = [];
    $themes = $this->themeHandler->listInfo();
    foreach (array_keys($themes) as $theme) {
      $type_params = ['%themename' => $theme];
      $perms += [
        "administer themes $theme" => [
          'title' => $this->t('administer themes %themename', $type_params),
        ],
        "uninstall themes $theme" => [
          'title' => $this->t('uninstall themes %themename', $type_params),
        ],
        "Edit Administration theme" => [
          'title' => $this->t('Edit Administration theme'),
        ],
      ];
    }
    return $perms;
  }

}
