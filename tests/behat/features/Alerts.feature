@alerts
Feature: Alerts
  In order to verify that alerts are working
  As a user
  I should see a test alert
  If the module is configured to show them

  @api
  Scenario: Create a custom alert
    Given I am logged in as a user with role webmaster
    And I am on "/admin/config/system/uiowa-alerts"
    And I check the box "Display Custom Alert"
    And I fill in "Custom Alert Message" with "<h2>TEST</h2><p>This is a custom alert.</p>"
    And I press the "Save configuration" button
    And I am on the homepage
    Then I should see the heading "TEST"
    And I should see "This is a custom alert."

  @drush
  @api
  @javascript
  Scenario: Configure the module for a test Hawk Alert
    Given I run drush "config:set uiowa_alerts.settings hawk_alert.source 'https://emergency.stage.drupal.uiowa.edu/api/active.json'"
    And I am on the homepage
    And I wait for AJAX to finish
    Then I should see the warning message "Hawk Alert"
