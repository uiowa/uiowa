<?php

namespace Drupal\sitenow_media_wysiwyg\Commands;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for SiteNow Media WYSIWYG SVG modifications.
 */
class SitenowMediaWysiwygCommands extends DrushCommands {
  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * Modify SVG files in the brand icons directory.
   *
   * @command sitenow_media_wysiwyg:modify-svgs
   * @aliases smw-svg
   * @usage drush smw-svg
   *   Modifies SVG files in the brand icons directory.
   */
  public function modifySvgFiles() {
    try {
      $theme_path = \Drupal::theme()->getActiveTheme()->getPath();
      $original_icons_path = $theme_path . '/brand-icons/icons/';
      $modified_icons_path = $theme_path . '/assets/brand-icons/';

      // Ensure the modified icons directory exists.
      if (!file_exists($modified_icons_path)) {
        mkdir($modified_icons_path, 0755, TRUE);
      }

      // Get all SVG files in the original icons directory.
      $svg_files = glob($original_icons_path . '*-two-color.svg');

      $modified_count = 0;
      foreach ($svg_files as $original_file_path) {
        $filename = basename($original_file_path);
        $modified_file_path = $modified_icons_path . $filename;

        // Copy and modify the file.
        copy($original_file_path, $modified_file_path);
        chmod($modified_file_path, 0644);
        $this->modifySvgFile($modified_file_path);
        $modified_count++;
      }

      $this->logger('sitenow_media_wysiwyg')->notice($this->t('Modified @count SVG files.', [
        '@count' => $modified_count,
      ]));
    }
    catch (\Exception $e) {
      $this->logger('sitenow_media_wysiwyg')->error($this->t('Error modifying SVG files: @message', [
        '@message' => $e->getMessage(),
      ]));
    }
  }

  /**
   * Modify a single SVG file.
   *
   * @param string $file
   *   Path to the SVG file to modify.
   */
  protected function modifySvgFile($file) {
    if (!file_exists($file)) {
      return;
    }

    $svg_content = file_get_contents($file);

    // Remove any <text> elements from the SVG.
    $svg_content = preg_replace('/<text[^>]*>.*?<\/text>/s', '', $svg_content);

    // Modify the viewBox to expand the canvas to 90x90 and center the content.
    $svg_content = preg_replace('/viewBox="[^"]*"/', 'viewBox="-10 -10 70 70"', $svg_content);

    // Remove any existing width/height attributes first.
    $svg_content = preg_replace('/(width|height)=["\']\d+["\']/', '', $svg_content);

    // Add width and height attributes to the SVG tag if they don't exist.
    $svg_content = preg_replace('/<svg/', '<svg width="70" height="70"', $svg_content, 1);

    // Add a white background rect that fully covers the 90x90 canvas.
    if (strpos($svg_content, 'fill="white"') === FALSE) {
      $svg_content = preg_replace('/(<svg[^>]*>)/', '$1<rect x="-10" y="-10" width="70" height="70" fill="white"/>', $svg_content);
    }

    // Remove standalone stroke- attributes.
    $svg_content = preg_replace('/\s+stroke-(?=[\s\/>])/', '', $svg_content);

    // Remove any existing stroke-width attributes to avoid duplicates.
    $svg_content = preg_replace('/\s+stroke-width=["\']\d+["\']/', '', $svg_content);

    // Add stroke-width="0" to all paths and ellipses that don't already have it.
    $svg_content = preg_replace('/(<(?:path|ellipse)[^>]*?)(\s*\/>)/', '$1 stroke-width="0"$2', $svg_content);

    // Clean up any double spaces that might have been created.
    $svg_content = preg_replace('/\s+/', ' ', $svg_content);

    file_put_contents($file, $svg_content);
  }

}
