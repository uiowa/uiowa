<?php

namespace Drupal\Tests\uiowa_maui\Kernel;

use Drupal\Core\Form\FormState;
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

  /**
   * Mock MAUI service.
   *
   * @var \Drupal\uiowa_maui\MauiApi|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $maui;

  /**
   * Mock FormBuilder service.
   *
   * @var \Drupal\Core\Form\FormBuilder|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formBuilder;

  /**
   * Fake block plugin configuration.
   *
   * @var string[]
   */
  protected $plugin;

  /**
   * Shared initial config for the block constructor.
   *
   * @var array
   */
  protected $blockConfig;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->plugin = [
      'admin_label' => 'Academic dates',
      'provider' => 'uiowa_maui',
      'category' => 'MAUI',
    ];

    $this->blockConfig = [
      'headline' => 'Test',
      'hide_headline' => FALSE,
      'heading_size' => 'h3',
      'headline_style' => 'default',
      'session' => 0,
      'category' => '',
      'items_to_display' => 10,
      'limit_dates' => 0,
      'display_more_link' => 'https://registrar.uiowa.edu/academic-calendar',
      'display_more_text' => 'View more',
    ];

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
  public function testHeadlinePlaceholderIsReplaced($placeholder) {
    $config = $this->blockConfig;
    $config['headline'] = $placeholder;
    $sut = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);

    $build = $sut->build();
    $this->assertStringContainsString('Winter 2020', $build['heading']['#headline']);
  }

  /**
   * Test headline placeholder.
   */
  public function testHeadlinePlaceholderCannotBeUsedWithExposedSession() {
    $sut = new AcademicDatesBlock([], 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $form_state = new FormState();

    $form_state->setValues([
      'headline' => [
        'container' => [
          'headline' => 'foo bar @session baz',
        ],
      ],
      'session' => '',
      'category' => '',
    ]);

    $sut->blockValidate([], $form_state);
    $this->assertTrue($form_state->hasAnyErrors());
  }

  /**
   * Test empty session and category select options are saved as NULL.
   */
  public function testEmptyValuesSavedAsNull() {
    $sut = new AcademicDatesBlock([], 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);

    $form_state = new FormState();

    $form_state->setValues([
      'headline' => [
        'container' => [
          'headline' => 'Foo',
        ],
      ],
      'session' => '',
      'category' => '',
    ]);

    $sut->blockSubmit([], $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
    $config = $sut->getConfiguration();
    $this->assertEquals(NULL, $config['session']);
    $this->assertEquals(NULL, $config['category']);
  }

  /**
   * Test selected values for session and category.
   */
  public function testNonEmptyValuesSavedAsNotNull() {
    $sut = new AcademicDatesBlock([], 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $form_state = new FormState();

    $form_state->setValues([
      'headline' => [
        'container' => [
          'headline' => 'Foo',
        ],
      ],
      'session' => 0,
      'category' => 1,
    ]);

    $sut->blockSubmit([], $form_state);
    $this->assertFalse($form_state->hasAnyErrors());
    $config = $sut->getConfiguration();
    $this->assertEquals(0, $config['session']);
    $this->assertEquals(1, $config['category']);
  }

  /**
   * The more link should render if limiting dates and link provided.
   */
  function testMoreLinkDoesRenderIfSet() {
    $config = $this->blockConfig;
    $config['limit_dates'] = 1;
    $config['display_more_link'] = 'https://registrar.uiowa.edu/academic-calendar';
    $config['display_more_text'] = 'View more';

    $sut = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $build = $sut->build();
    $this->assertArrayHasKey('more_link', $build);
  }

  /**
   * The more link should not render if not limiting dates.
   */
  function testMoreLinkDoesNotRenderIfNotSet() {
    $config = $this->blockConfig;
    $config['limit_dates'] = 0;
    $config['display_more_link'] = 'https://registrar.uiowa.edu/academic-calendar';
    $config['display_more_text'] = 'View more';

    $sut = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $build = $sut->build();
    $this->assertArrayNotHasKey('more_link', $build);
  }

  /**
   * The more link should not render if limiting dates but the link is blank.
   */
  function testMoreLinkDoesNotRenderIfLimitSetButLinkEmpty() {
    $config = $this->blockConfig;
    $config['limit_dates'] = 1;
    $config['display_more_link'] = '';
    $config['display_more_text'] = 'View more';
    $sut = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $build = $sut->build();
    $this->assertArrayNotHasKey('more_link', $build);
  }

  /**
   * Data provider.
   */
  public function placeholderProvider() {
    return [
      ['@session'],
      ['@session Deadlines'],
      ['Upcoming Deadlines, @session'],
      ['@foo @bar @session'],
    ];
  }

}
