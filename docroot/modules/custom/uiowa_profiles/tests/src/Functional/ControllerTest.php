<?php

namespace Drupal\Tests\uiowa_profiles\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test description.
 *
 * @group uiowa_profiles
 */
class ControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'uiowa_profiles'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('module_installer')->install(['uiowa_profiles_mock']);

    // Set uids_base header type to avoid Twig error.
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();

    $this->config('system.site')->set('name', 'Test Site')->save();

    $directories = [
      [
        'title' => 'People',
        'api_key' => '123-456-7890',
        'path' => '/directory',
        'page_size' => 25,
        'intro' => [
          'value' => 'This is the intro',
          'format' => 'filtered_html',
        ],
      ],
    ];

    $this->config('uiowa_profiles.settings')->set('directories', $directories)->save();

    drupal_flush_all_caches();
  }

  /**
   * Test directory meta data.
   */
  public function testDirectory() {
    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);
    $this->drupalGet('directory');
    $session = $this->assertSession();
    $session->elementExists('css', 'head title');
    $session->elementsCount('css', 'head title', 1);
    $session->titleEquals('People | Test Site');

    // $session->elementExists('css', 'meta[name="description"]');
  }

}
