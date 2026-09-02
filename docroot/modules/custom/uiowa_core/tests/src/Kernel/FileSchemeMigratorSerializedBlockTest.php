<?php

namespace Drupal\Tests\uiowa_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;

/**
 * Tests rewriting the paths inside a serialized inline block.
 *
 * Layout builder carries an unsaved inline block as a serialized entity, which
 * only a real one exercises: the rewrite is refused unless the payload names
 * nothing but BlockContent and an untouched decode re-encodes byte for byte.
 *
 * @group uiowa_core
 * @coversDefaultClass \Drupal\uiowa_core\FileSchemeMigrator
 */
class FileSchemeMigratorSerializedBlockTest extends KernelTestBase {

  /**
   * The path a migration would break.
   */
  const PATH = '/sites/example.uiowa.edu/files/';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field',
    'file',
    'filter',
    'system',
    'text',
    'uiowa_core',
    'user',
  ];

  /**
   * The migrator under test.
   *
   * @var \Drupal\uiowa_core\FileSchemeMigrator
   */
  protected $migrator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installConfig(['block_content', 'filter']);

    BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic block',
    ])->save();
    block_content_add_body_field('basic');

    $this->migrator = $this->container->get('uiowa_core.file_scheme_migrator');
  }

  /**
   * @covers ::rewriteSerializedEntity
   */
  public function testRewritesPathInsideSerializedBlock(): void {
    $value = serialize($this->block('<img src="' . self::PATH . 'a.jpg">'));

    $out = $this->rewrite($value);

    $this->assertNotSame($value, $out, 'A block holding a moved path must be rewritten.');

    $block = unserialize($out, ['allowed_classes' => [BlockContent::class]]);

    $this->assertInstanceOf(BlockContent::class, $block, 'The rewritten payload must still unserialize.');
    $this->assertSame(
      '<img src="/system/files/a.jpg">',
      $block->get('body')->value,
      'The body must hold the destination path.'
    );
    $this->assertSame('A block', $block->label(), 'Values with no path must survive the round trip.');
  }

  /**
   * @covers ::rewriteSerializedEntity
   */
  public function testLeavesBlockWithNoMovedPathAlone(): void {
    $value = serialize($this->block('<img src="' . self::PATH . 'elsewhere.jpg">'));

    $this->assertSame($value, $this->rewrite($value), 'A path that did not move must come back untouched.');
  }

  /**
   * @covers ::safeUnserialize
   */
  public function testRefusesPayloadNamingAnotherClass(): void {
    $value = serialize(new \ArrayObject([self::PATH . 'a.jpg']));

    $this->assertSame($value, $this->rewrite($value), 'Only a BlockContent payload may be decoded.');
  }

  /**
   * @covers ::rewriteRecursive
   */
  public function testRoutesSerializedBlocksOutOfThePlainTextRewrite(): void {
    // Rewriting the payload as text would shorten it without fixing the
    // s:<length> prefixes, and the layout fatals the next time it is opened.
    $configuration = [
      'id' => 'inline_block:basic',
      'block_serialized' => serialize($this->block('<img src="' . self::PATH . 'a.jpg">')),
      'label' => 'See ' . self::PATH . 'a.jpg',
    ];

    $out = $this->invoke('rewriteRecursive', [$configuration, $this->context()]);
    $block = unserialize($out['block_serialized'], ['allowed_classes' => [BlockContent::class]]);

    $this->assertInstanceOf(BlockContent::class, $block, 'The rewritten payload must still unserialize.');
    $this->assertSame('<img src="/system/files/a.jpg">', $block->get('body')->value);

    // A plain string alongside it still goes through the text rewrite.
    $this->assertSame('See /system/files/a.jpg', $out['label']);
  }

  /**
   * Builds an unsaved block carrying the given body.
   *
   * @param string $body
   *   The body value.
   *
   * @return \Drupal\block_content\Entity\BlockContent
   *   The block.
   */
  protected function block(string $body): BlockContent {
    return BlockContent::create([
      'type' => 'basic',
      'info' => 'A block',
      'body' => ['value' => $body, 'format' => 'plain_text'],
    ]);
  }

  /**
   * Runs a serialized value through the rewrite.
   *
   * @param string $value
   *   The serialized value.
   *
   * @return string
   *   What the migrator would store.
   */
  protected function rewrite(string $value): string {
    return $this->invoke('rewriteSerializedEntity', [$value, $this->context()]);
  }

  /**
   * Builds the rewrite context for a single moved file.
   *
   * @return array
   *   The context ::rewriteReferences() would pass down.
   */
  protected function context(): array {
    return [
      'map' => [self::PATH . 'a.jpg' => '/system/files/a.jpg'],
      'targets' => ['a.jpg' => TRUE],
      'from' => 'public',
      'to' => 'private',
      'styles_from' => self::PATH . 'styles/',
      'styles_to' => '/system/files/styles/',
    ];
  }

  /**
   * Calls a protected method on the migrator.
   *
   * @param string $method
   *   The method name.
   * @param array $args
   *   The arguments.
   *
   * @return mixed
   *   Whatever the method returned.
   */
  protected function invoke(string $method, array $args): mixed {
    $ref = new \ReflectionMethod($this->migrator, $method);
    $ref->setAccessible(TRUE);

    return $ref->invokeArgs($this->migrator, $args);
  }

}
