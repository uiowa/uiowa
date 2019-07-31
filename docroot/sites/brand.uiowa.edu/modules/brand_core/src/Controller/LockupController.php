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
    $alt = FALSE;
    $hasSecondary = FALSE;
    $tagline = FALSE;
    $primary_text = '';
    $sub_height = 0;

    $lockup = new EasySVG();

    $svg_center = 216;

    if (!empty($node->field_lockup_sub_unit->value)) {
      $sub_text = $node->field_lockup_sub_unit->value;
      $sub_explode = explode(PHP_EOL, $sub_text);
      $lockup->setFont($regular, 6, $text_color);
      $sub_dimensions = $lockup->textDimensions($sub_text);
      foreach ($sub_explode as $line) {
        str_replace('\r', '', $line);
        $sublines[] = $lockup->textDimensions($line);
      }
    }

    // Unit name generation.
    $primary_text = $node->field_lockup_primary_unit->value;
    $primary_explode = explode(PHP_EOL, $primary_text);
    $primary_count = count($primary_explode);
    $lockup->setFont($bold, 8, $text_color);
    $lockup->setLineHeight(9.6);
    $lockup->setLetterSpacing(.01);

    $primary_lines = [];
    foreach ($primary_explode as $key => $line) {
      $primary_explode[$key] = preg_replace('~[[:cntrl:]]~', '', $line);
      $primary_lines[] = $lockup->textDimensions($line);
    }

    $primary_dimensions = $lockup->textDimensions($primary_explode[0]);
    $primary_width = $primary_dimensions[0];
    $primary_center = $primary_width / 2;
    $horizontal_height = $primary_dimensions[1] + $sub_dimensions[1];
    $horizontal_middle = $horizontal_height / 2;

    switch ($type) {
      case 'stacked':
        LockupController::addStackedLogo($lockup, $iowa_color);
        // Border. Width based on primary width.
        $lockup->addRect([
          'x' => $svg_center - $primary_center - 3.52 - .282,
          'y' => '133.22',
          'width' => $primary_width + 7.50 - 0.37 + .474,
          'height' => '0.8',
          'style' => 'fill:' . $text_color,
        ]);
        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $primary_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $primary_center,
          142.9 - 1 - ($primary_lines[0][1] / 2)
        );
        // Primary Line 2.
        $p2_center = $primary_lines[1][0] / 2;
        $lockup->addText(html_entity_decode(
          $primary_explode[1],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $p2_center,
          152.36 - 1 - ($primary_lines[1][1] / 2)
        );
        // Primary Line 3.
        $p3_center = $primary_lines[2][0] / 2;
        $lockup->addText(html_entity_decode(
          $primary_explode[2],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $p3_center,
          161.82 - 1 - ($primary_lines[2][1] / 2)
        );

        switch ($primary_count) {
          case 1:
            $sub_y = 150.33;
            break;

          case 2:
            $sub_y = 160.28;
            break;

          case 3:
            $sub_y = 170.23;
            break;

        }
        $lockup->setFont($regular, 6, $text_color);
        $lockup->setLineHeight(7.5);
        $lockup->setLetterSpacing(0);

        $s1_center = $sublines[0][0] / 2;
        $lockup->addText(html_entity_decode(
          $sub_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $s1_center,
          $sub_y - 2
        );

        $s2_center = $sublines[1][0] / 2;
        $lockup->addText(html_entity_decode(
          $sub_explode[1],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $s2_center,
          $sub_y + 7.57 - 2
        );

        $s3_center = $sublines[2][0] / 2;
        $lockup->addText(html_entity_decode(
          $sub_explode[2],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $svg_center - $s3_center,
          $sub_y + 15.43 - 2
        );
        break;

      case 'horizontal':
        LockupController::addHorizontalLogo($lockup, $iowa_color);
        // Border. Height based on primary and secondary combined height.
        $lockup->addRect([
          'x' => 206.77,
          'y' => 144 - $horizontal_middle,
          'width' => 0.8,
          'height' => $horizontal_height,
          'style' => 'fill:' . $text_color,
        ]);

        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $primary_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          '255.126',
          144 - $horizontal_middle
        );
        break;

    }

    $lockup->addAttribute('viewBox', '0 0 432 288');

    return $lockup->asXML();
  }

  /**
   * Add Stacked Logo.
   */
  public function addStackedLogo($svg, $iowa_color) {
    $svg->addPath('M189,111.1h-1.72v12.42H189V128h-9.49v-4.5h1.72V111.1h-1.72v-4.47H189Z', ["fill" => $iowa_color]);
    $svg->addPath('M201.65,128h-6.88c-2.49,0-4.13-1.63-4.13-4.32V110.94a4,4,0,0,1,3.68-4.3,3.38,3.38,0,0,1,.45,0h6.88a4,4,0,0,1,4.14,3.86,3.38,3.38,0,0,1,0,.45V123.7a4,4,0,0,1-3.69,4.29A3.2,3.2,0,0,1,201.65,128Zm-1.94-4.5V111.1h-3v12.42Z', ["fill" => $iowa_color]);
    $svg->addPath('M208.17,111.1h-1.51v-4.47h9v4.47H214l1.54,10.29,3.2-14.76h4.56l3.42,14.76L228,111.1h-1.57v-4.47h8.87v4.47h-1.48L231.07,128h-7.24L221,115.41,218.16,128h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M233.19,123.52h1.63l3.24-16.89h9.71l3.2,16.89h1.51V128h-7.24l-.68-5.21H241l-.64,5.21h-7.12Zm11-4.62-1.39-8.63-1.41,8.63Z', ["fill" => $iowa_color]);
  }

  /**
   * Add Horizontal Logo.
   */
  public function addHorizontalLogo($svg, $iowa_color) {
    $svg->addPath('M138,137.77h-1.72V150.2H138v4.49h-9.49V150.2h1.73V137.77h-1.73V133.3H138Z', ["fill" => $iowa_color]);
    $svg->addPath('M150.64,154.69h-6.87c-2.5,0-4.13-1.63-4.13-4.31V137.62a4,4,0,0,1,4.13-4.32h6.87a4,4,0,0,1,4.13,4.32v12.76A4,4,0,0,1,150.64,154.69Zm-1.94-4.49V137.77h-3V150.2Z', ["fill" => $iowa_color]);
    $svg->addPath('M157.16,137.77h-1.51V133.3h9v4.47H163l1.54,10.3,3.2-14.77h4.56l3.43,14.77,1.23-10.3h-1.57V133.3h8.87v4.47h-1.48l-2.68,16.92h-7.24L170,142.09l-2.86,12.6h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M182.18,150.2h1.63l3.24-16.9h9.71l3.2,16.9h1.51v4.49h-7.24l-.68-5.2H190l-.65,5.2h-7.12Zm11-4.63-1.39-8.63-1.41,8.63Z', ["fill" => $iowa_color]);
  }

}
