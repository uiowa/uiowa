---
title: "Advanced webform configuration"
has_children: false
parent: "Webforms"
nav_order: 3
---

# Advanced webform configuration

## How to conditionally show or require fields:

1. Login to the website.
2. Navigate to the webform (Structure -> Webforms -> Find your webform).
3. Select *Build* under the *Operations* column.
4. Select *Edit* from the desired content item.
5. Select *Conditions* at the top of the *Edit* menu.
6. Add your conditions and then click the *Save* conditions button at the bottom of the page.

## An example of conditional fields:

Suppose you have a webform with a radio field title "Person type" with the options *Faculty*, *Staff*, and *Student* and a radio field titled *Student classification* with the options *Freshman, Sophomore, Junior, and Senior.*
If the user selects the "Person Type" Student, you want to know what their classification is (freshman, sophomore, junior, or senior). However, you do not want to display the "Student classification" field to users who identify as *Faculty* or *Staff.*
  1. Add a condition to show the "Student classification" field when the value of the "Person type" field is "Student". You're going to want to do this in the "Student" field  
  - Conditions if Person field =  checkboxes:
    - Conditions for student: State = Visible if "all" of the following met: Student "checkboxes" "checked"
    - Hidden if "all" of the following is met: Student "checkboxes" "unchecked"
  - Conditions if Person type =  select:
    - Conditions for student: State = Visible if “all” of the following is met: Person"Select" Value is "Student"
    - Hidden if "all" of the following is met: Person "Radios" Value is not "Student"
  - Conditions if Person type =  radios:
    - Conditions for student: State =  Visible if “all” of the following is met: Person "Radios" Value is "Student"
    - Hidden if "all" of the following is met: Person Select Value is not "Student"
  2. Add a condition to require the "Student classificaiton" field when the value of the "Person type" field is "Student".
  **Note:** The "Person type" field must be configured as "required" for this to work.
  - In the *Radios*, *General* menu, select *Form Validation* and check the *Required* box to require a selection.
  3. Click the "Save conditions" button at the bottom of the page.
  - Now when the form is viewed, the "Student classification" field does not display and is not requried unless the "Person type" selected is "Student". The default state of the form. Note that the *Student classification* field does not display.
  - The state of the form when the *Person type* selected is *Student*. Note that the *Student classification* field is displayed and is required.
  
## How to view, download and delete webform submissions:

### To view, download or delete webform submissions and submitted data:
  1. Login to the website.
  2. Navigate to the webform and click on the *Results* tab.
    - To view a single submission, click on the view link for the submission.
    - To view an analysis of the submitted data, such as the number of submissions per component values, calculuations, and averages, click on the *Analysis* link. 
    - To view the submission results in a table, click the *Table* link.
### To download submitted data:
  3. Click the *Download* link.
  4. Configure the download and export options, then click the *Download* button at the bottom of the page.
### To delete all submitted data:
  3. Click the *Clear* link.
  4. Confirm that you want to delete all submissions for the webform by clicking the *Clear* button at the bottom of the page.

## How to send an email when a form is submitted:

Often when a user submits a webform it is desired to send an email notifying someone of the submission or to the user confirming their submission.

### To send an email to a specific email address:
  1. Login to the website.
  2. Navigate to the webform.
  3. Click the *Emails/ Handlers* link.
  4. Click *Add email*.
  5. Enter the email address of the recipient in the *Title* field to correctly label it.
  6. Select *To email* and enter the recipient email address.
  7. Select *From email* and enter your email address.
  8. Click the *Save* button at the bottom of the page.
  
### To send an email to a user-provided email address (e.g. to the user submitting the form):
**Note:** To send an email to a user, the form must include an E-mail field to collect an email address from the user. For information about adding fields to a webform, please see the support article How to create or edit a webform.

  1. Login to the website.
  2. Navigate to the webform.
  3. Click the *Emails/ Handlers* link.
  4. Click *Edit* under the email.
  5. Click the *Advanced* tab.
  6. Under *Additional Settings* setup your desired email reply.
  7. Click the *Save* button at the bottom of the page.

## Future Confirmation Settings:

By default after submitting a webform it will return the user to the page where the form was filled out, not the webform entity. A feature in progress will allow custom confirmation/ redirection to a different page if configured.

## How to provide a custom confirmation message upon successful form submission:
  - Refer to the above section, "To send an email to a user-provided email address".
  
## How to close a webform:
Closing a form prevents any further submissions by any users.

  1. Login to the website.
  2. Navigate to the webform.
  3. Select the *Form* tab.
  4. Under *Form General Settings* change the *Form status* to closed.
  5. Click *Save* at the bottom of the page.
    
    
