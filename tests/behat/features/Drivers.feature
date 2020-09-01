@drivers
Feature: Web drivers
  In order to verify that web drivers are working
  As a user
  I should be able to load the homepage successfully
  With various drivers

  Scenario: Load the homepage without Javascript
    Given I am on the homepage
    Then the response status code should be 200
    And the "h1.site-name" element should contain "New Website"

  @javascript
  Scenario: Load the homepage with Javascript
    Given I am on the homepage
    Then the response status code should be 200
    And the "h1.site-name" element should contain "New Website"

   @api
   Scenario: Load page as authenticated user
     Given I am logged in as a user with role editor
     And I am on "/user"
     Then the response status code should be 200
     And I should see "behat_editor"

   @drush
   Scenario: Load site via Drush
     Given I run drush "status"
     Then drush output should contain "Drupal bootstrap : Successful"
