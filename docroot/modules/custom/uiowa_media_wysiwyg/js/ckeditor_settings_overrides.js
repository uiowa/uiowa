CKEDITOR.on( 'dialogDefinition', function( ev )
{
    var dialogName = ev.data.name;
    var dialogDefinition = ev.data.definition;

    if (dialogName == 'table') {
        // Get the properties tab reference.
        var infoTab = dialogDefinition.getContents('info');

        // Remove unnecessary bits from this tab.
        infoTab.remove('txtBorder');
        infoTab.remove('cmbAlign');
        infoTab.remove('txtWidth');
        infoTab.remove('txtHeight');
        infoTab.remove('txtCellSpace');
        infoTab.remove('txtCellPad');
    }
    if (dialogName == 'cellProperties') {
        // Get the properties tab reference.
        var infoTab = dialogDefinition.getContents('info');

        infoTab.remove('borderColor');
        infoTab.remove('bgColor');
        infoTab.remove('vAlign');
        infoTab.remove('hAlign');
        infoTab.remove('wordWrap');
    }
    if (dialogName == 'tableProperties') {
        // Get the properties tab reference.
        var infoTab = dialogDefinition.getContents('info');

        // Remove unnecessary bits from this tab.
        infoTab.remove('txtBorder');
        infoTab.remove('cmbAlign');
        infoTab.remove('txtWidth');
        infoTab.remove('txtHeight');
        infoTab.remove('txtCellSpace');
        infoTab.remove('txtCellPad');
    }
});