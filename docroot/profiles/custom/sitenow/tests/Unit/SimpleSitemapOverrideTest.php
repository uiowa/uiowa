<?php

namespace Drupal\Tests\sitenow\Unit;

use Drupal\sitenow\ConfigOverride\SimpleSitemapOverride;
use Drupal\Tests\UnitTestCase;

/**
 * Simple sitemap config override test.
 *
 * @group unit
 */
class SimpleSitemapOverrideTest extends UnitTestCase {
  /**
   * RequestStack mock.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Request mock.
   *
   * @var \Symfony\Component\HttpFoundation\Request|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
  public function testConfigByEnv($host) {
    $this->request->expects($this->any())
      ->method('getHost')
      ->will($this->returnValue($host));

    $sut = new SimpleSitemapOverride($this->requestStack);

    $overrides = $sut->loadOverrides(['simple_sitemap.settings']);
    $this->assertEquals($overrides['simple_sitemap.settings']['base_url'], "https://{$host}");
  }

  /**
   * DataProvider for testConfigByEnv().
   */
  public function providerConfigByEnv() {
    return [
      [
        'www.foo.com',
      ],
      [
        'foo.io',
      ],
      [
        'baz.bar.foo.com',
      ],
    ];
  }

}
