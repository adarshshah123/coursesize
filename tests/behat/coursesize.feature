@report @report_coursesize @_file_upload
Feature: Course size report calculates correct information
  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
      | Course 2 | C2 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | teacher1 | C2 | editingteacher |
    And I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I log out
   @javascript
  Scenario: Check coursesize report after sharing same file in same course Course 1
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I add a "File" to section "2"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I log out
    When I log in as "admin"
    And I navigate to "Reports > Course size" in site administration
    Then I should see "File usage report"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I wait "10" seconds
    And I should not see "C3"
  @javascript
  Scenario: Check coursesize report for course 1
    When I log in as "admin"
    And I navigate to "Reports > Course size" in site administration
    Then I should see "File usage report"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I click on "view stats" "link"
    Then I should see "Course Size Overview"
    And I should see "0 MB" in the "#sharedsize_C1" "css_element"
    And I should see "1 MB" in the "#recoverablesize_C1" "css_element"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I should not see "C2"
    And I log out
  @javascript
  Scenario: Check coursesize report after sharing same file in same course Course 1
    When I log in as "teacher1"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I add a "File" to section "2"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I log out
    When I log in as "admin"
    And I navigate to "Reports > Course size" in site administration
    Then I should see "File usage report"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I click on "view stats" "link"
    Then I should see "Course Size Overview"
    And I should see "0 MB" in the "#sharedsize_C1" "css_element"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I wait "10" seconds
    And I should not see "C3"
  @javascript 
  Scenario: Check coursesize report for course 1 after creating backup
    When I log in as "admin"
    And I click on "Site home" "link"
    And I follow "Course 1"
    And I navigate to "Backup" in current page administration
    And I press "Jump to final step"
    And I press "Continue"
    Then I should see "Course backup area"
    And I navigate to "Reports > Course size" in site administration
    Then I should see "File usage report"
    And I should see "1 MB" in the "#backupsize_C1" "css_element"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I click on "view stats" "link"
    Then I should see "Course Size Overview"
    And I should see "0 MB" in the "#sharedsize_C1" "css_element"
    And I should see "2 MB" in the "#recoverablesize_C1" "css_element"
    And I should see "1 MB" in the "#backupsize_C1" "css_element"
    And I should see "2 MB" in the "#coursesize_C1" "css_element"
    And I wait "10" seconds
    And I log out
  @javascript
  Scenario: Check coursesize report after sharing same file in two course course 1 and course 2
    When I log in as "teacher1"
    And I follow "Course 2"
    And I turn editing mode on
    And I add a "File" to section "1"
    And I set the following fields to these values:
      | Name                      | Myfile     |
    And I upload "report/coursesize/tests/fixtures/COPYING.txt" file to "Select files" filemanager
    And I press "Save and return to course"
    And I log out
    When I log in as "admin"
    And I navigate to "Reports > Course size" in site administration
    Then I should see "File usage report"
    And I should see "0 MB" in the "#backupsize_C1" "css_element"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I click on "view stats" "link"
    Then I should see "Course Size Overview"
    And I should see "1 MB" in the "#sharedsize_C1" "css_element"
    And I should see "0 MB" in the "#recoverablesize_C1" "css_element"
    And I should see "0 MB" in the "#backupsize_C1" "css_element"
    And I should see "1 MB" in the "#coursesize_C1" "css_element"
    And I should not see "C3"
    And I wait "10" seconds
