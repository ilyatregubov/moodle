@core @core_contentbank @contentbank_h5p @_file_upload @javascript
Feature: Content bank view disabled content types feature
  In order to view disabled content
  As a user
  I need to be able to see it if I have permissions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | manager  | Manager   | 1        |
    And the following "system role assigns" exist:
      | user    | course               | role    |
      | manager | Acceptance test site | manager |
    And I log in as "admin"
    And I follow "Manage private files..."
    And I upload "h5p/tests/fixtures/filltheblanks.h5p" file to "Files" filemanager
    And I click on "Save changes" "button"
    And I am on site homepage
    And I turn editing mode on
    And I add the "Navigation" block if not present
    And I configure the "Navigation" block
    And I set the following fields to these values:
      | Page contexts | Display throughout the entire site |
    And I press "Save changes"
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    And I click on "Upload" "link"
    And I click on "Choose a file..." "button"
    And I click on "Private files" "link" in the ".fp-repo-area" "css_element"
    And I click on "filltheblanks.h5p" "link"
    And I click on "Select this file" "button"
    And I click on "Save changes" "button"
    And I log out

  Scenario: Users without permission can not see disabled content type
    Given I log in as "admin"
    And I navigate to "Plugins > Content bank > Manage content types" in site administration
    And I click on "Disable" "link"
    And I log out
    And I log in as "manager"
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    Then I should not see "Add"
    And I should not see "Upload"
    Then I should not see "filltheblanks.h5p" in the "h1" "css_element"
    And I follow "Dashboard" in the user menu
    And I follow "Manage private files..."
    And I follow "Add..."
    And I click on "Content bank" "link" in the ".fp-repo-area" "css_element"
    Then "filltheblanks.h5p" "link" should not be visible

  Scenario: Users with permission can see disabled content type
    Given the following "permission overrides" exist:
      | capability                             | permission | role    | contextlevel | reference |
      | moodle/contentbank:viewdisabledtypes   | Allow      | manager | System       |           |
    And I log in as "admin"
    And I navigate to "Plugins > Content bank > Manage content types" in site administration
    And I click on "Disable" "link"
    And I log out
    And I log in as "manager"
    And I click on "Site pages" "list_item" in the "Navigation" "block"
    And I click on "Content bank" "link" in the "Navigation" "block"
    Then I should not see "Add"
    And I should not see "Upload"
    And I click on "filltheblanks.h5p" "link"
    Then I click on "Edit" "link"
    And I switch to "h5p-editor-iframe" class iframe
    And I switch to the main frame
    And I click on "Cancel" "button"
    And I should see "filltheblanks.h5p" in the "h1" "css_element"
    And I follow "Dashboard" in the user menu
    And I follow "Manage private files..."
    And I follow "Add..."
    And I click on "Content bank" "link" in the ".fp-repo-area" "css_element"
    Then "filltheblanks.h5p" "link" should not be visible
