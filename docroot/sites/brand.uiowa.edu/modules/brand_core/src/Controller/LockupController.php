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
    $psize = 8;
    $pline_height = 0;
    $pletter_spacing = 0;//0.033;
    $ssize = 6;
    $sline_height = 7.5;
    $sletter_spacing = 0;//0.021;

    $lockup = new BrandSVG();

    // Bunch of variables to use later.
    $stacked_center = 200;
    $stacked_start = 40;
    $horizontal_center = 50;
    $horizontal_start = 130.447;
    // Horizontal Primary Text Correction.
    $hptc = 6.291;
    // Horizontal Sub Text Correction.
    $hstc = 4.602;
    // Stacked Sub Text Correction.
    $sstc = 5.635;
    // Stacked Primary Text Correction.
    $sptc = 7.376;
    // Horizontal Rule Correction.
    $hrc = 10.841;

    switch ($type) {
      case 'stacked':
        // Sub unit generation.
        $s_data = [];
        $s_data['count'] = 0;
        if (!empty($node->field_lockup_s_unit_stacked->value)) {
          $s_txt = $node->field_lockup_s_unit_stacked->value;
          $s_explode = explode(PHP_EOL, $s_txt);
          $s_count = count($s_explode);
          $lockup->setFont($regular, $ssize, $text_color);
          $lockup->setLineHeight($sline_height);
          $lockup->setLetterSpacing($sletter_spacing);
          $s_lines = [];
          foreach ($s_explode as $key => $line) {
            preg_replace('~[[:cntrl:]]~', '', $line);
            $s_explode[$key] = $line;
            $s_lines[$key] = $lockup->textDimensions($line);
          }
          $s_data['count'] = $s_count;
          $s_data['lines'] = $s_lines;
        }

        // Unit name generation.
        $p_txt = $node->field_lockup_p_unit_stacked->value;
        $p_explode = explode(PHP_EOL, $p_txt);
        $p_count = count($p_explode);
        $lockup->setFont($bold, $psize, $text_color);
        $lockup->setLineHeight($pline_height);
        $lockup->setLetterSpacing($pletter_spacing);
        $p_lines = [];
        foreach ($p_explode as $key => $line) {
          preg_replace('~[[:cntrl:]]~', '', $line);
          $p_explode[$key] = $line;
          $p_lines[$key] = $lockup->textDimensions($line);
        }
        $p_data['count'] = $p_count;
        $p_data['lines'] = $p_lines;

        $text = LockupController::prepareText($s_data, $p_data);

        LockupController::addStackedLogo($lockup, $iowa_color);

        // Border. Width based on primary 1 width.
        if ($p_lines[0][0] < 81.791) {
          $border_width = 81.791;
        }
        else {
          $border_width = $p_lines[0][0] + 8;
        }
        $lockup->addRect([
          'x' => $stacked_center - ($border_width / 2),
          'y' => '35.16',
          'width' => $border_width,
          'height' => '0.733',
          'style' => 'fill:' . $text_color,
        ]);

        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $p_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $stacked_center - ($p_lines[0][0] / 2),
          $stacked_start - $sptc + $text['p1y']
        );
        // Primary Line 2.
        if (isset($p_lines[1])) {
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - ($p_lines[1][0] / 2),
            $stacked_start - $sptc + $text['p2y']
          );
        }
        // Primary Line 3.
        if (isset($p_lines[2])) {
          $lockup->addText(html_entity_decode(
            $p_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - ($p_lines[2][0] / 2),
            $stacked_start - $sptc + $text['p3y']
          );
        }

        $lockup->setFont($regular, $ssize, $text_color);
        $lockup->setLineHeight($sline_height);
        $lockup->setLetterSpacing($sletter_spacing);

        if (isset($s_lines[0])) {
          $lockup->addText(html_entity_decode(
            $s_explode[0],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - ($s_lines[0][0] / 2),
            $stacked_start - $sstc + $text['s1y']
          );
        }

        if (isset($s_lines[1])) {
          $lockup->addText(html_entity_decode(
            $s_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - ($s_lines[1][0] / 2),
            $stacked_start - $sstc + $text['s2y']
          );
        }
        if (isset($s_lines[2])) {
          $lockup->addText(html_entity_decode(
            $s_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $stacked_center - ($s_lines[2][0] / 2),
            $stacked_start - $sstc + $text['s3y']
          );
        }
        break;

      case 'horizontal':
        // Sub unit generation.
        $s_data = [];
        $s_data['count'] = 0;
        if (!empty($node->field_lockup_sub_unit->value)) {
          $s_txt = $node->field_lockup_sub_unit->value;
          $s_explode = explode(PHP_EOL, $s_txt);
          $s_count = count($s_explode);
          $lockup->setFont($regular, $ssize, $text_color);
          $lockup->setLineHeight($sline_height);
          $lockup->setLetterSpacing($sletter_spacing);
          $s_lines = [];
          foreach ($s_explode as $key => $line) {
            preg_replace('~[[:cntrl:]]~', '', $line);
            $s_explode[$key] = $line;
            $s_lines[$key] = $lockup->textDimensions($line);
          }
          $s_data['count'] = $s_count;
          $s_data['lines'] = $s_lines;
        }

        // Unit name generation.
        $p_txt = $node->field_lockup_primary_unit->value;
        $p_explode = explode(PHP_EOL, $p_txt);
        $p_count = count($p_explode);
        $lockup->setFont($bold, $psize, $text_color);
        $lockup->setLineHeight($pline_height);
        $lockup->setLetterSpacing($pletter_spacing);
        $p_lines = [];
        foreach ($p_explode as $key => $line) {
          preg_replace('~[[:cntrl:]]~', '', $line);
          $p_explode[$key] = $line;
          $p_lines[$key] = $lockup->textDimensions($line);
        }
        $p_data['count'] = $p_count;
        $p_data['lines'] = $p_lines;

        $text = LockupController::prepareText($s_data, $p_data);

        LockupController::addHorizontalLogo($lockup, $iowa_color);

        // Primary Line 1.
        $lockup->addText(html_entity_decode(
          $p_explode[0],
          ENT_QUOTES | ENT_XML1,
          'UTF-8'),
          $horizontal_start,
          $horizontal_center - $hptc + $text['p1y'] - $text['offset']
        );
        // Primary Line 2.
        if (isset($p_explode[1])) {
          $lockup->addText(html_entity_decode(
            $p_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $horizontal_start,
            $horizontal_center - $hptc + $text['p2y'] - $text['offset']
          );
        }
        // Primary Line 3.
        if (isset($p_explode[2])) {
          $lockup->addText(html_entity_decode(
            $p_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $horizontal_start,
            $horizontal_center - $hptc + $text['p3y'] - $text['offset']
          );
        }

        $lockup->setFont($regular, $ssize, $text_color);
        $lockup->setLineHeight($sline_height);
        $lockup->setLetterSpacing($sletter_spacing);

        if (isset($s_explode[0])) {
          $lockup->addText(html_entity_decode(
            $s_explode[0],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $horizontal_start,
            $horizontal_center - $hstc + $text['s1y'] - $text['offset']
          );
        }

        if (isset($s_explode[1])) {
          $lockup->addText(html_entity_decode(
            $s_explode[1],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $horizontal_start,
            $horizontal_center - $hstc + $text['s2y'] - $text['offset']
          );
        }

        if (isset($s_explode[2])) {
          $lockup->addText(html_entity_decode(
            $s_explode[2],
            ENT_QUOTES | ENT_XML1,
            'UTF-8'),
            $horizontal_start,
            $horizontal_center - $hstc + $text['s3y'] - $text['offset']
          );
        }

        $border_height = $text['total_height'] + 8;
        if ($border_height < 29.369) {
          $border_height = 29.369;
        }

        // Draw border.
        $lockup->addRect([
          'x' => 124.625,
          'y' => $horizontal_center - ($border_height / 2),
          'width' => 0.733,
          'height' => $border_height,
          'style' => 'fill:' . $text_color,
        ]);
        break;

    }

    $lockup->addAttribute('viewBox', '0 0 400 100');

    return $lockup->asXML();
  }

  /**
   * Prepare Text.
   */
  public function prepareText($s_data, $p_data) {
    $variant = 'p' . $p_data['count'] . 's' . $s_data['count'];
    $half_p1h = $p_data['lines'][0][1] / 2;
    // Primary Margin Bottom.
    $pmb = 0;
    // Primary/Sub Gap.
    $psg = 3; //3.3
    // Sub Margin Bottom.
    $smb = 0;
    $text = [];

    switch ($variant) {
      case 'p1s0':
        // 1 Primary, 0 Sub.
        $text['total_height'] = $p_data['lines'][0][1];
        $text['offset'] = $text['total_height'] / 2;
        $text['p1y'] = $half_p1h;
        break;

      case 'p1s1':
        // 1 Primary, 1 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $psg + $s_data['lines'][0][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['s1y'] = $half_p1h + $psg + $s_data['lines'][0][1];
        break;

      case 'p1s2':
        // 1 Primary, 2 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['s1y'] = $half_p1h + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        break;

      case 'p1s3':
        // 2 Primary, 3 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['s1y'] = $half_p1h + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        $text['s3y'] = $half_p1h + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        break;

      case 'p2s0':
        // 2 Primary, 0 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1];
        break;

      case 'p2s1':
        // 2 Primary, 1 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1];
        break;

      case 'p2s2':
        // 2 Primary, 2 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        break;

      case 'p2s3':
        // 2 Primary, 3 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        $text['s3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        break;

      case 'p3s0':
        // 3 Primary, 0 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['p3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1];
        break;

      case 'p3s1':
        // 3 Primary, 1 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['p3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1];
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1];
        break;

      case 'p3s2':
        // 3 Primary, 2 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['p3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1];
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1]+ $smb + $s_data['lines'][1][1];
        break;

      case 'p3s3':
        // 3 Primary, 3 Sub.
        // Total Text Height.
        $text['total_height'] = $p_data['lines'][0][1] + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1] + $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        // Overall Offset.
        $text['offset'] = $text['total_height'] / 2;
        // Positions.
        $text['p1y'] = $half_p1h;
        $text['p2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] ;
        $text['p3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1];
        $text['s1y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1];
        $text['s2y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1]+ $smb + $s_data['lines'][1][1];
        $text['s3y'] = $half_p1h + $pmb + $p_data['lines'][1][1] + $pmb + $p_data['lines'][2][1] + $psg + $s_data['lines'][0][1]+ $smb + $s_data['lines'][1][1] + $smb + $s_data['lines'][2][1];
        break;
    }

    return $text;
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
