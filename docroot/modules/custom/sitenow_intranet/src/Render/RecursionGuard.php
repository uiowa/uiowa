<?php

namespace Drupal\sitenow_intranet\Render;

use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\media\Plugin\Filter\MediaEmbed;

/**
 * Clears the render recursion guards core keeps in static properties.
 *
 * Core detects an entity that renders itself by counting how often each
 * referenced entity has been rendered, and refuses to render it again past
 * twenty. The counters live in static properties and only ever climb: they
 * are scoped to a process, where what they describe is a single render tree.
 * A web request never notices, rendering one page before it ends. Rendering
 * hundreds of unrelated pages in one process does, because counts carried
 * over from earlier pages are indistinguishable from a cycle.
 *
 * Clearing them at the start of a render restores the scope the counters are
 * meant to have, and leaves the guard catching what it is for.
 */
class RecursionGuard {

  /**
   * The guards, keyed by the class holding them.
   *
   * @var string[]
   */
  protected const GUARDS = [
    EntityReferenceEntityFormatter::class => 'recursiveRenderDepth',
    MediaEmbed::class => 'recursiveRenderDepth',
  ];

  /**
   * Clears every guard.
   *
   * Repetition within one render tree is left alone: a page that renders the
   * same entity more than twenty times still trips the guard, and core has no
   * way to tell that apart from a cycle.
   */
  public static function reset(): void {
    foreach (static::GUARDS as $class => $name) {
      if (!class_exists($class)) {
        continue;
      }

      (new \ReflectionProperty($class, $name))->setValue(NULL, []);
    }
  }

}
