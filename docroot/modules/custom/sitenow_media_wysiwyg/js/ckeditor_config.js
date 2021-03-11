/**
 * @file
 *
 * Custom CKEditor configuration.
 */

// Allow empty spans for FontAwesome icons.
CKEDITOR.dtd.$removeEmpty['span'] = false;

// Update default body text font to UIDS typeface.
CKEDITOR.addCss( 'body { font-family: Roboto,sans-serif } ' );

// Remove table and cell properties that can make them unusable/inaccessible.
CKEDITOR.on('dialogDefinition', function (ev) {
  var dialogName = ev.data.name;
  var dialogDefinition = ev.data.definition;

  if (dialogName == 'table' || 'tableProperties') {
    var infoTab = dialogDefinition.getContents('info');

    infoTab.remove('txtBorder');
    infoTab.remove('cmbAlign');
    infoTab.remove('txtWidth');
    infoTab.remove('txtHeight');
    infoTab.remove('txtCellSpace');
    infoTab.remove('txtCellPad');
  }
  if (dialogName == 'cellProperties') {
    var infoTab = dialogDefinition.getContents('info');

    infoTab.remove('borderColor');
    infoTab.remove('bgColor');
    infoTab.remove('vAlign');
    infoTab.remove('hAlign');
    infoTab.remove('wordWrap');
  }
});

// If our document has 'text--serif' set on the body class.
// Set in uids_base_preprocess_html() of docroot/themes/custom/uids_base/uids_base.theme.
if (document.getElementsByTagName("body")[0].classList.contains('text--serif')) {

  // Set allowed fields.
  let allowedInstances = [
    "edit-body-0-value",
    "edit-field-person-bio-0-value",
    "edit-settings-block-form-field-uiowa-text-area-0-value"
  ];
  // Get ckeditor instances keys.
  let ckeInstances = Object.keys(CKEDITOR.instances);

  // For each of our allowed fields...
  allowedInstances.forEach(function(field) {
    // For each instance key...
    ckeInstances.forEach(function(instance) {
      // If the key includes one of our allowed fields...
      // We do this check because some fields have generated block ids concatenated to them.
      // We want to affect those blocks, but not have to know the ids, so we do this check.
      if (instance.includes(field)) {
        // Add text-serif to the WYSIWYG editor.
        CKEDITOR.instances[instance].config.bodyClass = 'text--serif';
      }
    });
  });
}

// This was not working properly when a user first added a table.
// Removed and functionality duplicated for all CKE tables in docroot/themes/custom/uids_base/scss/components/tables.scss .
CKEDITOR.config.removePlugins = 'showborders';


