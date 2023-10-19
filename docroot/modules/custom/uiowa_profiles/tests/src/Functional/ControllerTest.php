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
  protected static $modules = ['uiowa_profiles'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // This needs to be enabled here to avoid a non-existent service error.
    \Drupal::service('module_installer')->install(['uiowa_profiles_mock']);

    // Set uids_base header type to avoid Twig error.
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();

    // This is used in the controller.
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

    // This is necessary to rebuild the ProfilesDynamic route.
    drupal_flush_all_caches();
  }

  /**
   * Test directory meta data.
   */
  public function testDirectory(): void {
    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);
    $this->drupalGet('directory');
    $session = $this->assertSession();
    $session->elementExists('css', 'head title');
    $session->elementsCount('css', 'head title', 1);
    $session->titleEquals('People | Test Site');

    $session->elementExists('css', 'meta[name="description"]');
  }

  /**
   * Test directory person page meta data.
   */
  public function testDirectoryPerson(): void {
    $user = $this->createUser(['access content']);
    $this->drupalLogin($user);
    $this->drupalGet('directory/foo-bar');
    $session = $this->assertSession();

    $session->elementExists('css', 'head title');
    $session->elementsCount('css', 'head title', 1);
    $session->titleEquals('Foo Bar | Test Site');

    $session->elementExists('css', 'meta[name="description"]');
    $session->elementsCount('css', 'meta[name="description"]', 1);

    $session->elementExists('css', 'script[type="application/ld+json"]');
    $session->elementsCount('css', 'script[type="application/ld+json"]', 1);
  }

}
