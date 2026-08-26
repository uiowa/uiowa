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
   * @covers ::pathVariants
   */
  public function testPathVariantsCoverPercentEncoding(): void {
    // Real content holds the encoded form Drupal generates, so a rewrite that
    // only matched the raw path would miss it.
    $variants = $this->invoke('pathVariants', ['2024-07/July 17 [64].pdf']);

    $this->assertContains('2024-07/July 17 [64].pdf', $variants);
    $this->assertContains('2024-07/July%2017%20%5B64%5D.pdf', $variants);
  }

  /**
   * @covers ::pathVariants
   */
  public function testPathVariantsDeduplicatesWhenNothingToEncode(): void {
    $this->assertSame(['2024-07/plain.pdf'], $this->invoke('pathVariants', ['2024-07/plain.pdf']));
  }

  /**
   * @covers ::rewriteString
   */
  public function testRewritesDerivativePathsAndNotOtherFiles(): void {
    $context = $this->context();

    // A derivative carries the scheme as its own path segment, so it needs the
    // segment swapped as well as the prefix.
    $value = implode(' ', [
      '<img src="/sites/example.uiowa.edu/files/styles/medium/public/a.jpg">',
      '<a href="/sites/example.uiowa.edu/files/a.jpg">direct</a>',
      '<a href="/sites/example.uiowa.edu/files/gone.pdf">not moved</a>',
    ]);

    $out = $this->invoke('rewriteString', [$value, $context]);

    $this->assertStringContainsString('/system/files/styles/medium/private/a.jpg', $out);
    $this->assertStringContainsString('/system/files/a.jpg', $out);
    // Left alone: nothing moved it, so rewriting would only change which URL
    // returns the 404 it already returns.
    $this->assertStringContainsString('/sites/example.uiowa.edu/files/gone.pdf', $out);
  }

  /**
   * @covers ::rewriteString
   */
  public function testLeavesDerivativesOfFilesThatDidNotMove(): void {
    $context = $this->context();

    // gone.pdf is not in the moved set, so neither its direct path nor a
    // derivative built from it may be rewritten. The derivative still resolves
    // against the file where it is; repointing it would break a working image.
    $value = implode(' ', [
      '<img src="/sites/example.uiowa.edu/files/styles/medium/public/gone.jpg">',
      '<img src="/sites/example.uiowa.edu/files/styles/medium/public/a.jpg?itok=abc">',
    ]);

    $out = $this->invoke('rewriteString', [$value, $context]);

    $this->assertStringContainsString('/sites/example.uiowa.edu/files/styles/medium/public/gone.jpg', $out);
    $this->assertStringNotContainsString('/system/files/styles/medium/private/gone.jpg', $out);
    // The query string is not part of the path and must survive the rewrite.
    $this->assertStringContainsString('/system/files/styles/medium/private/a.jpg?itok=abc', $out);
  }

  /**
   * @covers ::rewriteRecursive
   * @covers ::rewriteSerializedEntity
   */
  public function testDoesNotCorruptSerializedInlineBlocks(): void {
    // Layout builder stores an unsaved inline block as a serialized entity in
    // this key. Rewriting it as plain text shortens the payload without fixing
    // the s:<length> prefixes, and the layout fatals the next time it is
    // opened. Whatever comes back has to still unserialize.
    $block = (object) ['body' => 'see /sites/example.uiowa.edu/files/a.jpg'];
    $configuration = [
      'id' => 'inline_block:quote',
      'block_serialized' => serialize($block),
      'label' => 'A quote',
    ];

    $out = $this->invoke('rewriteRecursive', [$configuration, $this->context()]);

    $this->assertNotFalse(
      unserialize($out['block_serialized'], ['allowed_classes' => ['stdClass']]),
      'The serialized inline block must still be readable after a rewrite.'
    );
    // Plain strings alongside it are still handled normally.
    $this->assertSame('A quote', $out['label']);
  }

  /**
   * @covers ::rewriteString
   */
  public function testRewriteIsReversible(): void {
    $forward = $this->context();
    $back = [
      'map' => array_flip($forward['map']),
      'targets' => $forward['targets'],
      'from' => 'private',
      'to' => 'public',
      'styles_from' => $forward['styles_to'],
      'styles_to' => $forward['styles_from'],
    ];
    $original = '<a href="/sites/example.uiowa.edu/files/a.jpg">x</a>';

    $private = $this->invoke('rewriteString', [$original, $forward]);
    $public = $this->invoke('rewriteString', [$private, $back]);

    $this->assertSame($original, $public);
  }

  /**
   * Builds a public to private rewrite context.
   *
   * Hand-built rather than derived from PublicStream, so these stay unit tests
   * with no container behind them.
   *
   * @return array
   *   A rewrite context.
   */
  protected function context(): array {
    return [
      'map' => ['/sites/example.uiowa.edu/files/a.jpg' => '/system/files/a.jpg'],
      'targets' => ['a.jpg' => TRUE],
      'from' => 'public',
      'to' => 'private',
      'styles_from' => '/sites/example.uiowa.edu/files/styles/',
      'styles_to' => '/system/files/styles/',
    ];
  }

  /**
   * Calls a protected method on the migrator.
   *
   * @param string $method
   *   The method name.
   * @param array $args
   *   Positional arguments.
   *
   * @return mixed
   *   Whatever the method returns.
   */
  protected function invoke(string $method, array $args): mixed {
    $ref = new \ReflectionMethod($this->migrator, $method);
    $ref->setAccessible(TRUE);

    return $ref->invokeArgs($this->migrator, $args);
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
