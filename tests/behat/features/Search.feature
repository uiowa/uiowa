@search
  Feature: Search
    In order to search
    As a user
    I should be able to input search terms
    And see search results

  @javascript
  Scenario: Search for something
    Given I am on the homepage
    And I click the "button.search-button" element
    When I fill in "Search" with "Foo"
    And I press the "Submit Search" button
    Then I should see the heading "Search"
    And I should see the link "Search all University of Iowa for Foo"
