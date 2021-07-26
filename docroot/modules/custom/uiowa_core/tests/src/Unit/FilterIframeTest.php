<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Component\Utility\Html;
use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_core\Plugin\Filter\FilterIframe;

/**
 * Test iframe filter.
 *
 * @group uiowa_core
 */
class FilterIframeTest extends UnitTestCase {
  /**
   * The system under test.
   *
   * @var \Drupal\uiowa_core\Plugin\Filter\FilterIframe
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $configuration['settings'] = [
      'allowed_sources' => "https://foo.com/bar\r\nhttps://baz.me/qux",
    ];

    $this->sut = new FilterIframe($configuration, 'filter_iframe', ['provider' => 'uiowa_core']);
    $this->sut->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Test iframe removed if no src attribute.
   */
  public function testIframeRemovedIfNoSrc() {
    $text = '<iframe bogus="yes"></iframe>';
    $processed = $this->sut->process($text, 'en')->getProcessedText();
    $this->assertStringNotContainsString($text, $processed);
  }

  /**
   * Test iframe removed if no HTTPS.
   */
  public function testIframeRemovedIfNoHttps() {
    $text = '<iframe src="<iframe src="http://foo.com/bar?nohttps=yes"></iframe>">';
    $processed = $this->sut->process($text, 'en')->getProcessedText();
    $this->assertStringNotContainsString($text, $processed);
  }

  /**
   * Test iframe removed if not allowed.
   */
  public function testIframeRemovedIfNotAllowed() {
    $text = '<iframe src="https://bogus.com" allow="video microphone"></iframe>';
    $processed = $this->sut->process($text, 'en')->getProcessedText();
    $this->assertStringNotContainsString($text, $processed);
  }

  /**
   * Test iframe allowed and attributes are set.
   */
  public function testIframeAllowedAndAttributesSet() {
    $text = '<iframe src="https://baz.me/qux?quux=bar&foo=bar"></iframe>';
    $processed = $this->sut->process($text, 'en')->getProcessedText();
    $html = Html::load($processed);

    /** @var \DOMElement $iframe */
    $iframe = $html->getElementsByTagName('iframe')->item(0);

    $attributes = $this->sut->getIframeAttributes();

    foreach ($attributes as $attribute => $value) {
      $this->assertEquals($value, $iframe->getAttribute($attribute));

      // Every iframe should have a title.
      $this->assertTrue($iframe->hasAttribute('title'));
    }
  }

  /**
   * Test iframe allowed and responsive classes are set on parent div.
   *
   * @dataProvider providerDimensions
   */
  public function testIframeAllowedAndClassesSet($aspectRatio, $width, $height) {
    $text = "<iframe src='https://foo.com/bar?baz=qux' width='{$width}' height='{$height}'></iframe>";
    $processed = $this->sut->process($text, 'en')->getProcessedText();
    $html = Html::load($processed);

    /** @var \DOMElement $iframe */
    $iframe = $html->getElementsByTagName('iframe')->item(0);
    $classes = explode(' ', $iframe->parentNode->getAttribute('class'));
    $this->assertContains("embed-responsive-{$aspectRatio}", $classes);
  }

  /**
   * Data provider for testIframeAllowedAndClassesSet.
   */
  public function providerDimensions() {
    return [
      ['1by1', 500, 500],
      ['4by3', 1024, 768],
      ['16x9', 1920, 1080],
    ];
  }

}
