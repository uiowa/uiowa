<?php

namespace Drupal\uiowa_dcv\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Returns responses for Uiowa DCV routes.
 */
class UiowaDcvController extends ControllerBase {

  /**
   * Constructs the controller object.
   */
  public function __construct() {}

  /**
   * File page callback.
   */
  public function file($filename) {
    if (file_exists("public://dcv/{$filename}")) {
      return new BinaryFileResponse("public://dcv/{$filename}", 200, ['Content-Type' => 'text/plain']);
    }
    else {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Admin page callback.
   */
  public function form() {
    global $base_url;

    $form = [];

    $form['uiowa_dcv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    // If it is not set, config::get() defaults to NULL.
    if ($current = \Drupal::config('uiowa_dcv_file')->get()) {
      $form['uiowa_dcv_file']['#description'] = t('The hash file to upload. Currenly set to <a href="@path">@file</a>.', [
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
      $form['uiowa_dcv_file']['#description'] = $this->t('The hash file to upload.');
    }

    return $form;
  }

  /**
   * Delete submit handler.
   */
  public function delete($form, &$form_state) {
    $dir = 'public://dcv/';
    file_unmanaged_delete_recursive($dir);
    variable_del('uiwoa_dcv_file');
  }

  /**
   * Admin page validate.
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
   * Admin page submit.
   */
  public function submit($form, &$form_state) {
    file_delete($form_state['storage']['file']);
    // Recommended to change this to $form_state->set(***), but leaving for now.
    unset($form_state['storage']['file']);

    $filename = $form_state['storage']['dcv_file'];
    \Drupal::configFactory()->getEditable()->set('uiowa_dcv_file', $filename);

    drupal_set_message($this->t('Uploaded @file successfully.', [
      '@file' => $filename,
    ]));
  }

}
