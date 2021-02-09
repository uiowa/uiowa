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

  /**
   * {@inheritdoc}
   */
  public function generateFilename(array $entities, callable $entity_label_callback = NULL) {
    $filenames = [];

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    foreach ($entities as $entity) {
      if ($entity->getType() == 'area_of_study') {
        $label = $entity->get('field_area_of_study_mail_code')->getString();
      }
      else {
        $label = $entity->label();
      }

      if ($label = trim($this->sanitizeFilename($label, $entity->language()->getId()))) {
        $filenames[] = $label;
      }
    }

    return $filenames ? implode('-', $filenames) : static::DEFAULT_FILENAME;
  }
}
