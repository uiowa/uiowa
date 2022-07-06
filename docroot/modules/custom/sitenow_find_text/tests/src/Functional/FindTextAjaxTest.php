<?php

namespace Drupal\Tests\sitenow_find_text\Functional;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\menu_admin_per_menu\Traits\MenuLinkContentTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * AJAX-based tests for the SiteNow Find Text module.
 *
 * @group sitenow_find_text
 */
class FindTextAjaxTest extends WebDriverTestBase {
  use NodeCreationTrait;
  use MenuLinkContentTrait;
  use TaxonomyTestTrait;

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = [
    'sitenow_find_text',
    'menu_link_content',
    'node',
    'taxonomy',
  ];


  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A simple user.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * The Find Text tool page.
   *
   * @var \Drupal\Core\GeneratedUrl
   */
  private $findTextPage;

  /**
   * Perform initial setup tasks that run before every test method.
   */
  public function setUp() : void {
    parent::setUp();
    // Create a user with the find text permission.
    $this->user = $this->drupalCreateUser(['access find text']);
    // Grab the relative path to our find text page.
    $this->findTextPage = Url::fromRoute('sitenow_find_text.search_form', [], ['absolute' => FALSE])
      ->toString();
  }

}
