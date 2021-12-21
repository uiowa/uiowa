<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\fragments\FragmentStorage;
use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_core\Plugin\Block\RegionContentBlock;

/**
 * Test description.
 *
 * @group uiowa_core
 */
class RegionContentBlockTest extends UnitTestCase {

  /**
   * Tests something.
   */
  public function testSomething() {
    $configuration = [
      'block_id' => 'foo',
    ];

    $config_factory = $this->getConfigFactoryStub([
      'uiowa_core.settings' => [
        'uiowa_core.region_content.foo' => 3,
      ],
    ]);

    $esi = $this->createMock(FragmentStorage::class);
    $esi->expects($this->any())
      ->method('load')
      ->will($this->returnValue(NULL));

    $evb = $this->createMock(EntityViewBuilder::class);
    $etm = $this->createMock(EntityTypeManager::class);

    $etm->expects($this->any())
      ->method('getStorage')
      ->will($this->returnValue($esi));

    $etm->expects($this->any())
      ->method('getViewBuilder')
      ->will($this->returnValue($evb));

    $sut = new RegionContentBlock($configuration, 'filter_iframe', ['provider' => 'uiowa_core'], $config_factory, $etm);
    $sut->setStringTranslation($this->getStringTranslationStub());
    $this->assertNotEmpty($sut->build());

  }

}
