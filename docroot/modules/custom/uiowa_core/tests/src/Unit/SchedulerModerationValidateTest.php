<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\node\NodeInterface;

/**
 * Tests uiowa_core_scheduler_moderation_validate() against issue #9924.
 *
 * @group uiowa_core
 */
class SchedulerModerationValidateTest extends UnitTestCase {

  /**
   * Shape the datetime widget returns for a blank field.
   *
   * @var array
   */
  protected const BLANK_WIDGET_VALUE = [
    'date' => '',
    'time' => '',
    'object' => NULL,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if (!function_exists('uiowa_core_scheduler_moderation_validate')) {
      require_once __DIR__ . '/../../../uiowa_core.module';
    }

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1000);

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->method('getCurrentLanguage')->willReturn(new \Drupal\Core\Language\Language(['id' => 'en']));

    $container = new ContainerBuilder();
    $container->set('datetime.time', $time);
    $container->set('messenger', $this->createMock(MessengerInterface::class));
    $container->set('language_manager', $language_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Confirms this shape is what broke bare strtotime() in production.
   */
  public function testMalformedShapeBreaksBareStrtotime(): void {
    $this->expectException(\TypeError::class);
    // @phpstan-ignore-next-line argument.type
    strtotime(self::BLANK_WIDGET_VALUE);
  }

  /**
   * @dataProvider publishOnValueProvider
   */
  public function testNoExceptionForVariousPublishOnShapes(string $case): void {
    $publish_on_value = match ($case) {
      'blank_widget_array' => self::BLANK_WIDGET_VALUE,
      'date_only_array' => ['date' => '2020-01-01', 'time' => '', 'object' => NULL],
      'drupal_date_time' => new DrupalDateTime('2020-01-01 00:00:00'),
      'plain_string' => '2020-01-01T00:00:00',
      'null' => NULL,
    };

    $form_state = $this->buildFormState($publish_on_value);
    uiowa_core_scheduler_moderation_validate([], $form_state);
    $this->assertTrue(TRUE, 'uiowa_core_scheduler_moderation_validate() did not throw.');
  }

  /**
   * Data provider of publish_on[0]['value'] shapes seen in the wild.
   */
  public static function publishOnValueProvider(): array {
    return [
      'blank widget array (the reported crash)' => ['blank_widget_array'],
      'array with only a date filled in' => ['date_only_array'],
      'DrupalDateTime object' => ['drupal_date_time'],
      'plain string' => ['plain_string'],
      'null' => ['null'],
    ];
  }

  /**
   * Builds a FormState wired up like a submitted node edit form.
   */
  protected function buildFormState($publish_on_value): FormState {
    $node = $this->createMock(NodeInterface::class);
    $node->method('hasField')->with('moderation_state')->willReturn(TRUE);
    $node->method('isNew')->willReturn(FALSE);
    $node->method('label')->willReturn('Test article');
    // Silence the dynamic-property deprecation from setting a magic
    // property on the mock.
    set_error_handler(static fn () => TRUE, E_DEPRECATED);
    $node->moderation_state = (object) ['value' => 'draft'];
    restore_error_handler();

    $form_object = $this->createMock(EntityFormInterface::class);
    $form_object->method('getEntity')->willReturn($node);

    $form_state = new FormState();
    $form_state->setFormObject($form_object);
    $form_state->setValue('moderation_state', [['value' => 'published']]);
    $form_state->setValue('publish_on', [['value' => $publish_on_value]]);

    return $form_state;
  }

}
