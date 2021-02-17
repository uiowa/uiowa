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

// If our document has 'text--serif' set on the body class
// Set in uids_base_preprocess_html() of docroot/themes/custom/uids_base/uids_base.theme.
if (document.getElementsByTagName("body")[0].classList.contains('text--serif')) {
  // Define a set of fields that we want to have text-serif.
  let fields = ["field--name-body", "field--name-field-person-bio", "field--name-field-uiowa-text-area", "field--name-field-uiowa-card-excerpt"];
  // Get the off canvas UI when it is generated.
  let off_canvas_ui = document.getElementById("layout-builder-update-block");
  // For each of our defined fields...
  fields.forEach(function(field) {
    // If the off canvas UI contains one...
    if(off_canvas_ui.getElementsByClassName(field).length) {
      // Give the CKEditor the 'text--serif' class.
      CKEDITOR.config.bodyClass = 'text--serif';
    }
  });
}


