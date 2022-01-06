<?php

namespace Drupal\Tests\uiowa_core\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test description.
 *
 * @group uiowa_core
 */
class MailTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['uiowa_core', 'uiowa_core_test', 'filter', 'system'];

  /**
   * The email message.
   *
   * @var array
   */
  protected $params;

  /**
   * The service account email address.
   *
   * @var string
   */
  protected $serviceAccount;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->params = [
      'subject' => 'Test Email',
      'body' => 'This is a test.',
    ];

    $this->serviceAccount = base64_decode('aXRzLXdlYkB1aW93YS5lZHU=');
  }

  /**
   * Test O365 header is always set.
   *
   * @dataProvider providerO365
   */
  public function testO365HeaderSet($from, $name) {
    $this->config('system.site')->set('mail', $from)->save();
    $this->config('system.site')->set('name', $name)->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals('ITS-Acquia', $result['headers']['X-UI-Hosted']);
  }

  /**
   * Test the from email is overridden if not originating from uiowa.edu.
   */
  public function testFromAddressOverriddenIfNotUiowa() {
    $this->config('system.site')->set('mail', 'foo@bar.com')->save();
    $this->config('system.site')->set('name', 'Foo')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals("\"Foo\" <$this->serviceAccount>", $result['headers']['From']);
  }

  /**
   * Test the from email is not overridden if originating from uiowa.edu.
   */
  public function testFromAddressNotOverriddenIfUiowa() {
    $this->config('system.site')->set('mail', 'foo@uiowa.edu')->save();
    $this->config('system.site')->set('name', 'Foo')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertNotEquals("\"Foo\" <$this->serviceAccount>", $result['headers']['From']);
  }

  /**
   * Test the from name is set to site name if empty for non-uiowa.edu emails.
   */
  public function testFromNameSetToSiteNameIfEmptyAndNotUiowa() {
    $this->config('system.site')->set('mail', 'foo@uiowa.edu')->save();
    $this->config('system.site')->set('name', 'Test Site')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core_test', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals("\"Test Site\" <$this->serviceAccount>", $result['headers']['From']);
  }

  /**
   * Data provider for O365 header test.
   *
   * @return array
   *   Array of arguments.
   */
  public function providerO365() {
    return [
      [
        'someone@external.com',
        'Someone External',
      ],
      [
        'someone@uiowa.edu',
        'Someone Internal',
      ],
    ];
  }

}
