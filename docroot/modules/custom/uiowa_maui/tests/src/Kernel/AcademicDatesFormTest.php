<?php

namespace Drupal\Tests\uiowa_maui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\uiowa_maui\Form\AcademicDatesForm;
use Drupal\uiowa_maui\MauiApi;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test description.
 *
 * @group uiowa_maui
 */
class AcademicDatesFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['uiowa_maui'];

  /**
   * The MAUI mock.
   */
  protected MauiApi|MockObject $maui;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
      ->method('getDateCategories')
      ->will($this->returnValue([
        'foo' => 'Foo',
        'bar' => 'Bar',
      ]));

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

    $this->maui->expects($this->any())
      ->method('searchSessionDates')
      ->will($this->returnValue([
        (object) [
          'name' => 'foo',
          'beginDate' => '1/1/2021',
          'endDate' => '1/1/2021',
          'session' => (object) [
            'shortDescription' => 'A short description',
          ],
          'dateLookup' => (object) [
            'description' => 'A description',
            'webDescription' => 'A description',
          ],
        ],
        (object) [
          'name' => 'bar',
          'beginDate' => '1/2/2021',
          'endDate' => '1/2/2021',
          'session' => (object) [
            'shortDescription' => 'A short description',
          ],
          'dateLookup' => (object) [
            'description' => 'A description',
            'webDescription' => 'A description',
          ],
        ],
      ]));

  }

  /**
   * Test form build without prefilters.
   */
  public function testFormBuildWithoutPrefilters(): void {
    $sut = new AcademicDatesForm($this->maui);
    $form_state = new FormState();

    $form = $sut->buildForm([], $form_state);

    $this->assertArrayHasKey('session', $form);
    $this->assertArrayHasKey('category', $form);
    $this->assertEquals(1, $form['session']['#default_value']);
  }

  /**
   * Test form build with prefilters.
   *
   * @dataProvider sessionPrefilterProvider
   */
  public function testFormBuildWithPrefilters($session_prefilter): void {
    $sut = new AcademicDatesForm($this->maui);
    $form_state = new FormState();

    $form = $sut->buildForm([], $form_state, $session_prefilter, 'foo');

    $this->assertArrayNotHasKey('session', $form);
    $this->assertArrayNotHasKey('category', $form);
  }

  /**
   * Test form build with prefilters.
   */
  public function testFormBuildWithFormState(): void {
    $sut = new AcademicDatesForm($this->maui);
    $form_state = new FormState();
    $form_state->setValue('session', 2);

    $form = $sut->buildForm([], $form_state, NULL, 'foo');

    $this->assertArrayHasKey('session', $form);
    $this->assertArrayNotHasKey('category', $form);
    $this->assertEquals(2, $form['session']['#default_value']);
  }

  /**
   * Test form IDs are not equal per request.
   */
  public function testFormIdsDifferPerForm(): void {
    $one = new AcademicDatesForm($this->maui);
    $two = new AcademicDatesForm($this->maui);

    $this->assertNotEquals($one->getFormId(), $two->getFormId());
  }

  /**
   * Test data is limited correctly if configured to limit.
   *
   * @dataProvider datesLimitProvider
   */
  public function testFormLimitDates($number, $limit, $expected_count): void {
    $sut = new AcademicDatesForm($this->maui);
    $form_state = new FormState();
    $form = $sut->buildForm([], $form_state, NULL, NULL, NULL, $number, $limit);
    $this->assertCount($expected_count, $form['dates-wrapper']['dates']);
  }

  /**
   * Data provider for session prefilters.
   */
  public function sessionPrefilterProvider() {
    return [
      [0],
      [1],
      [2],
    ];
  }

  /**
   * Data provider for dates limit test.
   */
  public function datesLimitProvider() {
    return [
      [0, 0, 2],
      [100, 0, 2],
      [1, 1, 1],
    ];
  }

}
