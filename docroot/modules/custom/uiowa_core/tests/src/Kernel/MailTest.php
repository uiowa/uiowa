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
  public static $modules = ['uiowa_core'];

  /**
   * The email message.
   *
   * @var array
   */
  protected $message;

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

    $this->message = [
      'subject' => 'Test Email',
      'body' => 'This is a test.',
      'from_name' => 'Foo',
      'from_mail' => 'foo@uiowa.edu',
    ];

    $this->serviceAccount = base64_decode('aXRzLXdlYkB1aW93YS5lZHU=');
  }

  /**
   * Test O365 header is always set.
   */
  public function testO365HeaderSet() {
    $result = $this->container->get('plugin.manager.mail')->doMail('uiowa_core', 'key', 'admin@example.com', 'en', $this->message);
    $this->assertEquals('ITS-Acquia', $result['headers']['X-UI-Hosted']);
  }

  /**
   * Test the from email is overridden if not originating from uiowa.edu.
   */
  public function testFromAddressOverriddenIfNotUiowa() {
    $this->message['from_mail'] = 'foo@bar.com';
    $this->message['from_name'] = 'Foo';
    $result = $this->container->get('plugin.manager.mail')->doMail('uiowa_core', 'key', 'admin@example.com', 'en', $this->message);
    $this->assertEquals("\"Foo\" <$this->serviceAccount>", $result['headers']['From']);
  }

  /**
   * Test the from email is not overridden if originating from uiowa.edu.
   */
  public function testFromAddressNotOverriddenIfUiowa() {
    $result = $this->container->get('plugin.manager.mail')->doMail('uiowa_core', 'key', 'admin@example.com', 'en', $this->message);
    $this->assertNotEquals("\"Foo\" <$this->serviceAccount>", $result['headers']['From']);
  }

}
