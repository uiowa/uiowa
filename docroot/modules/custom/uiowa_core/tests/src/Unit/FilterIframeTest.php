<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_core\Plugin\Filter\FilterIframe;

/**
 * Test iframe filter.
 *
 * @group uiowa_core
 */
class FilterIframeTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
  }

  /**
   * Tests that iframes are stripped from HTML text.
   */
  public function testIframesRemovedIfNotAllowed() {
    $configuration['settings'] = [
      'allowed_sources' => "https://foo.com/bar\r\nhttps://baz.me/qux",
    ];

    $sut = new FilterIframe($configuration, 'filter_iframe', ['provider' => 'uiowa_core']);
    $sut->setStringTranslation($this->getStringTranslationStub());

    $text = <<<EOD
<div class="foo">
<a href="#bar">Baz</a>
<iframe bogus="yes"></iframe>
<iframe src="https://bogus.com" allow="video microphone"></iframe>
<iframe src="https://foo.com/bar?baz=qux"></iframe>
<iframe src="http://foo.com/bar?nohttps=yes"></iframe>
<iframe src="https://baz.me/qux?quux=bar&foo=bar"></iframe>
</div>
EOD;

    $processed = $sut->process($text, 'en')->getProcessedText();

    // These should not be present.
    $this->assertStringNotContainsString('<iframe bogus="yes"></iframe>', $processed);
    $this->assertStringNotContainsString('<iframe src="https://bogus.com" allow="video microphone"></iframe>', $processed);
    $this->assertStringNotContainsString('<iframe src="http://foo.com/bar?nohttps=yes"></iframe>', $processed);

    // These should.
    $this->assertStringContainsString('<iframe src="https://foo.com/bar?baz=qux" loading="lazy" seamless="seamless" sandbox="allow-same-origin allow-scripts allow-popups" title="Embedded content from foo.com"></iframe>', $processed);
    $this->assertStringContainsString('<iframe src="https://baz.me/qux?quux=bar&amp;foo=bar" loading="lazy" seamless="seamless" sandbox="allow-same-origin allow-scripts allow-popups" title="Embedded content from baz.me"></iframe>', $processed);
  }

}
