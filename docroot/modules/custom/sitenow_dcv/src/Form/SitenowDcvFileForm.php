<?php

namespace Drupal\sitenow_dcv\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\State;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * File upload form for domain control validation.
 */
class SitenowDcvFileForm extends FormBase {
  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * SitenowDcvFileForm constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity storage service.
   * @param \Drupal\Core\State\State $state
   *   The state service.
   */
  public function __construct(FileSystemInterface $fileSystem, MessengerInterface $messenger, EntityTypeManagerInterface $entityTypeManager, State $state) {
    $this->fileSystem = $fileSystem;
    $this->messenger = $messenger;
    $this->entityTypeManager = $entityTypeManager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('messenger'),
      $container->get('entity_type.manager'),
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sitenow_dcv_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return 'sitenow_dcv.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('This form provides a file upload for domain control validation. The file should be generated by CertManager when selecting the HTTPS DCV method. Once uploaded, you can submit the DCV request for processing. Please delete the file once DCV is complete.'),
    ];

    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    /* @var \Drupal\file\Entity\File[] $file */
    $file = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties(['filename' => $this->state->get('sitenow_dcv_filename', '')]);

    // There can only be one file with this name since we are replacing on upload.
    $file = array_pop($file);

    if ($file) {
      $form['file']['#description'] = $this->t('The hash file to upload. Currently set to <a href="@path">@file</a>.', [
        '@path' => $base_url . '/.well-known/pki-validation/' . $file->getFilename(),
        '@file' => $file->getFilename(),
      ]);
    }
    else {
      $form['file']['#description'] = $this->t('The hash file to upload.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(&$form, $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = file_save_upload('file', [
      'file_validate_is_file' => [],
      'file_validate_extensions' => [
        'txt',
      ],
    ],
    FALSE,
    0,
    FileSystemInterface::EXISTS_REPLACE
    );

    if ($file) {
      $form_state->set('sitenow_dcv_filename', $file->getFilename());
    }
    else {
      $form_state->setErrorByName('file', $this->t('There was an error trying to upload the file.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(&$form, $form_state) {
    $filename = $form_state->get('sitenow_dcv_filename');
    $this->state->set('sitenow_dcv_filename', $filename);

    $this->messenger->addMessage($this->t('Uploaded @file successfully.', [
      '@file' => $filename,
    ]));
  }

}
