<?php

namespace Drupal\Tests\uiowa_core\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\file\FileRepositoryInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\uiowa_core\FileSchemeMigrator;
use Psr\Log\LoggerInterface;

/**
 * Tests the path detection in FileSchemeMigrator.
 *
 * A scan that silently reports nothing is worse than no scan, so these cover
 * the cases where a path is nested, serialized, or absent.
 *
 * @group uiowa_core
 * @coversDefaultClass \Drupal\uiowa_core\FileSchemeMigrator
 */
class FileSchemeMigratorTest extends UnitTestCase {

  /**
   * The path a migration would break.
   */
  const PATH = '/sites/example.uiowa.edu/files/';

  /**
   * The migrator under test.
   *
   * @var \Drupal\uiowa_core\FileSchemeMigrator
   */
  protected FileSchemeMigrator $migrator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Path detection touches none of these, but the constructor requires them.
    $this->migrator = new FileSchemeMigrator(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityFieldManagerInterface::class),
      $this->createMock(FileSystemInterface::class),
      $this->createMock(FileRepositoryInterface::class),
      $this->createMock(StreamWrapperManagerInterface::class),
      $this->createMock(Connection::class),
      $this->createMock(LoggerInterface::class),
    );
  }

  /**
   * @covers ::containsPath
   */
  public function testFindsPathNestedInConfiguration(): void {
    $configuration = [
      'label' => 'Downloads',
      'settings' => [
        'links' => [
          ['url' => 'https://example.uiowa.edu' . self::PATH . 'report.pdf'],
        ],
      ],
    ];

    $this->assertTrue($this->containsPath($configuration));
  }

  /**
   * @covers ::containsPath
   */
  public function testFindsPathInSerializedInlineBlock(): void {
    // An unsaved inline block is carried through configuration as a string.
    $configuration = [
      'block_serialized' => serialize(['body' => '<img src="' . self::PATH . 'chart.jpg">']),
    ];

    $this->assertTrue($this->containsPath($configuration));
  }

  /**
   * @covers ::containsPath
   */
  public function testIgnoresConfigurationWithoutPath(): void {
    $configuration = [
      'label' => 'Downloads',
      'settings' => ['links' => [['url' => 'https://example.uiowa.edu/about']]],
      'block_revision_id' => 42,
    ];

    $this->assertFalse($this->containsPath($configuration));
  }

  /**
   * @covers ::containsPath
   */
  public function testIgnoresNonStringLeaves(): void {
    $this->assertFalse($this->containsPath(['weight' => 0, 'enabled' => TRUE, 'empty' => NULL]));
  }

  /**
   * @covers ::matchingComponents
   */
  public function testReportsMatchingComponentWithBlockRevision(): void {
    $section = new Section('layout_onecol', [], [
      new SectionComponent('uuid-1', 'content', [
        'id' => 'inline_block:uiowa_text_area',
        'block_revision_id' => 99,
        'label' => 'See ' . self::PATH . 'chart.jpg',
      ]),
    ]);

    $descriptions = $this->matchingComponents($section);

    $this->assertCount(1, $descriptions);
    $this->assertStringContainsString('inline_block:uiowa_text_area', $descriptions[0]);
    $this->assertStringContainsString('99', $descriptions[0]);
  }

  /**
   * @covers ::matchingComponents
   */
  public function testMarksComponentUnsavedWhenNoBlockRevision(): void {
    $section = new Section('layout_onecol', [], [
      new SectionComponent('uuid-1', 'content', [
        'id' => 'inline_block:uiowa_text_area',
        'block_serialized' => serialize(self::PATH . 'chart.jpg'),
      ]),
    ]);

    $descriptions = $this->matchingComponents($section);

    $this->assertCount(1, $descriptions);
    $this->assertStringContainsString('unsaved', $descriptions[0]);
  }

  /**
   * @covers ::matchingComponents
   */
  public function testReportsOnlyComponentsHoldingThePath(): void {
    $section = new Section('layout_twocol', [], [
      new SectionComponent('uuid-1', 'first', [
        'id' => 'inline_block:clean',
        'block_revision_id' => 1,
        'label' => 'Nothing to see',
      ]),
      new SectionComponent('uuid-2', 'second', [
        'id' => 'inline_block:dirty',
        'block_revision_id' => 2,
        'label' => self::PATH . 'chart.jpg',
      ]),
    ]);

    $descriptions = $this->matchingComponents($section);

    $this->assertCount(1, $descriptions);
    $this->assertStringContainsString('inline_block:dirty', $descriptions[0]);
  }

  /**
   * @covers ::matchingComponents
   */
  public function testReportsNothingForCleanSection(): void {
    $section = new Section('layout_onecol', [], [
      new SectionComponent('uuid-1', 'content', [
        'id' => 'inline_block:clean',
        'block_revision_id' => 1,
      ]),
    ]);

    $this->assertSame([], $this->matchingComponents($section));
  }

  /**
   * Calls the protected containsPath() method.
   *
   * @param mixed $value
   *   The value to search.
   *
   * @return bool
   *   Whether the path was found.
   */
  protected function containsPath(mixed $value): bool {
    $method = new \ReflectionMethod($this->migrator, 'containsPath');
    $method->setAccessible(TRUE);

    return $method->invoke($this->migrator, $value, self::PATH);
  }

  /**
   * Calls the protected matchingComponents() method.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section to walk.
   *
   * @return string[]
   *   The descriptions of matching components.
   */
  protected function matchingComponents(Section $section): array {
    $method = new \ReflectionMethod($this->migrator, 'matchingComponents');
    $method->setAccessible(TRUE);

    return $method->invoke($this->migrator, $section, self::PATH);
  }

}
