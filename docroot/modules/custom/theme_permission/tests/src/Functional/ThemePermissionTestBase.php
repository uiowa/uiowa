<?php

declare(strict_types=1);

namespace Drupal\Tests\theme_permission\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Administration theme access check.
 *
 * @group theme_permission
 */
abstract class ThemePermissionTestBase extends BrowserTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'user',
    'system',
    'theme_permission',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bartik';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    \Drupal::service('theme_installer')->install(['bartik', 'seven']);

    $settings = [
      'theme' => 'bartik',
      'region' => 'header',
    ];
    // Place a block.
    $this->drupalPlaceBlock('local_tasks_block', $settings);
  }

  /**
   * Drupal User login.
   *
   * @param array $permissions
   *   User permission.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function userLogin(array $permissions = NULL) {

    $permissions = isset($permissions) ? $permissions : [];
    $userPermission = array_merge(
      $permissions,
      [
        'administer themes',
        'administer blocks',
      ]
    );

    $user = $this->drupalCreateUser($userPermission);
    $this->drupalLogin($user);
  }

}
