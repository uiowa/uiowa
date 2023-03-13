/**
 * @file
 * Callout plugin definition.
 */

CKEDITOR.plugins.add('callout', {

  init: function(editor) {
    'use strict';

    editor.addCommand('callout', {
      modes: { wysiwyg: 1 },
      canUndo: true,
      exec: function exec(execEditor) {
        var dialogSettings = {
          title: 'Add Callout',
          dialogClass: 'callout_dialog'
        };
        var saveCallback = function saveCallback(returnValues) {
          execEditor.fire('saveSnapshot');

          var selection = execEditor.getSelection();
          var range = selection.getRanges(1)[0];

          var container = new CKEDITOR.dom.element(returnValues.wrapper.tag, execEditor.document);
          container.setAttributes(returnValues.wrapper.attributes);

          var innerContainer = new CKEDITOR.dom.element('div', execEditor.document);


          var heading = new CKEDITOR.dom.element(returnValues.heading.tag, execEditor.document);
          heading.setAttributes(returnValues.heading.attributes);
          heading.setText(returnValues.heading.value);

          var body = new CKEDITOR.dom.element(returnValues.body.tag, execEditor.document);
          body.setText(returnValues.body.value);

          innerContainer.append(heading);
          innerContainer.append(body);

          container.append(innerContainer);

          range.insertNode(container);
          range.select();

          execEditor.fire('saveSnapshot');

        };
        Drupal.ckeditor.openDialog(execEditor, Drupal.url('uiowa_core/dialog/' + execEditor.config.drupal.format), {}, saveCallback, dialogSettings);
      }
    });

    if (editor.ui.addButton) {
      editor.ui.addButton('Callout', {
        label: Drupal.t('Callout'),
        command: 'callout',
        toolbar: 'insert'
      });
    }

  }
});
