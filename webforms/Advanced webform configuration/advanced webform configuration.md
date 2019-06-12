---
title: "Advanced webform configuration"
has_children: false
parent: "Webforms"
nav_order: 2
---

# Advanced webform configuration

## How to conditionally show or require fields

1. Login to the website.
2. Navigate to the webform (Structure < Webforms < Find your webform).
3. Select *Build* under the *Operations* column.
4. Select *Edit* from the desired content item.
5. Select *Conditions* at the top of the *Edit* menu.
6. Add your conditions and then click the *Save* conditions button at the bottom of the page.

## An example of conditional fields.

Suppose you have a webform with a radio field title "Person type" with the options *Faculty*, *Staff*, and *Student* and a radio field titled *Student classification* with the options *Freshman, Sophomore, Junior, and Senior.*
If the user selects the "Person Type" Student, you want to know what their classification is (freshman, sophomore, junior, or senior). However, you do not want to display the "Student classification" field to users who identify as *Faculty* or *Staff.*
  1. Add a condition to show the "Student classification" field when the value of the "Person type" field is "Student".
  2. Add a condition to require the "Student classificaiton" field when the value of the "Person type" field is "Student".
 **Note:** The "Person type" field must be configured as "required" for this to work.
  - In the *Radios*, *General* menu, select *Form Validation* and check the *Required* box to require a selection.
  3. Click the "Save conditions" button at the bottom of the page.
  - Now when the form is viewed, the "Student classification" field does not display and is not requried unless the "Person type" selected is "Student". The default state of the form. Note that the *Student classification* field does not display.
  - The state of the form when the *Person type* selected is *Student*. Note that the *Student classification* field is displayed and is required.
  
## How to view, download and delete webform submissions

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
 
    
    
    
    
    
    
