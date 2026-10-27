@local @local_mail @javascript
Feature: Send mail from course pages
  In order to reach a teacher or a group of students from where they appear
  As a course participant
  I need a "Send mail" entry point that opens a short message form

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teresa    | Teacher  |
      | student1 | Sam       | Student  |
      | student2 | Sara      | Second   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |

  Scenario: A student mails the teacher from the teacher's course profile
    Given I am on the "C1" "enrolled users" page logged in as "student1"
    And I click on "Teresa Teacher" "link" in the "participants" "table"
    And I should see "Send mail" in the ".userprofile .page-context-header" "css_element"
    When I click on "Send mail" "link" in the ".local-mail-sendmail" "css_element"
    And I set the following fields to these values:
      | Subject | Question about the course |
      | Message | When is the deadline?     |
    And I click on "Send" "button" in the "Send mail" "dialogue"
    Then I should see "Message sent"
    And I log out
    And I log in as "teacher1"
    And I visit "/local/mail/view.php?t=inbox"
    And I should see "Question about the course"

  Scenario: The global profile offers no mail
    When I am on the "teacher1" "user > profile" page logged in as "student1"
    Then I should not see "Send mail"

  Scenario: A teacher mails selected participants, in Bcc by default
    Given I am on the "C1" "enrolled users" page logged in as "teacher1"
    And I click on "Select 'Sam Student'" "checkbox"
    And I click on "Select 'Sara Second'" "checkbox"
    When I set the field "With selected users..." to "Send mail"
    Then "Send mail" "dialogue" should be visible
    And the field "Send as" matches value "Bcc"
    And I set the following fields to these values:
      | Subject | Reminder         |
      | Message | Submit by Friday |
    And I click on "Send" "button" in the "Send mail" "dialogue"
    And I should see "Message sent"
    And I log out
    And I log in as "student2"
    And I visit "/local/mail/view.php?t=inbox"
    And I should see "Reminder"

  Scenario: The administrator switches the entry points off
    Given the following config values are set as admin:
      | entrypoints |  | local_mail |
    When I am on the "C1" "enrolled users" page logged in as "teacher1"
    Then the "With selected users..." select box should not contain "Send mail"
    And I click on "Sam Student" "link" in the "participants" "table"
    And I should not see "Send mail"
