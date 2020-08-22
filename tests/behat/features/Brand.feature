@base
Feature: Brand
  In order to see global branding
  As a user
  I should be able to load the homepage

  Scenario: Load the homepage
    Given I am on the homepage
    Then the response status code should be 200

  Scenario: See header branding elements
    Given I am on the homepage
    Then I should see an "header svg.logo-icon" element
    And I should see an "header h1.site-name" element
    And I should see an "header button.search-button" element
