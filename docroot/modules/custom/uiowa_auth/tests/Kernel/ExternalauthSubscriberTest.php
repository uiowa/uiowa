<?php

namespace Drupal\Tests\uiowa_auth\Kernel;

use Drupal\uiowa_auth\EventSubscriber\ExternalAuthSubscriber;
use Drupal\user\Entity\User;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test description.
 *
 * @group kernel
 */
class ExternalauthSubscriberTest extends EntityKernelTestBase {
  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The user account.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $account;

  /**
   * The authmap service.
   *
   * @var \Drupal\externalauth\Authmap
   */
  protected $authmap;

  /**
   * The SamlauthUserSyncEvent.
   *
   * @var \Drupal\samlauth\Event\SamlauthUserSyncEvent
   */
  protected $event;

  /**
   * The samlauth service.
   *
   * @var \Drupal\samlauth\SamlService
   */
  protected $saml;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['uiowa_auth', 'externalauth', 'samlauth'];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    file_put_contents($this->root . '/../vendor/onelogin/php-saml/certs/idp.crt', 'foo');
    $this->installConfig(['uiowa_auth', 'externalauth', 'samlauth']);
    $this->installSchema('externalauth', ['authmap']);

    $this->config = $this->container->get('config.factory');
    $this->config->getEditable('samlauth.authentication')
      ->set('user_name_attribute', 'name')
      ->set('idp_certs', [
        'boguscertdata',
      ])
      ->set('idp_entity_id', 'bogus-idp-id')
      ->set('idp_single_sign_on_service', 'https://bogus.uiowa.edu/sso')
      ->set('idp_single_log_out_service', 'https://bogus.uiowa.edu/slo')
      ->set('sp_entity_id', 'bogus-sp-id')
      ->set('sp_x509_certificate', 'boguscertdata')
      ->set('sp_private_key', 'bogusprivatekeydata')
      ->save();

    $this->config->getEditable('uiowa_auth.settings')->set('role_mappings', [
      'webmaster|groups|DN=web',
      'webmaster|groups|DN=web2',
      'editor|groups|DN=edit',
    ])->save();

    $this->logger = $this->createMock('Psr\Log\LoggerInterface');
    $this->authmap = $this->container->get('externalauth.authmap');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->event = $this->createMock('Drupal\externalauth\Event\ExternalAuthLoginEvent');

    $this->event->expects($this->any())
      ->method('getProvider')
      ->will($this->returnValue('samlauth'));

    $this->event->expects($this->any())
      ->method('getAuthname')
      ->will($this->returnValue('foo'));

    $attributes = [
      'name' => ['foo'],
      'groups' => [
        'DN=web',
        'DN=foo',
        'DN=bar',
      ],
    ];

    $this->samlauth = $this->createMock('Drupal\samlauth\SamlService');

    $this->samlauth->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($attributes));
  }

  /**
   * Test integrity of authmap data.
   */
  public function testAuthmapData() {
    /** @var \Drupal\user\UserInterface $account */
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $account->addRole('webmaster');
    $account->addRole('editor');
    $account->save();

    $this->samlauth->expects($this->any())
      ->method('getAttributeByConfig')
      ->will($this->returnValue($account->getAccountName()));

    $this->event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new ExternalAuthSubscriber($this->config, $this->logger, $this->authmap, $this->samlauth);
    $sut->onUserLogin($this->event);
    $data = unserialize($this->authmap->getAuthData($account->id(), 'samlauth')['data'], ['allowed_classes' => FALSE]);
    $count = array_count_values($data['uiowa_auth_mappings']);
    $this->assertEquals(1, $count['webmaster']);
    $this->assertArrayNotHasKey('editor', $count);
  }

  /**
   * Test no authmap row exists when account names don't match SAML response.
   */
  public function testNoAuthmapDataWhenAccountNamesDoNotMatch() {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $account->save();

    $this->samlauth->expects($this->any())
      ->method('getAttributeByConfig')
      ->will($this->returnValue('bogus'));

    $this->event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new ExternalAuthSubscriber($this->config, $this->logger, $this->authmap, $this->samlauth);
    $sut->onUserLogin($this->event);
    $this->assertFalse($this->authmap->get($account->id(), 'samlauth'));
  }

}
