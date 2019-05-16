<?php

namespace Drupal\Tests\sitenow\Unit\ConfigOverride;

use Drupal\Tests\UnitTestCase;
use Drupal\sitenow\ConfigOverride\AcquiaConnectorOverride;

/**
 * Test description.
 *
 * @group unit
 */
class AcquiaConnectorOverrideTest extends UnitTestCase {

  protected $requestStack;
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->requestStack = $this->createMock('\Symfony\Component\HttpFoundation\RequestStack');
    $this->request = $this->createMock('\Symfony\Component\HttpFoundation\Request');

    $this->requestStack->expects($this->any())
      ->method('getCurrentRequest')
      ->will($this->returnValue($this->request));
  }

  /**
   * Test config overrides for different environment variables.
   *
   * @dataProvider providerConfigByEnv
   */
  public function testConfigByEnv($env, $use_cron, $host) {
    $this->request->expects($this->any())
      ->method('getHost')
      ->will($this->returnValue($host));

    $sut = new AcquiaConnectorOverride($this->requestStack);
    putenv('AH_PRODUCTION=' . $env);

    $overrides = $sut->loadOverrides(['acquia_connector.settings']);
    $this->assertEquals($overrides['acquia_connector.settings']['spi']['use_cron'], $use_cron);
    $this->assertEquals($overrides['acquia_connector.settings']['site_name'], $host);
    $this->assertEquals($overrides['acquia_connector.settings']['hide_signup_messages'], TRUE);
  }

  /**
   * DataProvider for testConfigByEnv().
   */
  public function providerConfigByEnv() {
    return [
      [
        NULL,
        FALSE,
        'www.foo.com',
      ],
      [
        NULL,
        FALSE,
        'foo.io',
      ],
      [
        1,
        TRUE,
        'baz.bar.foo.com',
      ],
    ];
  }

}
