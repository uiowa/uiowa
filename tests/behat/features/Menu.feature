@menu
  Feature: Main menu
    In order to use the main menu
    As a user
    I should only see the toggle
    If the menu contains links

  Scenario: I do see the main menu toggle when there are links
    Given "main" menu links to content with published status
    And I am an anonymous user
    And I am on the homepage
    Then I should see the text "Site Main Navigation"

  Scenario: I don't see the main menu toggle when there are no links
    Given no "main" menu links
    And I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Site Main Navigation"

  Scenario: I don't see the main menu toggle when there are no published links
    Given "main" menu links to content with unpublished status
    And I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Site Main Navigation"
