@core @core_grades @javascript
Feature: Average grades are displayed in the gradebook
    In order to check the expected results are displayed
    As an admin
    I need to assign grades and check that they display correctly in the gradebook.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1        | topics |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
      | student3 | Student   | 3        | student3@example.com |
      | student4 | Student   | 4        | student4@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | student3 | C1     | student        |
      | student4 | C1     | student        |
    And the following "grade item" exists:
      | course   | C1            |
      | itemname | Manual item 1 |
    And the following "grade grades" exist:
      | gradeitem     | user     | grade | hidden |
      | Manual item 1 | student1 | 10.00 | 0      |
      | Manual item 1 | student2 | 20.00 | 0      |
      | Manual item 1 | student3 | 30.00 | 0      |
      | Manual item 1 | student4 | 40.00 | 1      |
    And the following "course enrolments" exist:
      | user     | course | role    | status    |
      | student2 | C1     | student | suspended |

    # Enable averages
    And I am on the "Course 1" "grades > course grade settings" page logged in as "admin"
    And I set the following fields to these values:
      | Show average | Show |
    And I press "Save changes"

  Scenario: Grade a grade item and ensure the results display correctly in the gradebook
    # Check the admin grade table
    Given I am on the "Course 1" "grades > Grader report > View" page logged in as "admin"
    # Average is (10 + 30 + 40)/3 = 26.67 for manual and total since hidden items are included on grader report
    And the following should exist in the "user-grades" table:
      | -1-                | -2-       | -3-       |
      | Overall average    | 26.67     | 26.67     |
    # Check the user grade table
    When I am on the "Course 1" "grades > user > View" page logged in as "student1"
    # Average of manual item is (10 + 30)/2 = 20.00 since hidden items are not included on user report.
    # But total is calculated and its settings allow using hidden grades so it will stay the same.
    Then the following should exist in the "user-grade" table:
      | Grade item              | Calculated weight | Grade  | Range | Percentage | Average | Contribution to course total |
      | Manual item 1           | 100.00 %          | 10.00  | 0–100 | 10.00 %    | 20.00   | 10.00 %                      |
      | Course total            | -                 | 10.00  | 0–100 | 10.00 %    | 26.67   | -                            |
