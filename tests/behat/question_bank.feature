@qtype @qtype_mojomatch @javascript
Feature: TopoMojo questions in the question bank
  In order to manage the questions imported from a TopoMojo challenge
  As a teacher
  I need the TopoMojo question type to work in the question bank

  # Preview, and any attempt, are deliberately not visited: rendering the question asks TopoMojo
  # for the live answer, and with no TopoMojo configured qtype_mojomatch_question::setup()
  # reports the missing client through debugging(), which fails the step.

  Background:
    Given the following "users" exist:
      | username |
      | teacher  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | intro           | course | idnumber |
      | qbank    | Qbank 1 | Question bank 1 | C1     | qbank1   |
    And the following "questions" exist:
      | questioncategory    | qtype     | name          | template   |
      | Default for Qbank 1 | mojomatch | Mojomatch Q1  | exactmatch |
      | Default for Qbank 1 | truefalse | True-false Q1 | true       |

  Scenario: TopoMojo is offered in the question type chooser
    When I am on the "Qbank 1" "core_question > question bank" page logged in as teacher
    And I press "Create a new question ..."
    Then "TopoMojo" "field" should exist in the "Choose a question type to add" "dialogue"

  Scenario: The TopoMojo editing form opens from the question type chooser
    When I am on the "Qbank 1" "core_question > question bank" page logged in as teacher
    And I press "Create a new question ..."
    And I set the field "item_qtype_mojomatch" to "1"
    And I click on "Add" "button" in the "Choose a question type to add" "dialogue"
    Then I should see "Adding a mojomatch question"
    And the field "variant" matches value "1"
    And the field "transforms" matches value "1"
    And the field "workspaceid" matches value ""

  Scenario: A TopoMojo question is listed and found by the type filter
    When I am on the "Qbank 1" "core_question > question bank" page logged in as teacher
    And I should see "Mojomatch Q1"
    And I should see "True-false Q1"
    And I apply question bank filter "Type" with value "TopoMojo"
    Then I should see "Mojomatch Q1"
    And I should not see "True-false Q1"
