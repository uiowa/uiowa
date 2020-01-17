<?php

namespace Drupal\brand_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\brand_core\BrandSVG;
use Drupal\Component\Utility\Html;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

      $path = $node->getTitle();
      $name = Html::cleanCssIdentifier($path);

      $directories['lockups'] = 'public://lockups';
      $directories['lockup'] = $directories['lockups'] . '/' . $nid;

      /** @var \Drupal\Core\File\FileSystemInterface $file_system */
      $file_system = \Drupal::service('file_system');

      foreach ($directories as $dir) {
        $file_system->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);
      }

      // Create the different lockup versions.
      $lockup_stacked_black = LockupController::generateLockup($node, '#000000', "#000000", 'stacked');
      $lockup_stacked_black_file = $name . '-LockupStacked-BLACK.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_black_file, 'w'), $lockup_stacked_black);
      $file_system->copy(file_directory_temp() . '/' . $lockup_stacked_black_file, $directories['lockup'] . '/' . $lockup_stacked_black_file, FileSystemInterface::EXISTS_REPLACE);

      $lockup_stacked_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'stacked');
      $lockup_stacked_rgb_file = $name . '-LockupStacked-RGB.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_rgb_file, 'w'), $lockup_stacked_rgb);
      $file_system->copy(file_directory_temp() . '/' . $lockup_stacked_rgb_file, $directories['lockup'] . '/' . $lockup_stacked_rgb_file, FileSystemInterface::EXISTS_REPLACE);

      $lockup_stacked_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'stacked');
      $lockup_stacked_reversed_file = $name . '-LockupStacked-RGB-REVERSED.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_stacked_reversed_file, 'w'), $lockup_stacked_reversed);
      $file_system->copy(file_directory_temp() . '/' . $lockup_stacked_reversed_file, $directories['lockup'] . '/' . $lockup_stacked_reversed_file, FileSystemInterface::EXISTS_REPLACE);

      $lockup_horizontal_black = LockupController::generateLockup($node, '#000000', "#000000", 'horizontal');
      $lockup_horizontal_black_file = $name . '-LockupHorizontal-BLACK.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_black_file, 'w'), $lockup_horizontal_black);
      $file_system->copy(file_directory_temp() . '/' . $lockup_horizontal_black_file, $directories['lockup'] . '/' . $lockup_horizontal_black_file, FileSystemInterface::EXISTS_REPLACE);

      $lockup_horizontal_rgb = LockupController::generateLockup($node, '#FFCD00', "#000000", 'horizontal');
      $lockup_horizontal_rgb_file = $name . '-LockupHorizontal-RGB.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, 'w'), $lockup_horizontal_rgb);
      $file_system->copy(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, $directories['lockup'] . '/' . $lockup_horizontal_rgb_file, FileSystemInterface::EXISTS_REPLACE);

      $lockup_horizontal_reversed = LockupController::generateLockup($node, '#FFCD00', "#FFFFFF", 'horizontal');
      $lockup_horizontal_reversed_file = $name . '-LockupHorizontal-RGB-REVERSED.svg';
      fwrite(fopen(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, 'w'), $lockup_horizontal_reversed);
      $file_system->copy(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, $directories['lockup'] . '/' . $lockup_horizontal_reversed_file, FileSystemInterface::EXISTS_REPLACE);

      $zip = new \ZipArchive();

      $zip_filename = 'temporary://lockup.zip';

      if ($zip->open(\Drupal::service('file_system')->realpath($zip_filename), \ZipArchive::CREATE) !== TRUE) {
        exit("cannot open <$zip_filename>\n");
      }

      $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_black_file, $name . "-Lockup/" . $lockup_stacked_black_file);
      $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_rgb_file, $name . "-Lockup/" . $lockup_stacked_rgb_file);
      $zip->addFile(file_directory_temp() . '/' . $lockup_stacked_reversed_file, $name . "-Lockup/" . $lockup_stacked_reversed_file);
      $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_black_file, $name . "-Lockup/" . $lockup_horizontal_black_file);
      $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_rgb_file, $name . "-Lockup/" . $lockup_horizontal_rgb_file);
      $zip->addFile(file_directory_temp() . '/' . $lockup_horizontal_reversed_file, $name . "-Lockup/" . $lockup_horizontal_reversed_file);

      // Read the instructions.
      $instructions = drupal_get_path('module', 'brand_core') . '/lockup-instructions.docx';
      $zip->addFile($instructions, $name . "-Lockup/lockup-instructions.docx");
      $zip->close();

      $file_system->copy($zip_filename, $directories['lockup'] . '/' . $nid . '.zip', FileSystemInterface::EXISTS_REPLACE);

      unlink($zip_filename);
      unlink(file_directory_temp() . '/' . $lockup_stacked_black_file);
      unlink(file_directory_temp() . '/' . $lockup_stacked_rgb_file);
      unlink(file_directory_temp() . '/' . $lockup_stacked_reversed_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_black_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_rgb_file);
      unlink(file_directory_temp() . '/' . $lockup_horizontal_reversed_file);

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

    // Bunch of variables to use later.
    $stacked_center = 200;
    $horizontal_center = 50.462;
    $p_line = 8;
    $s_line = 6;
    $s_count = 0;
    $s_reduce = 0;
    $s_offset = 0;
    $p_offset = 1;

    switch ($type) {
      case 'stacked':
        // Sub unit generation.
        if (!empty($node->field_lockup_s_unit_stacked->value)) {
          $p_offset = $p_offset + 5.5;
          $s_txt = $node->field_lockup_s_unit_stacked->value;
          $s_explode = explode(PHP_EOL, $s_txt);
          $s_count = count($s_explode);
          $s_offset = 0;
          switch ($s_count) {
            case 1:
              $s_offset = $s_offset + 0;
              $s_reduce = 2;
              break;

            case 2:
              $s_offset = $s_offset + 4;
              $s_reduce = 1;
              break;

            case 3:
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
        $p_txt = $node->field_lockup_p_unit_stacked->value;
        $p_explode = explode(PHP_EOL, $p_txt);
        $p_count = count($p_explode);
        switch ($p_count) {
          case 1:
            $stacked_sub_y = 49.57;
            $p_offset = 1;
            $s_offset = 11.5;
            break;

          case 2:
            $stacked_sub_y = 59.52;
            $p_offset = 2;
            $s_offset = 21.5;
            break;

          case 3:
            $stacked_sub_y = 69.47;
            $p_offset = 3;
            $s_offset = 31.5;
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
          'y' => '35.16',
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
          42.64 - ($p_lines[0][1] / 2)
        );
        // Primary Line 2.
        if (isset($p_lines[1])) {
          $p2_center = $p_lines[1][0] / 2;
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - $p2_center,
            51.8 - ($p_lines[1][1] / 2)
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
            61.24 - ($p_lines[2][1] / 2)
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
        // Sub unit generation.
        if (!empty($node->field_lockup_sub_unit->value)) {
          $p_offset = $p_offset + 5.5;
          $s_txt = $node->field_lockup_sub_unit->value;
          $s_explode = explode(PHP_EOL, $s_txt);
          $s_count = count($s_explode);
          $s_offset = 0;
          switch ($s_count) {
            case 1:
              $s_offset = $s_offset + 0;
              $s_reduce = 2;
              break;

            case 2:
              $s_offset = $s_offset + 4;
              $s_reduce = 1;
              break;

            case 3:
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
            $p_offset = 1;
            $s_offset = 11.5;
            break;

          case 2:
            $stacked_sub_y = 158.28;
            $p_offset = 2;
            $s_offset = 21.5;
            break;

          case 3:
            $stacked_sub_y = 168.23;
            $p_offset = 3;
            $s_offset = 31.5;
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

        LockupController::addHorizontalLogo($lockup, $iowa_color);
        // Silly math that made sense at the time...
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

        // Used later to find last line position.
        $y_positions = [];

        // Primary Line 1.
        $p1y = $horizontal_center - $combined_half - $p_offset + $s_reduce;
        $y_positions[] = $p1y;
        $lockup->addText(html_entity_decode(
          $p_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          173 - 42,
          $p1y
        );
        // Primary Line 2.
        if (isset($p_explode[1])) {
          $p2y = $horizontal_center - $combined_half - $p_offset + $s_reduce + 10;
          $y_positions[] = $p2y;
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            173 - 42,
            $p2y
          );
        }
        // Primary Line 3.
        if (isset($p_explode[2])) {
          $p3y = $horizontal_center - $combined_half - $p_offset + $s_reduce + 20;
          $y_positions[] = $p3y;
          $lockup->addText(html_entity_decode(
            $p_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            173 - 42,
            $p3y
          );
        }

        $lockup->setFont($regular, 6, $text_color);
        $lockup->setLineHeight(7.5);

        if (isset($s_explode[0])) {
          $s1y = $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset;
          $y_positions[] = $s1y;
          $lockup->addText(html_entity_decode(
            $s_explode[0],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            173 - 42,
            $s1y
          );
        }

        if (isset($s_explode[1])) {
          $s2y = $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset + 7.5;
          $y_positions[] = $s2y;
          $lockup->addText(html_entity_decode(
            $s_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            173 - 42,
            $s2y
          );
        }

        if (isset($s_explode[2])) {
          $s3y = $horizontal_center - $combined_half - $p_offset + $s_reduce + $s_offset + 15;
          $y_positions[] = $s3y;
          $lockup->addText(html_entity_decode(
            $s_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            173 - 42,
            $s3y
          );
        }

        // Calculate border based on top and furthest bottom y values.
        $bottom_y = max($y_positions);
        $border_height = intval($bottom_y - $p1y);
        // Account for primary/sub spacing and 4 top and bottom.
        if (isset($s_txt)) {
          $border_height = $border_height + 12;
        }
        else {
          $border_height = $border_height + 8;
        }
        if ($border_height < 30) {
          $border_height = 30;
        }
        $border_half = $border_height / 2;

        // Draw border.
        $lockup->addRect([
          'x' => 124.625,
          'y' => $horizontal_center - $border_half,
          'width' => 0.8,
          'height' => $border_height,
          'style' => 'fill:' . $text_color,
        ]);
        break;

    }

    $lockup->addAttribute('viewBox', '0 0 400 100');

    return $lockup->asXML();
  }

  /**
   * Add Stacked Logo.
   */
  public function addStackedLogo($svg, $iowa_color) {
    $svg->addPath('M172.51,13.23h-1.73V25.69h1.73v4.54H163V25.69h1.73V13.23H163V8.77h9.51Z', ["fill" => $iowa_color]);
    $svg->addPath('M185.29,30.23H178.4c-2.5,0-4.14-1.64-4.14-4.33V13.09a4,4,0,0,1,3.66-4.31,3.85,3.85,0,0,1,.48,0h6.89c2.5,0,4.13,1.67,4.13,4.32V25.87a4,4,0,0,1-3.62,4.35A4.34,4.34,0,0,1,185.29,30.23Zm-2-4.51V13.23h-3V25.69Z', ["fill" => $iowa_color]);
    $svg->addPath('M192.13,13.24h-1.52V8.78h9v4.46h-1.7l1.55,10.31,3.21-14.79h4.57l3.43,14.79,1.25-10.31h-1.57V8.78h8.89v4.46h-1.48l-2.69,17h-7.25L205,17.58l-2.88,12.66h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M217.67,25.69h1.63l3.25-16.92h9.72l3.21,16.92H237v4.54h-7.26L229.06,25h-3.61l-.65,5.22h-7.13Zm11-4.63-1.39-8.65-1.42,8.65Z', ["fill" => $iowa_color]);
  }

  /**
   * Add Horizontal Logo.
   */
  public function addHorizontalLogo($svg, $iowa_color) {
    $svg->addPath('M54.9,43.73H53.17V56.19H54.9v4.54H45.39V56.19h1.73V43.73H45.39V39.27H54.9Z', ["fill" => $iowa_color]);
    $svg->addPath('M67.68,60.73H60.79c-2.5,0-4.14-1.64-4.14-4.33V43.59a4,4,0,0,1,3.66-4.31,3.85,3.85,0,0,1,.48,0h6.89c2.5,0,4.13,1.67,4.13,4.32V56.37a4,4,0,0,1-3.62,4.35A4.34,4.34,0,0,1,67.68,60.73Zm-2-4.51V43.73h-3V56.19Z', ["fill" => $iowa_color]);
    $svg->addPath('M74.52,43.74H73V39.28h9v4.46H80.3l1.55,10.31,3.21-14.79h4.57l3.43,14.79,1.25-10.31H92.74V39.28h8.89v4.46h-1.48l-2.69,17H90.21L87.4,48.08,84.52,60.74h-7Z', ["fill" => $iowa_color]);
    $svg->addPath('M100.06,56.19h1.63l3.25-16.92h9.72l3.21,16.92h1.52v4.54h-7.26l-.68-5.22h-3.61l-.65,5.22h-7.13Zm11-4.63-1.39-8.65-1.42,8.65Z', ["fill" => $iowa_color]);
  }

  /**
   * Download Lockup.
   */
  public function download($nid) {
    $is_node = \Drupal::entityQuery('node')->condition('nid', $nid)->execute();

    if ($is_node) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);

      $path = $node->getTitle();
      $name = Html::cleanCssIdentifier($path);
      $file = 'public://lockups/' . $nid . '/' . $nid . '.zip';

      if (file_exists($file)) {
        $response = new BinaryFileResponse($file);
        $response->setContentDisposition('attachment', $name . '-Lockup.zip');
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Length', filesize($file));
        return $response;
      }
    }
    throw new NotFoundHttpException();
  }

}
