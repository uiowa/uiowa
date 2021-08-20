<?php

namespace Drupal\Tests\uiowa_maui\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\uiowa_maui\Form\AcademicDatesForm;

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
   *
   * @var \Drupal\uiowa_maui\MauiApi|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $maui;

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
          'date' => '1/1/2021',
        ],
        (object) [
          'name' => 'bar',
          'date' => '1/2/2021',
        ],
      ]));

  }

  /**
   * Test form build without prefilters.
   */
  public function testFormBuildWithoutPrefilters() {
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
  public function testFormBuildWithPrefilters($session_prefilter) {
    $sut = new AcademicDatesForm($this->maui);
    $form_state = new FormState();

    $form = $sut->buildForm([], $form_state, $session_prefilter, 'foo');

    $this->assertArrayNotHasKey('session', $form);
    $this->assertArrayNotHasKey('category', $form);
  }

  /**
   * Test form build with prefilters.
   */
  public function testFormBuildWithFormState() {
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
  public function testFormIdsDifferPerForm() {
    $one = new AcademicDatesForm($this->maui);
    $two = new AcademicDatesForm($this->maui);

    $this->assertNotEquals($one->getFormId(), $two->getFormId());
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

}
