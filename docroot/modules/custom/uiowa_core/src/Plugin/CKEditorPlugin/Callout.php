<?php

namespace Drupal\uiowa_core\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "Callout" plugin, with a CKEditor.
 *
 * @CKEditorPlugin(
 *   id = "callout",
 *   label = @Translation("Callout Plugin"),
 *   module = "uiowa_core"
 * )
 */
class Callout extends CKEditorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(Editor $editor) {
    return [
      'uids_base/callout',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isInternal() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    // Provide the JS plugin path.
    return $this->getModulePath('uiowa_core') . '/js/plugins/callout/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    return [
      'Callout' => [
        'label' => 'Callout',
        'image' => $this->getModulePath('uiowa_core') . '/js/plugins/callout/icons/speech-bubble-one-color-black-square.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

}
