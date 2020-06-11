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
