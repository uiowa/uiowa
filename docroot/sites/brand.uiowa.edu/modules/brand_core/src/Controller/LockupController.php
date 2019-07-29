<?php

namespace Drupal\brand_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brand_core\EasySVG as EasySVG;

/**
 * Generates Lockup.
 */
class LockupController extends ControllerBase {

  /**
   * Generate Lockup.
   */
  public function generate($nid) {
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $path = $node->getTitle();
    $lockup_stacked_black = LockupController::generateLockup($node, '#000000', "#000000", 'stacked');
    $lockup_stacked_black_file = $path . ' LockupStacked-BLACK.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_black_file, 'w'), $lockup_stacked_black);

    $lockup_stacked_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'stacked');
    $lockup_stacked_rgb_file = $path . ' LockupStacked-RGB.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_rgb_file, 'w'), $lockup_stacked_rgb);

    $lockup_stacked_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'stacked');
    $lockup_stacked_reversed_file = $path . ' LockupStacked-REVERSED.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_reversed_file, 'w'), $lockup_stacked_reversed);

    $lockup_horizontal_black = LockupController::generateLockup($node, '#000000', "#000000", 'horizontal');
    $lockup_horizontal_black_file = $path . ' LockupHorizontal-BLACK.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_black_file, 'w'), $lockup_horizontal_black);

    $lockup_horizontal_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'horizontal');
    $lockup_horizontal_rgb_file = $path . ' LockupHorizontal-RGB.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, 'w'), $lockup_horizontal_rgb);

    $lockup_horizontal_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'horizontal');
    $lockup_horizontal_reversed_file = $path . ' LockupHorizontal-REVERSED.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, 'w'), $lockup_horizontal_reversed);

    $zip = new \ZipArchive();

    $zip_filename = 'temporary://lockup.zip';

    if ($zip->open(\Drupal::service('file_system')->realpath($zip_filename), \ZipArchive::CREATE) !== TRUE) {
      exit("cannot open <$zip_filename>\n");
    }

    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_black_file, $path . "-Lockup/" . $lockup_stacked_black_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_rgb_file, $path . "-Lockup/" . $lockup_stacked_rgb_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_reversed_file, $path . "-Lockup/" . $lockup_stacked_reversed_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_black_file, $path . "-Lockup/" . $lockup_horizontal_black_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, $path . "-Lockup/" . $lockup_horizontal_rgb_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, $path . "-Lockup/" . $lockup_horizontal_reversed_file);

    $zip->close();

    header('Content-type: application/zip');
    header("Content-disposition: attachment; filename=" . $path . "-Lockup.zip");
    readfile(\Drupal::service('file_system')->realpath($zip_filename));

    ob_clean();
    flush();
    readfile($zip_filename);
    unlink($zip_filename);
    unlink(file_directory_temp() . '/' . $lockup_stacked_black_file);
    unlink(file_directory_temp() . '/' . $lockup_stacked_rgb_file);
    unlink(file_directory_temp() . '/' . $lockup_stacked_reversed_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_black_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_rgb_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_reversed_file);
    return [
      '#type' => 'markup',
      '#markup' => $this->t('Hello @path', ['@path' => $path]),
    ];
  }

  /**
   * Generate Lockup.
   */
  public function generateLockup($node, $iowa_color, $text_color, $type) {
    // Load all of the needed assets to create the graphics.
    $bold = drupal_get_path('module', 'brand_core') . '/fonts/RobotoBold.svg';
    $regular = drupal_get_path('module', 'brand_core') . '/fonts/RobotoRegular.svg';

    // Determine all text features needed.
    $lines = 1;
    $alt = FALSE;
    $hasSecondary = FALSE;
    $tagline = FALSE;
    $primary_text = '';
    $svg_width = 0;
    $sub_height = 0;

    $primary = new EasySVG();

    // Unit name generation.
    $primary_text = $node->field_lockup_primary_unit->value;
    $primary_explode = explode(PHP_EOL, $primary_text);
    $lines = [];
    $primary_lines = count($primary_explode);

    $primary->setFont($bold, 8, $text_color);

    foreach ($primary_explode as $line) {
      str_replace('\r', '', $line);
      $lines[] = $primary->textDimensions($line);
    }
    $max = max($lines);

    $primary_dimensions = $primary->textDimensions($primary_text);
    if ($primary_dimensions[0] > $svg_width) {
      $svg_width = $primary_dimensions[0];
    }

    $svg_half_width = $svg_width / 2;

    if (!empty($node->field_lockup_sub_unit->value)) {
      $hasSecondary = TRUE;
      $sub_text = $node->field_lockup_sub_unit->value;
      $sub_text = $node->field_lockup_sub_unit->value;
      $sub_explode = explode(PHP_EOL, $sub_text);
      $sub = new EasySVG();
    }
    switch ($type) {
      case 'stacked':
        LockupController::addStackedLogo($primary, $iowa_color);
        // Border. Width based on primary width.
        $primary->addRect([
          'x' => 683.05 - $svg_half_width - 3.528,
          'y' => '376',
          'width' => $svg_width + 7.506,
          'height' => '0.8',
          'style' => 'fill:' . $text_color,
        ]);
        // Primary Line 1.
        $primary->addText(html_entity_decode(
          $primary_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          683.05 - $svg_half_width,
          '382'
        );
        // Primary Line 2.
        $line2_midpoint = $lines[1][0] / 2;
        $primary->addText(html_entity_decode(
          $primary_explode[1],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          683.05 - $line2_midpoint,
          '392'
        );
        // Primary Line 3.
        $line3_midpoint = $lines[2][0] / 2;
        $primary->addText(html_entity_decode(
          $primary_explode[2],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          683.05 - $line3_midpoint,
          '402'
        );
        $primary->setLineHeight(9.6);
        $primary->setLetterSpacing(0);
        break;

      case 'horizontal':
        LockupController::addHorizontalLogo($primary, $iowa_color);
        // Border. Height based on primary and secondary combined height.
        $primary->addRect([
          'x' => 675.15,
          'y' => '384.4',
          'width' => 0.8,
          'height' => '30.6',
          'style' => 'fill:' . $text_color,
        ]);
        break;

    }

    $primary->addAttribute('viewBox', '0 0 1366 768');

    return $primary->asXML();
  }

  /**
   * Add Stacked Logo.
   */
  public function addStackedLogo($svg, $iowa_color) {
    $svg->addPath('M656.1,354.1h-1.7v12.4h1.7v4.5h-9.5v-4.5h1.7v-12.4h-1.7v-4.5h9.5V354.1z', ["fill" => $iowa_color]);
    $svg->addPath('M668.7,371h-6.9c-2.5,0-4.1-1.6-4.1-4.3v-12.8c-0.2-2.2,1.4-4.1,3.6-4.3c0.2,0,0.4,0,0.5,0h6.9c2.2-0.1,4,1.6,4.1,3.8   c0,0.2,0,0.4,0,0.5v12.8C672.8,369.4,671.2,371,668.7,371z M666.8,366.5v-12.4h-3v12.4H666.8z', ["fill" => $iowa_color]);
    $svg->addPath('M675.2,354.1h-1.5v-4.5h9v4.5H681l1.5,10.3l3.2-14.8h4.6l3.4,14.8l1.2-10.3h-1.6v-4.5h8.9v4.5h-1.5L698,371h-7.2l-2.8-12.6   l-2.8,12.6h-7L675.2,354.1z', ["fill" => $iowa_color]);
    $svg->addPath('M700.2,366.5h1.6l3.2-16.9h9.7l3.2,16.9h1.5v4.5h-7.2l-0.7-5.2h-3.6l-0.6,5.2h-7.1V366.5z M711.2,361.9l-1.4-8.6l-1.4,8.6   H711.2z', ["fill" => $iowa_color]);
  }

  /**
   * Add Horizontal Logo.
   */
  public function addHorizontalLogo($svg, $iowa_color) {
    $svg->addPath('M606,377h-1.7v12.4H606v4.5h-9.5v-4.5h1.7V377h-1.7v-4.5H606Z', ["fill" => $iowa_color]);
    $svg->addPath('M618.6,393.9h-6.9c-2.5,0-4.1-1.6-4.1-4.3V376.8a3.94,3.94,0,0,1,4.1-4.3h6.9a3.94,3.94,0,0,1,4.1,4.3v12.8A3.89,3.89,0,0,1,618.6,393.9Zm-1.9-4.5V377h-3v12.4Z', ["fill" => $iowa_color]);
    $svg->addPath('M625.2,377h-1.5v-4.5h9V377H631l1.5,10.3,3.2-14.8h4.6l3.4,14.8,1.2-10.3h-1.6v-4.5h8.9V377h-1.5L648,393.9h-7.2L638,381.3l-2.9,12.6h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M650.2,389.4h1.6l3.2-16.9h9.7l3.2,16.9h1.5v4.5h-7.2l-.7-5.2h-3.6l-.6,5.2h-7.1Zm11-4.6-1.4-8.6-1.4,8.6Z', ["fill" => $iowa_color]);
  }

}
