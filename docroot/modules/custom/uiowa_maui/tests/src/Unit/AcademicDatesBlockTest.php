<?php

namespace Drupal\Tests\uiowa_maui\Unit;

use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_maui\MauiApi;
use Drupal\uiowa_maui\Plugin\Block\AcademicDatesBlock;

/**
 * Tests the AcademicDatesBlock build output and configuration handling.
 *
 * @group uiowa_maui
 * @coversDefaultClass \Drupal\uiowa_maui\Plugin\Block\AcademicDatesBlock
 */
class AcademicDatesBlockTest extends UnitTestCase {

  /**
   * Mock MAUI service.
   *
   * @var \Drupal\uiowa_maui\MauiApi|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $maui;

  /**
   * Mock FormBuilder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $formBuilder;

  /**
   * Fake block plugin definition.
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
      'limit_dates' => FALSE,
      'display_more_link' => FALSE,
      'more_link' => 'https://registrar.uiowa.edu/academic-calendar',
      'more_text' => 'View more',
    ];

    $this->maui = $this->createMock(MauiApi::class);

    $this->formBuilder = $this->createMock(FormBuilderInterface::class);
    $this->formBuilder->method('getForm')->willReturn([]);
  }

  /**
   * Builds the block under test with a translation stub attached.
   *
   * @param array $config
   *   The block configuration. Every other constructor argument is identical
   *   across tests, so only the varying config is passed in.
   *
   * @return \Drupal\uiowa_maui\Plugin\Block\AcademicDatesBlock
   *   The configured block.
   */
  protected function makeBlock(array $config): AcademicDatesBlock {
    $block = new AcademicDatesBlock($config, 'uiowa_maui_academic_dates', $this->plugin, $this->maui, $this->formBuilder);
    $block->setStringTranslation($this->getStringTranslationStub());
    return $block;
  }

  /**
   * Tests that the @session placeholder is replaced and nothing else is.
   *
   * @covers ::build
   * @dataProvider placeholderProvider
   */
  public function testHeadlinePlaceholderIsReplaced(string $placeholder, string $expected): void {
    $this->maui->method('getSessionsBounded')->willReturn([
      (object) ['id' => 1, 'shortDescription' => 'Winter 2020'],
      (object) ['id' => 2, 'shortDescription' => 'Spring 2021'],
      (object) ['id' => 3, 'shortDescription' => 'Summer 2021'],
    ]);

    $config = $this->blockConfig;
    $config['headline'] = $placeholder;
    $sut = $this->makeBlock($config);

    $build = $sut->build();
    $this->assertSame($expected, $build['heading']['#headline']);
  }

  /**
   * Tests validation rejects the @session placeholder with an exposed session.
   *
   * @covers ::blockValidate
   */
  public function testHeadlinePlaceholderCannotBeUsedWithExposedSession(): void {
    $sut = $this->makeBlock([]);
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
    $this->assertArrayHasKey('session', $form_state->getErrors());
  }

  /**
   * Tests empty session and category selections are saved as NULL.
   *
   * @covers ::blockSubmit
   */
  public function testEmptyValuesSavedAsNull(): void {
    $sut = $this->makeBlock([]);
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
    $this->assertEmpty($form_state->getErrors());
    $config = $sut->getConfiguration();
    $this->assertNull($config['session']);
    $this->assertNull($config['category']);
  }

  /**
   * Tests selected session and category values are preserved.
   *
   * @covers ::blockSubmit
   */
  public function testNonEmptyValuesSavedAsNotNull(): void {
    $sut = $this->makeBlock([]);
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
    $this->assertEmpty($form_state->getErrors());
    $config = $sut->getConfiguration();
    $this->assertSame(0, $config['session']);
    $this->assertSame(1, $config['category']);
  }

  /**
   * Tests the more link renders with expected text and URL when enabled.
   *
   * @covers ::build
   */
  public function testMoreLinkDoesRenderIfSet(): void {
    $config = $this->blockConfig;
    $config['display_more_link'] = TRUE;
    $config['more_link'] = 'https://registrar.uiowa.edu/academic-calendar';
    $config['more_text'] = 'View more';

    $sut = $this->makeBlock($config);
    $build = $sut->build();

    $this->assertArrayHasKey('more_link', $build);
    $this->assertSame('View more', (string) $build['more_link']['#title']);
    $this->assertSame('https://registrar.uiowa.edu/academic-calendar', $build['more_link']['#url']->getUri());
  }

  /**
   * Tests the more link is absent when disabled.
   *
   * @covers ::build
   */
  public function testMoreLinkDoesNotRenderIfNotSet(): void {
    $config = $this->blockConfig;
    $config['display_more_link'] = FALSE;

    $sut = $this->makeBlock($config);
    $build = $sut->build();

    $this->assertArrayNotHasKey('more_link', $build);
  }

  /**
   * Provides headline placeholders and their expected replacements.
   *
   * @return array
   *   Each case is [input headline, expected rendered headline].
   */
  public static function placeholderProvider(): array {
    return [
      ['@session', 'Winter 2020'],
      ['@session Deadlines', 'Winter 2020 Deadlines'],
      ['Upcoming Deadlines, @session', 'Upcoming Deadlines, Winter 2020'],
      ['@foo @bar @session', '@foo @bar Winter 2020'],
    ];
  }

}
