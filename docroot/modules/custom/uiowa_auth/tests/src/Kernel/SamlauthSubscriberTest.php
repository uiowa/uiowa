<?php

namespace Drupal\Tests\uiowa_auth\Kernel;

use Drupal\uiowa_auth\EventSubscriber\SamlauthSubscriber;
use Drupal\user\Entity\User;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Test description.
 *
 * @group kernel
 */
class SamlauthSubscriberTest extends EntityKernelTestBase {
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
   * The EntityTypeManager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The SamlauthUserSyncEvent.
   *
   * @var \Drupal\samlauth\Event\SamlauthUserSyncEvent
   */
  protected $event;

  /**
   * Array of samlauth attributes.
   *
   * @var array
   */
  protected $attributes;

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
      'editor|groups|DN=edit',
    ])->save();

    $this->logger = $this->createMock('Psr\Log\LoggerInterface');
    $this->authmap = $this->container->get('externalauth.authmap');
    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->attributes = [
      'name' => ['foo'],
      'groups' => [
        'DN=web',
        'DN=foo',
        'DN=bar',
      ],
    ];

    $this->event = $this->createMock('Drupal\samlauth\Event\SamlauthUserSyncEvent');

    $this->event->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($this->attributes));
  }

  /**
   * Test new user is granted role.
   */
  public function testUserSyncGrantsRoleForNewUser() {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $this->event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new SamlauthSubscriber($this->config, $this->logger, $this->authmap, $this->entityTypeManager);
    $sut->onUserSync($this->event);
    $this->assertTrue($account->hasRole('webmaster'));
    $this->assertFalse($account->hasRole('editor'));
  }

  /**
   * Test new user is not granted role.
   */
  public function testUserSyncDoesNotGrantRoleForNewUser() {
    $account = User::create([
      'name' => $this->randomMachineName(),
      'status' => 1,
    ]);

    $attributes = [
      'name' => ['foo'],
      'groups' => [
        'DN=bogus',
        'DN=foo',
      ],
    ];

    $event = $this->createMock('Drupal\samlauth\Event\SamlauthUserSyncEvent');

    $event->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($attributes));

    $event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new SamlauthSubscriber($this->config, $this->logger, $this->authmap, $this->entityTypeManager);
    $sut->onUserSync($event);
    $this->assertFalse($account->hasRole('webmaster'));
    $this->assertFalse($account->hasRole('editor'));
  }

  /**
   * Test existing user is granted role.
   */
  public function testUserSyncGrantsRoleForExistingUser() {
    $account = $this->createUser();
    $this->authmap->save($account, 'samlauth', 'foo', [
      'mapped' => [
        'webmaster',
        'bar',
      ],
    ]);

    $this->event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new SamlauthSubscriber($this->config, $this->logger, $this->authmap, $this->entityTypeManager);
    $sut->onUserSync($this->event);
    $this->assertTrue($account->hasRole('webmaster'));
    $this->assertFalse($account->hasRole('editor'));
  }

  /**
   * Test existing user has role revoked.
   */
  public function testUserSyncRevokesRoleForExistingUser() {
    $account = $this->createUser();
    $this->authmap->save($account, 'samlauth', 'foo', [
      'mapped' => [
        'webmaster',
        'foo',
      ],
    ]);

    $attributes = [
      'name' => ['foo'],
      'groups' => [
        'DN=bogus',
        'DN=bar',
      ],
    ];

    $event = $this->createMock('Drupal\samlauth\Event\SamlauthUserSyncEvent');

    $event->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue($attributes));

    $event->expects($this->any())
      ->method('getAccount')
      ->will($this->returnValue($account));

    $sut = new SamlauthSubscriber($this->config, $this->logger, $this->authmap, $this->entityTypeManager);
    $sut->onUserSync($event);
    $this->assertFalse($account->hasRole('webmaster'));
    $this->assertFalse($account->hasRole('editor'));
  }

  /**
   * Test unlinked account does not get created.
   */
  public function testUserLinkFailsForUnlinkedAccountWithNoMappings() {
    $sut = new SamlauthSubscriber($this->config, $this->logger, $this->authmap, $this->entityTypeManager);
    $event = $this->createMock('Drupal\samlauth\Event\SamlauthUserLinkEvent');

    $event->expects($this->any())
      ->method('getAttributes')
      ->will($this->returnValue([
        'name' => ['foo'],
        'groups' => [],
      ]));

    $event->expects($this->any())
      ->method('getLinkedAccount')
      ->will($this->returnValue(NULL));

    $this->expectException('Drupal\externalauth\Exception\ExternalAuthRegisterException');
    $sut->onUserLink($event);
  }

}
