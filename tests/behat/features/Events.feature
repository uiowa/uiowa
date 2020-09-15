@events
Feature: Events
  In order to verify that events can be added to a page
  As a user
  I should see events
  If I add the events block via layout builder or visit a single event page

  @api
  @javascript
  Scenario: Create a page with an events block
    Given I am logged in as a user with role webmaster
    And I am on "/node/add/page"
    And I fill in "Title" with "Events"
    And I fill in "moderation_state[0][state]" with "published"
    And I press the "Save" button
    And I am on "/events"
    Then I should see the heading "Events"
    And I click "Layout"
    And I click "Add Events"
    And I wait for AJAX to finish
    Then I should see "Configure block"
    And I press the "Add block" button
    And I wait for AJAX to finish
    And I press the "Save layout" button
    Then the response status code should be 200

Scenario: Go to a single event page
  Given I am on "/events"
  And I click the ".card__title a" element
  Then the response status code should be 200
