<?php

namespace Drupal\uiowa_dcv\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * File upload form for UIowa Domain Control Validation
 */

class UIowaDCVFileForm extends configFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'uiowa_dcv_settings';
  }

  public function getEditableConfigNames() {
    return ['uiowa_dcv.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    global $base_url;

    $form['markup'] = [
      '#type' => 'markup',
      '#markup' => $this->t('DCV file load.'),
    ];

    $form['file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // If it is not set, config::get() defaults to NULL.
    if ($current = \Drupal::config('dcv_file')->get()) {
      $form['file']['#description'] = t('The hash file to upload. Currently set to <a href="@path">@file</a>.', [
        '@path' => $base_url . '/.well-known/pki-validation/' . $current,
        '@file' => $current,
      ]);

      $form['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['uiowa_dcv_delete'],
      ];
    }
    else {
      $form['file']['#description'] = $this->t('The hash file to upload.');
    }

    return $form;
  }
  
  /**
   * File upload handler.
   */
  public function file($filename) {
    if (file_exists('public://dcv/{$filename}')) {
      return new BinaryFileResponse('public://dcv/{$filename}', 200, ['Content-Type' => 'text/plain']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Delete submit handler.
   */
  public function delete($form, &$form_state) {
    $dir = 'public://dcv/';
    file_unmanaged_delete_recursive($dir);
    variable_del('dcv_file');
  }

  /**
   * Page validate handler.
   */
  public function validate($form, &$form_state) {
    if ($form_state['values']['op'] == 'Submit') {
      $dir = 'public://dcv/';
      file_unmanaged_delete_recursive($dir);
      file_prepare_directory($dir, FILE_CREATE_DIRECTORY);

      $file = file_save_upload('file', [
        'file_validate_is_file' => [],
        'file_validate_extensions' => [
          'txt',
        ],
      ],
      FALSE,
      FILE_EXISTS_REPLACE
    );

    if ($file) {
      // Ensure the filename is uppercase since DCV is case-sensitive.
      $info = pathinfo($file->uri);
      $filename = strtoupper($info['filename']) . '.' . $info['extension'];
      $form_state['storage']['file'] = $file;
      $form_state['storage']['dcv_file'] = $filename;

      if (file_unmanaged_copy($file->uri, $dir . $filename) === FALSE) {
        $form_state->setErrorByName('file', $this->t("Failed to write the uploaded file to the site's file folder."));
      }
    }
    else {
      $form_state->setErrorByName('file', $this->t('No file was uploaded.'));
    }
    } 
  }

  /**
   * Page submit handler.
   */
  public function submit($form, &$form_state) {
    file_delete($form_state['storage']['file']);
    // Recommended to change this to $form_state->set(***), but leaving for now.
    unset($form_state['storage']['file']);

    $filename = $form_state['storage']['dcv_file'];
    \Drupal::configFactory()->getEditable()->set('dcv_file', $filename);

    drupal_set_message($this->t('Uploaded @file successfully.', [
      '@file' => $filename,
    ]));
  }

}