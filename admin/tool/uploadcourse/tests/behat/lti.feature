@tool @tool_uploadcourse @_file_upload
Feature: An admin can create courses with lti enrolments using a CSV file
  In order to create courses using a CSV file with lti enrolment
  As an admin
  I need to be able to upload a CSV file and navigate through the import process

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format |
      | Course 1 | C1 | topics |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Publish as LTI tool" "table_row"
    And I navigate to "Plugins > Enrolments > Publish as LTI tool" in site administration
    And I set the following fields to these values:
      | Email visibility    | Visible to everyone |
      | City/town           | Perth                                  |
      | Select a country    | Australia                              |
      | Timezone            | Australia/Perth                        |
      | Institution         | Moodle Pty Ltd                         |
    And I press "Save changes"
    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"

  @javascript
  Scenario: Upload lti enrolment if plugin is disabled
    Given I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Disable" "link" in the "Publish as LTI tool" "table_row"
    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_lti.csv" file to "File" filemanager
    When I click on "Preview" "button"
    Then I should see "Publish as LTI tool enrolment plugin is disabled"

  @javascript
  Scenario: Upload lti enrolment with default values (create and update)
    Given I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_lti.csv" file to "File" filemanager
    And I click on "Preview" "button"
    When I click on "Upload courses" "button"
    And I should see "Courses updated: 1"
    And I should see "Courses created: 1"
    And I am on the "Course 1" "enrolment methods" page
    Then I should see "Publish as LTI tool"
    And I click on "Edit" "link" in the "Publish as LTI tool" "table_row"
    And the following fields match these values:
      | LTI version                                                          | LTI Advantage                       |
      | Tool to be published                                                 | Course                              |
      | Maximum enrolled users                                               | 0                                   |
      | Role for teacher                                                     | Teacher                             |
      | Role for student                                                     | Student                             |
      | Teacher first launch provisioning mode                               | Existing and new accounts (prompt)  |
      | Student first launch provisioning mode                               | New accounts only (automatic)       |
      | Grade synchronisation                                                | Yes                                 |
      | Require course or activity completion prior to grade synchronisation | No                                  |
      | User synchronisation                                                 | Yes                                 |
      | User synchronisation mode                                            | Enrol new and unenrol missing users |
      | Email visibility                                                     | Visible to everyone                 |
      | City/town                                                            |                                     |
      | Institution                                                          |                                     |
    And the "enrolperiod[number]" "field" should be disabled
    And the "enrolperiod[timeunit]" "field" should be disabled
    And the "enrolstartdate[day]" "field" should be disabled
    And the "enrolstartdate[month]" "field" should be disabled
    And the "enrolstartdate[year]" "field" should be disabled
    And the "enrolstartdate[hour]" "field" should be disabled
    And the "enrolstartdate[minute]" "field" should be disabled
    And the "enrolenddate[day]" "field" should be disabled
    And the "enrolenddate[month]" "field" should be disabled
    And the "enrolenddate[year]" "field" should be disabled
    And the "enrolenddate[hour]" "field" should be disabled
    And the "enrolenddate[minute]" "field" should be disabled
    And I am on the "Course 2" "enrolment methods" page
    Then I should see "Publish as LTI tool"
    And I click on "Edit" "link" in the "Publish as LTI tool" "table_row"
    And the following fields match these values:
      | LTI version                                                          | LTI Advantage                       |
      | Tool to be published                                                 | Course                              |
      | Maximum enrolled users                                               | 0                                   |
      | Role for teacher                                                     | Teacher                             |
      | Role for student                                                     | Student                             |
      | Teacher first launch provisioning mode                               | Existing and new accounts (prompt)  |
      | Student first launch provisioning mode                               | New accounts only (automatic)       |
      | Grade synchronisation                                                | Yes                                 |
      | Require course or activity completion prior to grade synchronisation | No                                  |
      | User synchronisation                                                 | Yes                                 |
      | User synchronisation mode                                            | Enrol new and unenrol missing users |
      | Email visibility                                                     | Visible to everyone                 |
      | City/town                                                            |                                     |
      | Institution                                                          |                                     |
    And the "enrolperiod[number]" "field" should be disabled
    And the "enrolperiod[timeunit]" "field" should be disabled
    And the "enrolstartdate[day]" "field" should be disabled
    And the "enrolstartdate[month]" "field" should be disabled
    And the "enrolstartdate[year]" "field" should be disabled
    And the "enrolstartdate[hour]" "field" should be disabled
    And the "enrolstartdate[minute]" "field" should be disabled
    And the "enrolenddate[day]" "field" should be disabled
    And the "enrolenddate[month]" "field" should be disabled
    And the "enrolenddate[year]" "field" should be disabled
    And the "enrolenddate[hour]" "field" should be disabled
    And the "enrolenddate[minute]" "field" should be disabled

  @javascript
  Scenario: Validate lti enrolment csv values
    Given the following "language pack" exists:
      | language | en_ca |
    And the following "courses" exist:
      | fullname | shortname | format | enablecompletion |
      | Course 2 | C2        | topics | 0                |
      | Course 3 | C3        | topics | 1                |
    And the following "activities" exist:
      | activity | course | idnumber | name                 | intro                       | assignfeedback_comments_enabled | completion |
      | assign   | C2     | assign2  | Assignment 1         | Test assignment description | 1                               | 1          |
      | assign   | C3     | assign3  | Assignment 1         | Test assignment description | 1                               | 1          |
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_lti_validate.csv" file to "File" filemanager
    When I click on "Preview" "button"
    When I click on "Upload courses" "button"

    And I should see "Unsupported LTI version" in the "1" "table_row"
    Then I should see "Unexpected tool to be published: not exist. It should be either a course name or an activity name within a given course" in the "1" "table_row"
    And I should see "Wrong enrolment period format. Example: 2 weeks, 1 month, 1 year..." in the "1" "table_row"
    And I should see "Wrong enrolment end date format. Example: 1/1/2010" in the "1" "table_row"
    And I should see "Wrong enrolment start date format. Example: 1/1/2010" in the "1" "table_row"
    And I should see "Unexpected role for teacher" in the "1" "table_row"
    And I should see "Unexpected role for student" in the "1" "table_row"
    And I should see "Unexpected teacher first launch provisioning mode" in the "1" "table_row"
    And I should see "Unexpected student first launch provisioning mode" in the "1" "table_row"
    And I should see "Unexpected user synchronisation mode" in the "1" "table_row"
    And I should see "Unexpected email visibility mode" in the "1" "table_row"
    And I should see "Unexpected country" in the "1" "table_row"
    And I should see "Unexpected time zone" in the "1" "table_row"
    And I should see "Unexpected preferred language" in the "1" "table_row"

    And I should not see "Unsupported LTI version" in the "2" "table_row"
    And I should not see "Unexpected tool to be published: not exist. It should be either a course name or an activity name within a given course" in the "2" "table_row"
    And I should not see "Wrong enrolment period format. Example: 2 weeks, 1 month, 1 year..." in the "2" "table_row"
    And I should not see "Wrong enrolment end date format. Example: 1/1/2010" in the "2" "table_row"
    And I should not see "Wrong enrolment start date format. Example: 1/1/2010" in the "2" "table_row"
    And I should see "Enrolment end date cannot be earlier than start date" in the "2" "table_row"
    And I should not see "Unexpected role for teacher" in the "2" "table_row"
    And I should not see "Unexpected role for student" in the "2" "table_row"
    And I should not see "Unexpected teacher first launch provisioning mode" in the "2" "table_row"
    And I should not see "Unexpected student first launch provisioning mode" in the "2" "table_row"
    And I should not see "Unexpected user synchronisation mode" in the "2" "table_row"
    And I should not see "Unexpected email visibility mode" in the "2" "table_row"
    And I should not see "Unexpected country" in the "2" "table_row"
    And I should not see "Unexpected time zone" in the "2" "table_row"
    And I should not see "Unexpected preferred language" in the "2" "table_row"
    And I should see "Completion should be enabled for Assignment 1 when requirecompletion is set" in the "2" "table_row"

    And I should see "Courses updated: 1"
    And I should see "Courses errors: 2"
