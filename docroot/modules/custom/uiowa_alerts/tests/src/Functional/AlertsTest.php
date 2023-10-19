<?php

namespace Drupal\Tests\uiowa_alerts\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Test description.
 *
 * @group uiowa_alerts
 */
class AlertsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'uids_base';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'uiowa_alerts',
    'allowed_formats',
    'node',
    'block',
    'fontawesome',
  ];

  /**
   * The logged in user with necessary permissions to administer alerts.
   */
  protected User|false $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('uids_base.settings')->set('header.type', 'inline')->save();

    $this->drupalPlaceBlock('uiowa_alerts_block', [
      'region' => 'alert',
      'id' => 'alertsblock',
      'plugin' => 'uiowa_alerts_block',
    ]);

    $this->drupalCreateContentType(['type' => 'page']);
    $node = $this->drupalCreateNode();
    $this->config('system.site')->set('page.front', '/node/' . $node->id())->save(TRUE);

    $minimal = FilterFormat::create([
      'format' => 'minimal',
      'name' => 'Minimal',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<h2> <p> <br> <strong> <a> <em>',
          ],
        ],
      ],
    ]);

    $minimal->save();

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer uiowa_alerts configuration',
      'use text format minimal',
    ]);
  }

  /**
   * Test alerts display with expected test and class.
   *
   * @dataProvider alertLevels
   */
  public function testCustomAlert($level): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/sitenow/uiowa-alerts');
    $page = $this->getSession()->getPage();
    $page->checkField('Display Custom Alert');
    $page->selectFieldOption('Custom Alert Level', $level);
    $page->fillField('Custom Alert Message', "<h2>Test</h2><p>This is an alert of type $level.</p>");
    $page->pressButton('Save configuration');

    $this->drupalGet('<front>');
    $session = $this->assertSession();
    $session->pageTextContains("This is an alert of type $level.");
    $session->elementExists('css', 'div.alert--' . strtolower($level));

  }

  /**
   * Data provider for testCustomAlert().
   *
   * @return array
   *   Array of custom alert levels.
   */
  public function alertLevels(): array {
    return [
      ['Info'],
      ['Warning'],
      ['Danger'],
    ];
  }

}
