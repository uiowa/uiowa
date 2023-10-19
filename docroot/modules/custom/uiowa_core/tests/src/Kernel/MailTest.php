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
  public static $modules = [
    'filter',
    'symfony_mailer',
    'system',
    'uiowa_core',
    'uiowa_core_test',
    'user',
  ];

  /**
   * The email message.
   *
   * @var array
   */
  protected array $params;

  /**
   * The service account email address.
   */
  protected string $serviceAccount;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->params = [
      'subject' => 'Test Email',
      'body' => 'This is a test.',
    ];

    $this->serviceAccount = 'sitenow-noreply@uiowa.edu';
  }

  /**
   * Test O365 header is always set.
   *
   * @dataProvider providerO365
   */
  public function testO365HeaderSet($from, $name): void {
    $this->config('system.site')->set('mail', $from)->save();
    $this->config('system.site')->set('name', $name)->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals('ITS-Acquia', $result['headers']['x-ui-hosted']);
  }

  /**
   * Test the from email is overridden if not originating from uiowa.edu.
   */
  public function testFromAddressOverriddenIfNotUiowa(): void {
    $this->config('system.site')->set('mail', 'foo@bar.com')->save();
    $this->config('system.site')->set('name', 'Foo')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals("Foo <$this->serviceAccount>", $result['headers']['from']);
  }

  /**
   * Test the from email is not overridden if originating from uiowa.edu.
   */
  public function testFromAddressNotOverriddenIfUiowa(): void {
    $this->config('system.site')->set('mail', 'foo@uiowa.edu')->save();
    $this->config('system.site')->set('name', 'Foo')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertNotEquals("Foo <$this->serviceAccount>", $result['headers']['from']);
  }

  /**
   * Test the from name is set to site name if empty for non-uiowa.edu emails.
   */
  public function testFromNameSetToSiteNameIfEmptyAndNotUiowa(): void {
    $this->config('system.site')->set('mail', 'foo@uiowa.edu')->save();
    $this->config('system.site')->set('name', 'Test Site')->save();
    $result = $this->container->get('plugin.manager.mail')->mail('uiowa_core_test', 'key', 'admin@example.com', 'en', $this->params);
    $this->assertEquals("Test Site <$this->serviceAccount>", $result['headers']['from']);
  }

  /**
   * Data provider for O365 header test.
   *
   * @return array
   *   Array of arguments.
   */
  public function providerO365(): array {
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
