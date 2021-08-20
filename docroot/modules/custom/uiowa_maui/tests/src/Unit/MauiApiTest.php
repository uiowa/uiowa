<?php

namespace Drupal\Tests\uiowa_maui\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Test description.
 *
 * @group uiowa_maui
 */
class MauiApiTest extends UnitTestCase {
  /**
   * Mock maui service.
   *
   * @var \Drupal\uiowa_maui\MauiApi|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $maui;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->maui = $this->createPartialMock('\Drupal\uiowa_maui\MauiApi', ['request']);
  }

  /**
   * Initial test.
   */
  public function testGetCurrentSession() {
    $current = (object) [
      'id' => 34,
      'shortDescription' => 'Summer 2021',
    ];

    $this->maui->expects($this->any())
      ->method('request')
      ->will($this->returnValue($current));

    $data = $this->maui->getCurrentSession();
    $this->assertEquals(34, $data->id);
  }

  /**
   * Test sessions are returned in expected order.
   */
  public function testGetSessionsBounded() {
    $bounding = [
      (object) [
        'id' => 3,
        'startDate' => '12/1/2021',
      ],
      (object) [
        'id' => 1,
        'startDate' => '1/1/2021',
      ],
      (object) [
        'id' => 2,
        'startDate' => '2/1/2021',
      ],
    ];

    $this->maui->expects($this->any())
      ->method('request')
      ->will($this->returnValue($bounding));

    $data = $this->maui->getSessionsBounded();

    // Test sessions are returned in order.
    $i = 1;

    foreach ($data as $session) {
      $this->assertEquals($i, $session->id);
      $i++;
    }
  }

  /**
   * Test session dates returned in expected order with date categories.
   */
  public function testSearchSessionDates() {
    $dates = [
      (object) [
        'id' => 435,
        'dateCategoryLookups' => NULL,
      ],
      (object) [
        'id' => 234,
        'dateCategoryLookups' => '',
      ],
      (object) [
        'id' => 2,
        'dateCategoryLookups' => [
          'foo',
        ],
        'beginDate' => '12/1/2021',
        'subSession' => 'foo',
      ],
      (object) [
        'id' => 1,
        'dateCategoryLookups' => [
          'foo',
          'bar',
        ],
        'beginDate' => '12/1/2021',
      ],
      (object) [
        'id' => 3,
        'dateCategoryLookups' => [
          'bar',
        ],
        'beginDate' => '12/12/2021',
      ],
    ];

    $this->maui->expects($this->any())
      ->method('request')
      ->will($this->returnValue($dates));

    $data = $this->maui->searchSessionDates(1);
    $this->assertCount(3, $data);

    $i = 1;

    foreach ($data as $date) {
      $this->assertNotEmpty($date->dateCategoryLookups);
      $this->assertEquals($i, $date->id);
      $i++;
    }
  }

}
