<?php

namespace Drupal\Tests\uiowa_maui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\uiowa_maui\Plugin\Block\AcademicDatesBlock;

/**
 * Test description.
 *
 * @group uiowa_maui
 */
class AcademicDatesBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['uiowa_maui'];

  protected $maui;

  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->maui = $this->getMockBuilder('\Drupal\uiowa_maui\MauiApi')
      ->disableOriginalConstructor()
      ->getMock();

    $this->maui->expects($this->any())
      ->method('getCurrentSession')
      ->will($this->returnValue(
        (object) [
          'id' => 1,
          'shortDescription' => 'Winter 2020',
        ]
    ));

    $this->maui->expects($this->any())
      ->method('getSessionsBounded')
      ->will($this->returnValue([
        (object) [
          'id' => 1,
          'shortDescription' => 'Winter 2020',
        ],
        (object) [
          'id' => 2,
          'shortDescription' => 'Spring 2021',
        ],
        (object) [
          'id' => 3,
          'shortDescription' => 'Summer 2021',
        ],
      ]));

    $this->formBuilder = $this->getMockBuilder('\Drupal\Core\Form\FormBuilder')
      ->disableOriginalConstructor()
      ->getMock();

    $this->formBuilder->expects($this->any())
      ->method('getForm')
      ->will($this->returnValue([]));
  }

  /**
   * Test headline placeholder is replaced properly.
   *
   * @dataProvider placeholderProvider
   */
  public function testHeadlinePlaceholder($placeholder) {
    $config = [
      'headline' => $placeholder,
      'hide_headline' => FALSE,
      'heading_size' => 'h3',
      'headline_style' => 'default',
      'session' => 0,
      'category' => '',
    ];

    $plugin = [
      'admin_label' => 'Academic dates',
      'provider' => 'uiowa_maui',
      'category' => 'MAUI',
    ];

    $sut = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $plugin, $this->maui, $this->formBuilder);

    $build = $sut->build();
    $this->assertStringContainsString('Winter 2020', $build['heading']['#headline']);
  }

  public function placeholderProvider() {
    return [
      ['@session'],
      ['@session Deadlines'],
      ['Upcoming Deadlines, @session'],
      ['@foo @bar @session'],
    ];
  }
}
