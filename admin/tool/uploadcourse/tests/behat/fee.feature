@tool @tool_uploadcourse @_file_upload
Feature: An admin can create courses with fee enrolments using a CSV file
  In order to create courses using a CSV file with fee enrolment
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
      | Cohort 3        | CV3      | Category     | CAT2      | 1       |
      | Cohort 4        | CV4      | Category     | CAT1      | 1       |
      | Cohort 5        | CV5      | Category     | CAT1      | 1       |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | CAT1     |
    And the following "core_payment > payment accounts" exist:
      | name           | gateways |
      | Account1       | paypal   |
    And I log in as "admin"
    And I navigate to "Plugins > Enrolments > Manage enrol plugins" in site administration
    And I click on "Enable" "link" in the "Enrolment on payment" "table_row"
    And I navigate to "Courses > Upload courses" in site administration
    And I set the field "Upload mode" to "Create new courses, or update existing ones"
    And I set the field "Update mode" to "Update with CSV data only"

  @javascript
  Scenario: Upload fee enrolment if plugin is disabled
    Given the following config values are set as admin:
      | enrol_plugins_enabled | manual,guest,self |
    And I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_fee.csv" file to "File" filemanager
    When I click on "Preview" "button"
    Then I should see "Disabled"

  @javascript
  Scenario: Validation of csv data for uploaded courses for fee enrolment plugin
    Given I upload "admin/tool/uploadcourse/tests/fixtures/enrolment_fee.csv" file to "File" filemanager
    And I click on "Preview" "button"
    And I should see "Unknown payment account: Not exist"
    And I should see "Unknown currency: AAA"
    And I should see "The enrolment fee must be a number."
    When I click on "Upload courses" "button"
    And I should see "Unknown payment account: Not exist"
    And I should see "Unknown currency: AAA"
    And I should see "The enrolment fee must be a number."
    And I should see "Wrong enrolment end date format. Example: 1/1/2010"
    And I should see "Wrong enrolment start date format. Example: 1/1/2010"
    And I should see "Wrong enrolment period format. Example: 2 weeks, 1 month, 1 year..."
    And I should see "Courses created: 2"
    And I should see "Courses updated: 0"
    And I should see "Courses errors: 4"
    And I am on the "Course 2" "enrolment methods" page
    Then I should see "Enrolment on payment"
    And I click on "Edit" "link" in the "Enrolment on payment" "table_row"
    And I change window size to "large"
    And the following fields match these values:
      | Payment account | Account1 |
      | Enrolment fee   | 15       |
      | Currency        | AUD      |
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

    # End date is start date + enrolment period
    And I am on the "Course 6" "enrolment methods" page
    And I should see "Enrolment on payment"
    And I click on "Edit" "link" in the "Enrolment on payment" "table_row"
    And I change window size to "large"
    And the following fields match these values:
      | Payment account        | Account1 |
      | Enrolment fee          | 15       |
      | Currency               | AUD      |
      | enrolperiod[number]    | 5        |
      | enrolperiod[timeunit]  | days     |
      | enrolstartdate[day]    | 27       |
      | enrolstartdate[month]  | July     |
      | enrolstartdate[year]   | 2023     |
      | enrolstartdate[hour]   | 0        |
      | enrolstartdate[minute] | 0        |
      | enrolenddate[day]      | 1        |
      | enrolenddate[month]    | August   |
      | enrolenddate[year]     | 2023     |
      | enrolenddate[hour]     | 0        |
      | enrolenddate[minute]   | 0        |
