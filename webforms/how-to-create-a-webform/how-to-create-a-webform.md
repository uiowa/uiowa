---
title: "How to create a webform"
has_children: false
parent: "Webforms"
nav_order: 2
---

# Webforms

A webform is a form or questionnaire that is accessible to users. Submission results and statistics are recorded and accessible to privileged users.

Webforms are commonly used for "Contact Us" forms.

Go to Webforms via Structure > Webforms (/admin/structure/webform)

On this page you can create new Webforms or view previous ones.

## How to create a webform

1. At the top of the page click *Structure, Webforms, Add Webform.* 
2. Enter the title of the webform in the Title field and optionally provide additional information about the form or submission process in the Body field. By default, content entered in the Body field will display above the fields of the webform.
3. (Optionally) Select the Category and Status of the webform. Default is Category: None, Status: Open.
4. Click *Save* at the bottom of the page to continue to the *Form builder page*, where you can add fields to the webform.
5. To add a field to the webform, select "Add element".
- Element Types:
  - Basic Elements:
    - **Checkboxes** - Format a set of options as checkboxes. Similar to radios, except that users may select more than one option.
    - **Hidden** - Provides a form element for an HTML "hidden" input element.
    - **Textarea** - Provides a form element for input of multiple-line text.
    - **Text Field** - Provides a form element for input of a single-line text.
   - **Date** - A date selection box.
  - Advanced Elements:
    - **Autocomplete** - Provides a text field element with auto completion.
    - **Email** - Provides a form element for entering an email address.
    - **Email confirm** - Provides a form element for double-input of email addresses.
    - **Email multiple** - Provides a form element for multiple email addresses.
    - **Number** - Provides a form element for numeric input, with special numeric validation.
    - **Telephone** - Provides a * form element for entering a telephone number.
    - **Terms of service** - Provides a terms of service element.
    - **URL** - Provides a form element for input of a URL.
  - Markup Elements:
    - **Basic HTML** - Provides an element to render basic HTML markup.
    - **Horizontal rule** - Provides a horizontal rule element.
    - **Message** - Provides an element to render custom, dismissible, inline status messages.
  - Options Elements:
    - **Checkboxes** - Provides a form element for a set of checkboxes.
    - **Checkboxes other** - Provides a form element for a set of checkboxes, with the ability to enter a custom value.
    - **Radios** - Provides a form element for a set of radio buttons.
    - **Radios other** - Provides a form element for a set of radio buttons, with the ability to enter a custom value.
    - **Select** - Provides a form element for a drop-down menu or scrolling selection box.
    - **Select other** - Provides a form element for a drop-down menu or scrolling selection box, with the ability to enter a custom value.
  - Date/Time Elements:
    - **Date** - Provides a form element for date selection.
    - **Date/time** - Provides a form element for date & time selection.
    - **Date list** - Provides a form element for date & time selection using select menus and text fields.
    - **Time** - Provides a form element for time selection.
  - Containers:
    - **Details** - Provides an interactive element that a user can open and close.
    - **Flexbox layout** - Provides a flex(ible) box container used to layout elements in multiple columns.
  - File Upload Elements:
    - **File** - Provides a form element for uploading and saving a file.
  - Buttons:
    - **Submit button(s)** - Provides an element that contains a Webform's submit, draft, wizard, and/or preview buttons.
  - Other Elements:
    - **Generic element** - Provides a generic form element.
6. To configure a field on the webform, select *Add element* in the *Select an element* menu. The configuration options differ between the types of fields, but most include tabs for *Properties* (e.g. field title, description, and default value), *Display* (e.g. should the field label be above or inline with the field), and *Validation* (e.g. are users required to fill out this field?).
7. To remove a field from the webform, select the drop-down list arrow icon that displays under the *Operations* column in the *Build* section of the *Webforms* page.
8. Click the *Save elements* button at the bottom of the page to save your changes.

## How to edit a webform

1. Login to your website and navigate to the webform that you want to edit.
2. **To change the *Title* or *Body* of the webform**, click the *Edit* tab under the *operations* column. Make your changes and then click the *Save* button at the bottom of the page.
3. **To add, edit or remove fields/form components**, click the *Webforms* tab. Follow steps 5 through 8 of *How to create a webform*, above.

## Additional webform configuration

For more information about additional webform configuration options, including
  - how to conditionally show or require fields,
  - how to view, download and delete webform submissions,
  - how to send an email when a form is submitted,
  - how to provide a custom confirmation message upon successful form submission, and
  - how to close a webform (to prevent additional submissions),
  
Please refer to the [Advanced webform configuration](https://docs.sitenow.uiowa.edu/webforms/advanced-webform-configuration/advanced-webform-configuration.html) page.
