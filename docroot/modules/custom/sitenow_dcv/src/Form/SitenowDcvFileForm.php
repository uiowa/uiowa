<?php

namespace Drupal\sitenow_dcv\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * File upload form for domain control validation.
 */
class SitenowDcvFileForm extends FormBase {

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
      '#markup' => $this->t('Domain control validation configuration.'),
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
    if ($current = \Drupal::config('sitenow_dcv.settings')->get('dcv_file')) {
      $form['file']['#description'] = $this->t('The hash file to upload. Currently set to <a href="@path">@file</a>.', [
        '@path' => $base_url . '/.well-known/pki-validation/' . $current,
        '@file' => $current,
      ]);

      $form['delete'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#submit' => ['::delete'],
      ];
    }
    else {
      $form['file']['#description'] = $this->t('The hash file to upload.');
    }

    return $form;
  }

  /**
   * Delete submit handler.
   */
  public function delete(&$form, $form_state) {
    $filename = $form_state->get('dcv_file');
    if (file_unmanaged_delete_recursive("public://dcv/{$filename}")) {
      \Drupal::configFactory()->getEditable('sitenow_dcv.settings')
        ->set('dcv_file', NULL)
        ->save();
      drupal_set_message($this->t('Deleted successfully.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(&$form, $form_state) {
    if ($form_state->getValue('op') == 'Submit') {
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
      NULL,
      FILE_EXISTS_REPLACE
      );

      if ($file) {
        // Ensure the filename is uppercase since DCV is case-sensitive.
        $info = pathinfo($file[0]->getFileUri());
        $filename = strtoupper($info['filename']) . '.' . $info['extension'];
        $form_state->set('file', $file[0]);
        $form_state->set('dcv_file', $filename);

        if (file_unmanaged_copy($file[0]->getFileUri(), $dir . $filename) === FALSE) {
          $form_state->setErrorByName('file', $this->t("Failed to write the uploaded file to the site's file folder."));
        }
      }
      else {
        $form_state->setErrorByName('file', $this->t('No file was uploaded.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(&$form, $form_state) {
    if ($form_state->get('file')) {
      file_delete($form_state->get('file')->id());
      $form_state->set('file', NULL);
    }

    $filename = $form_state->get('dcv_file');
    \Drupal::configFactory()->getEditable('sitenow_dcv.settings')
      ->set('dcv_file', $filename)
      ->save();

    drupal_set_message($this->t('Uploaded @file successfully.', [
      '@file' => $filename,
    ]));
  }

}
