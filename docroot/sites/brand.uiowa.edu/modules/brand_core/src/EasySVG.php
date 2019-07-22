<?php

namespace Drupal\brand_core;

/**
 * EasySVG - Generate SVG from PHP.
 *
 * @author Simon Tarchichi <kartsims@gmail.com>
 * @version 0.1b
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/SVG/Attribute/transform
 * @see http://stackoverflow.com/questions/14684846/flattening-svg-matrix-transforms-in-inkscape
 * @see http://stackoverflow.com/questions/7742148/how-to-convert-text-to-svg-paths
 */
class EasySVG {

  protected $font;
  public $svg;

  /**
   *
   */
  public function __construct() {
    // Default font data.
    $this->font = new \stdClass();
    $this->font->id = '';
    $this->font->horizAdvX = 0;
    $this->font->unitsPerEm = 0;
    $this->font->ascent = 0;
    $this->font->descent = 0;
    $this->font->glyphs = [];
    $this->font->size = 20;
    $this->font->color = NULL;
    $this->font->lineHeight = 1;
    $this->font->letterSpacing = 0;

    $this->clearSVG();
  }

  /**
   *
   */
  public function clearSVG() {
    $this->svg = new \SimpleXMLElement('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"></svg>');
  }

  /**
   * Function takes UTF-8 encoded string and returns unicode number for every character.
   *
   * @param string $str
   *
   * @return string
   */
  private function _utf8ToUnicode($str) {
    $unicode = [];
    $values = [];
    $lookingFor = 1;

    for ($i = 0; $i < strlen($str); $i++) {
      $thisValue = ord($str[$i]);
      if ($thisValue < 128) {
        $unicode[] = $thisValue;
      }
      else {
        if (count($values) == 0) {
          $lookingFor = ($thisValue < 224) ? 2 : 3;
        }
        $values[] = $thisValue;
        if (count($values) == $lookingFor) {
          $number = ($lookingFor == 3) ?
            (($values[0] % 16) * 4096) + (($values[1] % 64) * 64) + ($values[2] % 64) :
            (($values[0] % 32) * 64) + ($values[1] % 64);

          $unicode[] = $number;
          $values = [];
          $lookingFor = 1;
        }
      }
    }

    return $unicode;
  }

  /**
   * Set font params (short-hand method)
   *
   * @param string $filepath
   * @param int $size
   * @param string $color
   */
  public function setFont($filepath, $size, $color = NULL) {
    $this->setFontSVG($filepath);
    $this->setFontSize($size);
    if ($color) {
      $this->setFontColor($color);
    }
  }

  /**
   * Set font size for display.
   *
   * @param int $size
   *
   * @return void
   */
  public function setFontSize($size) {
    $this->font->size = $size;
  }

  /**
   * Set font color.
   *
   * @param string $color
   *
   * @return void
   */
  public function setFontColor($color) {
    $this->font->color = $color;
  }

  /**
   * Set the line height from default (1) to custom value.
   *
   * @param float $value
   *
   * @return void
   */
  public function setLineHeight($value) {
    $this->font->lineHeight = $value;
  }

  /**
   * Set the letter spacing from default (0) to custom value.
   *
   * @param float $value
   *
   * @return void
   */
  public function setLetterSpacing($value) {
    $this->font->letterSpacing = $value;
  }

  /**
   * Function takes path to SVG font (local path) and processes its xml
   * to get path representation of every character and additional
   * font parameters.
   *
   * @param string $filepath
   *
   * @return void
   */
  public function setFontSVG($filepath) {
    $this->font->glyphs = [];
    $z = new \XMLReader();
    $z->open($filepath);

    // Move to the first <product /> node.
    while ($z->read()) {
      $name = $z->name;

      if ($z->nodeType == \XMLReader::ELEMENT) {
        if ($name == 'font') {
          $this->font->id = $z->getAttribute('id');
          $this->font->horizAdvX = $z->getAttribute('horiz-adv-x');
        }

        if ($name == 'font-face') {
          $this->font->unitsPerEm = $z->getAttribute('units-per-em');
          $this->font->ascent = $z->getAttribute('ascent');
          $this->font->descent = $z->getAttribute('descent');
        }

        if ($name == 'glyph') {
          $unicode = $z->getAttribute('unicode');
          $unicode = $this->_utf8ToUnicode($unicode);

          if (isset($unicode[0])) {
            $unicode = $unicode[0];

            $this->font->glyphs[$unicode] = new \stdClass();
            $this->font->glyphs[$unicode]->horizAdvX = $z->getAttribute('horiz-adv-x');
            if (empty($this->font->glyphs[$unicode]->horizAdvX)) {
              $this->font->glyphs[$unicode]->horizAdvX = $this->font->horizAdvX;
            }
            $this->font->glyphs[$unicode]->d = $z->getAttribute('d');

            // Save em value for letter spacing (109 is unicode for the letter 'm')
            if ($unicode == '109') {
              $this->font->em = $this->font->glyphs[$unicode]->horizAdvX;
            }
          }
        }
      }
    }
  }

  /**
   * Add a path to the SVG.
   *
   * @param string $def
   * @param array $attributes
   *
   * @return SimpleXMLElement
   */
  public function addPath($def, $attributes = []) {
    $path = $this->svg->addChild('path');
    foreach ($attributes as $key => $value) {
      $path->addAttribute($key, $value);
    }
    $path->addAttribute('d', $def);
    return $path;
  }

  /**
   * Add a rect to the SVG.
   *
   * @param array $attributes
   *
   * @return SimpleXMLElement
   */
  public function addRect($attributes = []) {
    $rect = $this->svg->addChild('rect');
    foreach ($attributes as $key => $value) {
      $rect->addAttribute($key, $value);
    }
    return $rect;
  }

  /**
   * Add a text to the SVG.
   *
   * @param string $def
   * @param floatstring $x
   * @param floatstring $y
   * @param array $attributes
   *
   * @return SimpleXMLElement
   */
  public function addText($text, $x = 0, $y = 0, $attributes = []) {
    $def = $this->textDef($text);

    if ($x === 'center' || $y === 'center') {
      list($textWidth, $textHeight) = $this->textDimensions($text);
    }

    // Center horizontally.
    if ($x === 'center') {
      if ($this->svg['width'] === NULL) {
        throw new Error('SVG width has to be set to center the text horizontally');
      }
      $x = (intval($this->svg['width']) - $textWidth) / 2;
    }

    // Center vertically.
    if ($y === 'center') {
      if ($this->svg['height'] === NULL) {
        throw new Error('SVG height has to be set to center the text vertically');
      }
      $y = (intval($this->svg['height']) - $textHeight) / 2;
    }

    if ($x != 0 || $y != 0) {
      $def = $this->defTranslate($def, $x, $y);
    }

    if ($this->font->color) {
      $attributes['fill'] = $this->font->color;
    }

    return $this->addPath($def, $attributes);
  }

  /**
   * Function takes UTF-8 encoded string and size, returns xml for SVG paths representing this string.
   *
   * @param string $text
   *   UTF-8 encoded text.
   *
   * @return string xml for text converted into SVG paths
   */
  public function textDef($text) {
    $def = [];

    $horizAdvX = 0;
    $horizAdvY = $this->font->ascent + $this->font->descent;
    $fontSize = floatval($this->font->size) / $this->font->unitsPerEm;
    $text = $this->_utf8ToUnicode($text);

    for ($i = 0; $i < count($text); $i++) {

      $letter = $text[$i];

      // Line break support (10 is unicode for linebreak)
      if ($letter == 10) {
        $horizAdvX = 0;
        $horizAdvY += $this->font->lineHeight * ($this->font->ascent + $this->font->descent);
        continue;
      }

      // Extract character definition.
      $d = $this->font->glyphs[$letter]->d;

      // Transform typo from original SVG format to straight display.
      $d = $this->defScale($d, $fontSize, -$fontSize);
      $d = $this->defTranslate($d, $horizAdvX, $horizAdvY * $fontSize * 2);

      $def[] = $d;

      // Next letter's position.
      $horizAdvX += $this->font->glyphs[$letter]->horizAdvX * $fontSize + $this->font->em * $this->font->letterSpacing * $fontSize;
    }
    return implode(' ', $def);
  }

  /**
   * Function takes UTF-8 encoded string and size, returns width and height of the whole text.
   *
   * @param string $text
   *   UTF-8 encoded text.
   *
   * @return array ($width, $height)
   */
  public function textDimensions($text) {
    $def = [];

    $fontSize = floatval($this->font->size) / $this->font->unitsPerEm;
    $text = $this->_utf8ToUnicode($text);

    $lineWidth = 0;
    $lineHeight = ($this->font->ascent + $this->font->descent) * $fontSize * 2;

    $width = 0;
    $height = $lineHeight;

    for ($i = 0; $i < count($text); $i++) {

      $letter = $text[$i];

      // Line break support (10 is unicode for linebreak)
      if ($letter == 10) {
        $width = $lineWidth > $width ? $lineWidth : $width;
        $height += $lineHeight * $this->font->lineHeight;
        $lineWidth = 0;
        continue;
      }

      $lineWidth += $this->font->glyphs[$letter]->horizAdvX * $fontSize + $this->font->em * $this->font->letterSpacing * $fontSize;
    }

    // Only keep the widest line's width.
    $width = $lineWidth > $width ? $lineWidth : $width;

    return [$width, $height];
  }

  /**
   * Function takes unicode character and returns the UTF-8 equivalent.
   *
   * @param string $str
   *
   * @return string
   */
  public function unicodeDef($unicode) {

    $horizAdvY = $this->font->ascent + $this->font->descent;
    $fontSize = floatval($this->font->size) / $this->font->unitsPerEm;

    // Extract character definition.
    $d = $this->font->glyphs[hexdec($unicode)]->d;

    // Transform typo from original SVG format to straight display.
    $d = $this->defScale($d, $fontSize, -$fontSize);
    $d = $this->defTranslate($d, 0, $horizAdvY * $fontSize * 2);

    return $d;
  }

  /**
   * Returns the character width, as set in the font file.
   *
   * @param string $str
   * @param bool $is_unicode
   *
   * @return float
   */
  public function characterWidth($char, $is_unicode = FALSE) {
    if ($is_unicode) {
      $letter = hexdec($char);
    }
    else {
      $letter = $this->_utf8ToUnicode($char);
    }

    if (!isset($this->font->glyphs[$letter])) {
      return NULL;
    }

    $fontSize = floatval($this->font->size) / $this->font->unitsPerEm;
    return $this->font->glyphs[$letter]->horizAdvX * $fontSize;
  }

  /**
   * Applies a translate transformation to definition.
   *
   * @param string $def
   *   definition.
   * @param float $x
   * @param float $y
   *
   * @return string
   */
  public function defTranslate($def, $x = 0, $y = 0) {
    return $this->defApplyMatrix($def, [1, 0, 0, 1, $x, $y]);
  }

  /**
   * Applies a translate transformation to definition.
   *
   * @param string $def
   *   Definition.
   * @param int $angle
   *   Rotation angle (degrees)
   * @param int $x
   *   X coordinate of rotation center.
   * @param int $y
   *   Y coordinate of rotation center.
   *
   * @return string
   */
  public function defRotate($def, $angle, $x = 0, $y = 0) {
    if ($x == 0 && $y == 0) {
      $angle = deg2rad($angle);
      return $this->defApplyMatrix($def, [cos($angle), sin($angle), -sin($angle), cos($angle), 0, 0]);
    }

    // Rotate by a given point.
    $def = $this->defTranslate($def, $x, $y);
    $def = $this->defRotate($def, $angle);
    $def = $this->defTranslate($def, -$x, -$y);
    return $def;
  }

  /**
   * Applies a scale transformation to definition.
   *
   * @param string $def
   *   definition.
   * @param int $x
   * @param int $y
   *
   * @return string
   */
  public function defScale($def, $x = 1, $y = 1) {
    return $this->defApplyMatrix($def, [$x, 0, 0, $y, 0, 0]);
  }

  /**
   * Calculates the new definition with the matrix applied.
   *
   * @param string $def
   * @param array $matrix
   *
   * @return string
   */
  public function defApplyMatrix($def, $matrix) {

    // If there are several shapes in this definition, do the operation for each.
    preg_match_all('/M[^zZ]*[zZ]/', $def, $shapes);
    $shapes = $shapes[0];
    if (count($shapes) > 1) {
      foreach ($shapes as &$shape) {
        $shape = $this->defApplyMatrix($shape, $matrix);
      }
      return implode(' ', $shapes);
    }

    preg_match_all('/[a-zA-Z]+[^a-zA-Z]*/', $def, $instructions);
    $instructions = $instructions[0];

    $return = '';
    foreach ($instructions as &$instruction) {
      $i = preg_replace('/[^a-zA-Z]*/', '', $instruction);
      preg_match_all('/\-?[0-9\.]+/', $instruction, $coords);
      $coords = $coords[0];

      if (empty($coords)) {
        continue;
      }

      $new_coords = [];
      while (count($coords) > 0) {

        // Do the matrix calculation stuff.
        list($a, $b, $c, $d, $e, $f) = $matrix;

        // Exception for relative instruction.
        if (preg_match('/[a-z]/', $i)) {
          $e = 0;
          $f = 0;
        }

        // Convert horizontal lineto (relative)
        if ($i == 'h') {
          $i = 'l';
          $x = floatval(array_shift($coords));
          $y = 0;

          // Add new point's coordinates.
          $current_point = [
            $a * $x + $c * $y + $e,
            $b * $x + $d * $y + $f,
          ];
          $new_coords = array_merge($new_coords, $current_point);
        }

        // Convert vertical lineto (relative)
        elseif ($i == 'v') {
          $i = 'l';
          $x = 0;
          $y = floatval(array_shift($coords));

          // Add new point's coordinates.
          $current_point = [
            $a * $x + $c * $y + $e,
            $b * $x + $d * $y + $f,
          ];
          $new_coords = array_merge($new_coords, $current_point);
        }

        // Convert quadratic bezier curve (relative)
        elseif ($i == 'q') {
          $x = floatval(array_shift($coords));
          $y = floatval(array_shift($coords));

          // Add new point's coordinates.
          $current_point = [
            $a * $x + $c * $y + $e,
            $b * $x + $d * $y + $f,
          ];
          $new_coords = array_merge($new_coords, $current_point);

          // Same for 2nd point.
          $x = floatval(array_shift($coords));
          $y = floatval(array_shift($coords));

          // Add new point's coordinates.
          $current_point = [
            $a * $x + $c * $y + $e,
            $b * $x + $d * $y + $f,
          ];
          $new_coords = array_merge($new_coords, $current_point);
        }

        // Every other commands.
        // @TODO: handle 'a,c,s' (elliptic arc curve) commands
        // cf. http://www.w3.org/TR/SVG/paths.html#PathDataCurveCommands
        else {
          $x = floatval(array_shift($coords));
          $y = floatval(array_shift($coords));

          // Add new point's coordinates.
          $current_point = [
            $a * $x + $c * $y + $e,
            $b * $x + $d * $y + $f,
          ];
          $new_coords = array_merge($new_coords, $current_point);
        }

      }

      $instruction = $i . implode(',', $new_coords);

      // Remove useless commas.
      $instruction = preg_replace('/,\-/', '-', $instruction);
    }

    return implode('', $instructions);
  }

  /**
   * Short-hand methods.
   */

  /**
   * Return full SVG XML.
   *
   * @return string
   */
  public function asXML() {
    return $this->svg->asXML();
  }

  /**
   * Adds an attribute to the SVG.
   *
   * @param string $key
   * @param string $value
   */
  public function addAttribute($key, $value) {
    return $this->svg->addAttribute($key, $value);
  }

}
