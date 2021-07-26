@menu
  Feature: Main menu
    In order to use the main menu
    As a user
    I should only see the toggle
    If it contains links

  Scenario: I don't see the main menu toggle when there are no links
    Given no "main" menu links
    And I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Site Main Navigation"

  Scenario: I don't see the main menu toggle when there are no published links
    Given all unpublished "main" menu links
    And I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Site Main Navigation"
