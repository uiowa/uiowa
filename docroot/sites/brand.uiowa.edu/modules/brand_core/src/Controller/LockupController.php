<?php

namespace Drupal\brand_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brand_core\BrandSVG;

/**
 * Generates Lockup.
 */
class LockupController extends ControllerBase {

  /**
   * Generate Lockup.
   */
  public function generate($nid) {
    $is_node = \Drupal::entityQuery('node')->condition('nid', $nid)->execute();

    if ($is_node) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

      if ($node->bundle() !== 'lockup') {
        $response = [
          '#markup' => $this->t('This is not a lockup and thus cannot be downloaded.'),
        ];
        return $response;
      }

      if ($node->get('moderation_state')->get(0)->getString() !== 'published') {
        $response = [
          '#markup' => $this->t('Content not approved for download.'),
        ];
        return $response;
      }

      $path = $node->getTitle();
      $lockup_stacked_black = LockupController::generateLockup($node, '#000000', "#000000", 'stacked');
      $lockup_stacked_black_file = $path . ' LockupStacked-BLACK.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_black_file, 'w'), $lockup_stacked_black);

      $lockup_stacked_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'stacked');
      $lockup_stacked_rgb_file = $path . ' LockupStacked-RGB.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_rgb_file, 'w'), $lockup_stacked_rgb);

      $lockup_stacked_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'stacked');
      $lockup_stacked_reversed_file = $path . ' LockupStacked-RGB-REVERSED.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_reversed_file, 'w'), $lockup_stacked_reversed);

      $lockup_horizontal_black = LockupController::generateLockup($node, '#000000', "#000000", 'horizontal');
      $lockup_horizontal_black_file = $path . ' LockupHorizontal-BLACK.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_black_file, 'w'), $lockup_horizontal_black);

      $lockup_horizontal_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'horizontal');
      $lockup_horizontal_rgb_file = $path . ' LockupHorizontal-RGB.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, 'w'), $lockup_horizontal_rgb);

      $lockup_horizontal_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'horizontal');
      $lockup_horizontal_reversed_file = $path . ' LockupHorizontal-RGB-REVERSED.svg';
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

      // Read the instructions.
      $instructions = drupal_get_path('module', 'brand_core') . '/lockup-instructions.docx';
      $zip->addFile($instructions, $path . "-Lockup/lockup-instructions.docx");

      $zip->close();

      header('Content-type: application/zip');
      header("Content-disposition: attachment; filename=" . $path . "-Lockup.zip");
      readfile(\Drupal::service('file_system')->realpath($zip_filename));

      ob_clean();
      flush();
      ob_end_flush();
      readfile($zip_filename);
      unlink($zip_filename);
      unlink(file_directory_temp() . '/' . $lockup_stacked_black_file);
      unlink(file_directory_temp() . '/' . $lockup_stacked_rgb_file);
      unlink(file_directory_temp() . '/' . $lockup_stacked_reversed_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_black_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_rgb_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_reversed_file);

      exit();

    }
    else {
      $response = [
        '#markup' => $this->t('This is not a lockup and thus cannot be downloaded.'),
      ];
      return $response;
    }
  }

  /**
   * Generate Lockup.
   */
  public function generateLockup($node, $iowa_color, $text_color, $type) {
    // Load all of the needed assets to create the graphics.
    $bold = drupal_get_path('module', 'brand_core') . '/fonts/RobotoBold.svg';
    $regular = drupal_get_path('module', 'brand_core') . '/fonts/RobotoRegular.svg';

    $lockup = new BrandSVG();

    $stacked_center = 216;
    $horizontal_center = 144;
    $p_line = 8;
    $s_line = 6;
    $s_reduce = 0;
    $p_offset = 1;

    $horizontal_sum_y = 0;

    if (!empty($node->field_lockup_sub_unit->value)) {
      $p_offset = $p_offset + 5.5;
      $s_txt = $node->field_lockup_sub_unit->value;
      $s_explode = explode(PHP_EOL, $s_txt);
      $s_count = count($s_explode);
      $s_offset = 0;
      switch ($s_count) {
        case 1:
          $horizontal_sum_y = $horizontal_sum_y + 5;
          $s_offset = $s_offset + 0;
          $s_reduce = 0;
          break;

        case 2:
          $horizontal_sum_y = $horizontal_sum_y + 10;
          $s_offset = $s_offset + 4;
          $s_reduce = 1;
          break;

        case 3:
          $horizontal_sum_y = $horizontal_sum_y + 15;
          $s_offset = $s_offset + 9;
          $s_reduce = 1;
          break;

      }
      $lockup->setFont($regular, 6, $text_color);
      $s_lines = [];
      $s_height = 0;
      foreach ($s_explode as $key => $line) {
        str_replace('\r', '', $line);
        $s_lines[$key] = $lockup->textDimensions($line);
        $s_height = $s_height + $s_lines[$key][1];
      }
    }

    // Unit name generation.
    $p_txt = $node->field_lockup_primary_unit->value;
    $p_explode = explode(PHP_EOL, $p_txt);
    $p_count = count($p_explode);
    switch ($p_count) {
      case 1:
        $stacked_sub_y = 148.33;
        $horizontal_sum_y = $horizontal_sum_y + 6.85;
        $p_offset = 1;
        $s_offset = 12;
        break;

      case 2:
        $stacked_sub_y = 158.28;
        $horizontal_sum_y = $horizontal_sum_y + 13.7;
        $p_offset = 2;
        $s_offset = 21.5;
        break;

      case 3:
        $stacked_sub_y = 168.23;
        $horizontal_sum_y = $horizontal_sum_y + 20.55;
        $p_offset = 3;
        $s_offset = 30;
        break;

    }
    $lockup->setFont($bold, 8, $text_color);
    $lockup->setLineHeight(9.6);

    $p_lines = [];
    $p_height = 0;
    foreach ($p_explode as $key => $line) {
      $p_explode[$key] = preg_replace('~[[:cntrl:]]~', '', $line);
      $p_lines[$key] = $lockup->textDimensions($line);
      $p_height = $p_height + $p_lines[$key][1];
    }

    $p1_dimensions = $lockup->textDimensions($p_explode[0]);
    $p_width = $p1_dimensions[0];
    $p_center = $p_width / 2;

    switch ($type) {
      case 'stacked':
        LockupController::addStackedLogo($lockup, $iowa_color);
        // Border. Width based on primary width.
        if ($p_width < 80) {
          $border_width = 80;
        }
        else {
          $border_width = $p_width + 8;
        }
        $border_x = $border_width / 2;
        $lockup->addRect([
          'x' => $stacked_center - $border_x,
          'y' => '132.72',
          'width' => $border_width,
          'height' => '0.8',
          'style' => 'fill:' . $text_color,
        ]);
        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $p_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $stacked_center - $p_center,
          141.4 - ($p_lines[0][1] / 2)
        );
        // Primary Line 2.
        if (isset($p_lines[1])) {
          $p2_center = $p_lines[1][0] / 2;
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $p2_center,
            150.56 - ($p_lines[1][1] / 2)
          );
        }
        // Primary Line 3.
        if (isset($p_lines[2])) {
          $p3_center = $p_lines[2][0] / 2;
          $lockup->addText(html_entity_decode(
            $p_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $p3_center,
            160 - ($p_lines[2][1] / 2)
          );
        }

        $lockup->setFont($regular, 6, $text_color);
        $lockup->setLineHeight(7.5);

        if (isset($s_lines[0])) {
          $s1_center = $s_lines[0][0] / 2;
          $lockup->addText(html_entity_decode(
            $s_explode[0],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $s1_center,
            $stacked_sub_y
          );
        }

        if (isset($s_lines[1])) {
          $s2_center = $s_lines[1][0] / 2;
          $lockup->addText(html_entity_decode(
            $s_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $s2_center,
            $stacked_sub_y + 7.57
          );
        }
        if (isset($s_lines[2])) {
          $s3_center = $s_lines[2][0] / 2;
          $lockup->addText(html_entity_decode(
            $s_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $s3_center,
            $stacked_sub_y + 15.43
          );
        }
        break;

      case 'horizontal':
        LockupController::addHorizontalLogo($lockup, $iowa_color);
        $po = $p_line * $p_count;
        $so = $s_line * $s_count;
        if (!empty($s_txt)) {
          $ps_break = 7.5;
        }
        else {
          $ps_break = 0;
        }
        $combined = $po + $so + $ps_break;
        $combined_half = $combined / 2;

        // Border. Height based on primary and secondary combined height.
        if ($horizontal_sum_y < 22) {
          $border_height = 30;
        }
        else {
          $border_height = $horizontal_sum_y + 12;
        }
        $border_y = $border_height / 2;
        $lockup->addRect([
          'x' => 206.77,
          'y' => $horizontal_center - $border_y,
          'width' => 0.8,
          'height' => $border_height,
          'style' => 'fill:' . $text_color,
        ]);
        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $p_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          255.126 - 42,
          $horizontal_center - $combined_half - $p_offset + $s_reduce
        );
        // Primary Line 2.
        if (isset($p_explode[1])) {
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            255.126 - 42,
            $horizontal_center - $combined_half - $p_offset + $s_reduce + 10
          );
        }
        // Primary Line 3.
        if (isset($p_explode[2])) {
          $lockup->addText(html_entity_decode(
            $p_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            255.126 - 42,
            $horizontal_center - $combined_half - $p_offset + $s_reduce + 20
          );
        }

        $lockup->setFont($regular, 6, $text_color);
        $lockup->setLineHeight(7.5);

        if (isset($s_explode[0])) {
          $lockup->addText(html_entity_decode(
            $s_explode[0],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            255.126 - 42,
            $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset
          );
        }

        if (isset($s_explode[1])) {
          $lockup->addText(html_entity_decode(
            $s_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            255.126 - 42,
            $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset + 7.5
          );
        }

        if (isset($s_explode[2])) {
          $lockup->addText(html_entity_decode(
            $s_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            255.126 - 42,
            $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset + 15
          );
        }
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
