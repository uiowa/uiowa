/**
 * @file
 *
 * Custom CKEditor configuration.
 */

// Update default body text font to UIDS typeface.
CKEDITOR.addCss( 'body { font-family: Roboto,sans-serif } ' );



// If our document has 'text--serif' set on the body class.
// Set in uids_base_preprocess_html() of docroot/themes/custom/uids_base/uids_base.theme.
if (document.getElementsByTagName("body")[0].classList.contains('text--serif')) {

  // Set allowed fields.
  let allowedInstances = [
    "edit-body-0-value",
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



