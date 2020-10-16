@layouts
Feature: layouts
  In order to verify that layout builder is working
  As a user
  I should be able edit a page layout

  @api
  @javascript
  Scenario: Edit the front page layout
    Given I am logged in as a user with role webmaster
    And I am on the homepage
    And I click the 'a[href="/node/1/layout"]' element
    Then the response status code should be 200
