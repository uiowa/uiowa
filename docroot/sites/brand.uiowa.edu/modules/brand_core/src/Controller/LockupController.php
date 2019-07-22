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

    $lockup_stacked_cmyk = LockupController::generateLockup($node, '#FFD600', "#000000", 'stacked');
    $lockup_stacked_cmyk_file = $path . ' LockupStacked-CMYK.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_cmyk_file, 'w'), $lockup_stacked_cmyk);

    $lockup_stacked_reversed = LockupController::generateLockup($node, '#FFFFFF', "#FFFFFF", 'stacked');
    $lockup_stacked_reversed_file = $path . ' LockupStacked-REVERSED.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_reversed_file, 'w'), $lockup_stacked_reversed);

    $lockup_horizontal_black = LockupController::generateLockup($node, '#000000', "#000000", 'horizontal');
    $lockup_horizontal_black_file = $path . ' LockupHorizontal-BLACK.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_black_file, 'w'), $lockup_horizontal_black);

    $lockup_horizontal_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'horizontal');
    $lockup_horizontal_rgb_file = $path . ' LockupHorizontal-RGB.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, 'w'), $lockup_horizontal_rgb);

    $lockup_horizontal_cmyk = LockupController::generateLockup($node, '#FFCD00', "#000000", 'horizontal');
    $lockup_horizontal_cmyk_file = $path . ' LockupHorizontal-CMYK.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_cmyk_file, 'w'), $lockup_horizontal_cmyk);

    $lockup_horizontal_reversed = LockupController::generateLockup($node, '#FFFFFF', "#FFFFFF", 'horizontal');
    $lockup_horizontal_reversed_file = $path . ' LockupHorizontal-REVERSED.svg';
    fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, 'w'), $lockup_horizontal_reversed);

    $zip = new \ZipArchive();

    $zip_filename = 'temporary://lockup.zip';

    if ($zip->open(\Drupal::service('file_system')->realpath($zip_filename), \ZipArchive::CREATE) !== TRUE) {
      exit("cannot open <$zip_filename>\n");
    }

    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_black_file, $path . "-Lockup/" . $lockup_stacked_black_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_rgb_file, $path . "-Lockup/" . $lockup_stacked_rgb_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_cmyk_file, $path . "-Lockup/" . $lockup_stacked_cmyk_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_reversed_file, $path . "-Lockup/" . $lockup_stacked_reversed_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_black_file, $path . "-Lockup/" . $lockup_horizontal_black_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, $path . "-Lockup/" . $lockup_horizontal_rgb_file);
    $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_cmyk_file, $path . "-Lockup/" . $lockup_horizontal_cmyk_file);
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
    unlink(file_directory_temp() . '/' . $lockup_stacked_cmyk_file);
    unlink(file_directory_temp() . '/' . $lockup_stacked_reversed_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_black_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_rgb_file);
    unlink(file_directory_temp() . '/' . $lockup_horizontal_cmyk_file);
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

    if (!empty($node->field_lockup_sub_unit->value)) {
      $hasSecondary = TRUE;
    }

    // Unit name generation.
    $primary_text = $node->field_lockup_primary_unit->value;
    $sub_text = $node->field_lockup_sub_unit->value;

    $primary = new EasySVG();
    $sub = new EasySVG();

    $primary->setFont($bold, 8, $text_color);
    $primary_dimensions = $primary->textDimensions($primary_text);
    if ($primary_dimensions[0] > $svg_width) {
      $svg_width = $primary_dimensions[0];
    }
    $svg_half_width = $svg_width / 2;

    switch ($type) {
      case 'stacked':
        LockupController::addStackedLogo($primary, $iowa_color);
        $primary->addRect([
          'x' => 680.91 - $svg_half_width - 3.528,
          'y' => '376',
          'width' => $svg_width + 7.506,
          'height' => '0.8',
        ]);
        $primary->addText(html_entity_decode(
          $primary_text,
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          680.91 - $svg_half_width,
          '382'
        );
        $primary->setLineHeight(9.6);
        $primary->setLetterSpacing(0);
        break;

      case 'horizontal':
        LockupController::addHorizontalLogo($primary, $iowa_color);
        $primary->addRect([
          'x' => 675.15,
          'y' => '384.4',
          'width' => 0.8,
          'height' => '30.6'
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
    $svg->addPath('M653.9,354.1h-1.7v12.4h1.7V371h-9.5v-4.5h1.7V354.1h-1.7v-4.5h9.5Z', ["fill" => $iowa_color]);
    $svg->addPath('M666.5,371h-6.9c-2.5,0-4.1-1.6-4.1-4.3V353.9a3.94,3.94,0,0,1,4.1-4.3h6.9a3.94,3.94,0,0,1,4.1,4.3v12.8C670.6,369.4,669,371,666.5,371Zm-1.9-4.5V354.1h-3v12.4Z', ["fill" => $iowa_color]);
    $svg->addPath('M673,354.1h-1.5v-4.5h9v4.5h-1.7l1.5,10.3,3.2-14.8h4.6l3.4,14.8,1.2-10.3h-1.6v-4.5H700v4.5h-1.5L695.8,371h-7.2l-2.8-12.6L683,371h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M698,366.5h1.6l3.2-16.9h9.7l3.2,16.9h1.5V371H710l-.7-5.2h-3.6l-.6,5.2H698Zm11-4.6-1.4-8.6-1.4,8.6Z', ["fill" => $iowa_color]);
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
