@tool @tool_uploadcourse @_file_upload
Feature: An admin can create courses with cohort enrolments using a CSV file
  In order to create courses using a CSV file with cohort enrolment
  As an admin
  I need to be able to upload a CSV file and navigate through the import process

  Background:
    Given the following "categories" exist:
      | name  | category | idnumber |
      | Cat 0 | 0        | CAT0     |
      | Cat 1 | CAT0     | CAT1     |
      | Cat 1 | CAT0     | CAT2     |
    And the following "cohorts" exist:
      | name            | idnumber | contextlevel | reference | visible |
      | Cohort 1        | CV1      | Category     | CAT1      | 1       |
      | Cohort 2        | CV2      | Category     | CAT2      | 1       |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
    And I log in as "admin"
    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"

  @javascript
  Scenario: Validation of cohorts for uploaded courses
    Given I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_cohort.csv" file to "File" filemanager
    And I click on "Preview" "button"
    And I should see "Unknown cohort (Not exist)!"
    And I should see "Cohort Cohort 2 not allowed in this context."
    When I click on "Upload courses" "button"
    And I should see "Unknown cohort (Not exist)!"
    And I should see "Cohort Cohort 2 not allowed in this context."
    And I should see "Cohort Cohort 1 not allowed in this context."
    And I should see "Courses created: 2"
    And I should see "Courses updated: 0"
    And I should see "Courses errors: 3"
    And I am on the "Course 1" "enrolment methods" page
    Then I should not see "Cohort sync (Cohort 2 - Student)"
    And I am on the "Course 2" "enrolment methods" page
    And I should not see "Cohort sync (Cohort 1 - Student)"
    And I am on the "Course 3" "enrolment methods" page
    And I should see "Cohort sync (Cohort 1 - Student)"
    And I click on "Edit" "link" in the "Cohort 1" "table_row"
    And the field "Add to group" matches value "None"

  @javascript
  Scenario: Validation of groups for uploaded courses with cohort enrolments
    Given the following "groups" exist:
      | name    | course | idnumber |
      | group1  | C1     | G1       |
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_cohort_addtogroup_groupname.csv" file to "File" filemanager
    And I click on "Preview" "button"
    And I should see "You can not specify groupname when Add to group option is 'None' or 'Creat new group'"

    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_cohort_addtogroup.csv" file to "File" filemanager
    And I click on "Preview" "button"
    And I click on "Upload courses" "button"
    And I should see "Courses created: 2"
    And I should see "Courses errors: 0"
    And I am on the "Course 2" "enrolment methods" page
    And I should see "Cohort sync (Cohort 2 - Student)"
    And I click on "Edit" "link" in the "Cohort 2" "table_row"
    And the field "Add to group" matches value "Cohort 2 cohort"
    And I am on the "Course 2" "groups" page
    And I should see "Cohort 2 cohort"
    And I am on the "Course 3" "enrolment methods" page
    And I should see "Cohort sync (Cohort 1 - Student)"
    And I click on "Edit" "link" in the "Cohort 1" "table_row"
    And the field "Add to group" matches value "None"
    And I am on the "Course 3" "groups" page
    And I should not see "Cohort 1 cohort"

    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_cohort_groups.csv" file to "File" filemanager
    And I click on "Preview" "button"
    And I should see "Error, invalid group notexist"
    When I click on "Upload courses" "button"
    And I should see "Error, invalid group notexist"
    And I should see "Courses updated: 1"
    And I should see "Courses errors: 1"
    And I am on the "Course 1" "enrolment methods" page
    Then I should see "Cohort sync (Cohort 1 - Student)"
    And I click on "Edit" "link" in the "Cohort 1" "table_row"
    And the field "Add to group" matches value "group1"
    And I am on the "Course 1" "groups" page
    And I should see "group1"
