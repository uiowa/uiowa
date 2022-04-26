<?php

namespace Drupal\Tests\uiowa_auth\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\uiowa_auth\RoleMappings;

/**
 * Tests for RoleMappings utility class.
 */
class RoleMappingsTest extends UnitTestCase {

  /**
   * Test testTextToArray.
   */
  public function testTextToArray() {
    $mappings = 'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz ' . PHP_EOL . ' webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar' . PHP_EOL . 'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux';
    $this->assertEquals(RoleMappings::textToArray($mappings), [
      'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz',
      'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar',
      'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux',
    ]);
  }

  /**
   * Test testArrayToText.
   */
  public function testArrayToText() {
    $mappings = [
      'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz',
      'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar',
      'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux',
    ];

    $this->assertEquals(RoleMappings::arrayToText($mappings), 'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz' . PHP_EOL . 'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar' . PHP_EOL . 'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux');
  }

  /**
   * Test generate.
   */
  public function testGenerate() {
    $mappings = [
      'admin|urn:oid:1.2.34.5|CN=foo,OU=bar,OU=baz',
      'webmaster|urn:oid:1.2.34.5|CN=foo,OU=bar',
      'webmaster|urn:oid:1.2.34.56789.10|CN=baz,OU=qux',
    ];

    $expected = [
      [
        'rid' => 'admin',
        'attr' => 'urn:oid:1.2.34.5',
        'value' => 'CN=foo,OU=bar,OU=baz',
      ],
      [
        'rid' => 'webmaster',
        'attr' => 'urn:oid:1.2.34.5',
        'value' => 'CN=foo,OU=bar',
      ],
      [
        'rid' => 'webmaster',
        'attr' => 'urn:oid:1.2.34.56789.10',
        'value' => 'CN=baz,OU=qux',
      ],
    ];

    $i = 0;

    foreach (RoleMappings::generate($mappings) as $mapping) {
      $this->assertEquals($expected[$i]['rid'], $mapping['rid']);
      $this->assertEquals($expected[$i]['attr'], $mapping['attr']);
      $this->assertEquals($expected[$i]['value'], $mapping['value']);
      $i++;
    }
  }

}
