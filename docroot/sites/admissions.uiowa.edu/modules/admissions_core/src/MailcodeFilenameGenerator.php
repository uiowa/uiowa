<?php

namespace Drupal\admissions_core;

use Drupal\Component\Transliteration\TransliterationInterface;
use \Drupal\entity_print\FilenameGenerator;
use Drupal\entity_print\FilenameGeneratorInterface;

class MailcodeFilenameGenerator extends FilenameGenerator {

  /**
   * The original FilenameGenerator service.
   *
   * @var FilenameGeneratorInterface
   */
  protected $innerService;

  public function __construct(FilenameGeneratorInterface $filenameGenerator, TransliterationInterface $transliteration) {
    $this->innerService = $filenameGenerator;
    parent::__construct($transliteration);
  }
}
