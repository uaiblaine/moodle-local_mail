@local @local_mail @format_mtube @javascript
Feature: Mail the course contacts of a MooTube course
  In order to reach the teachers of a MooTube course from its course page
  As a student
  I need an envelope beside each course contact and, when the course offers it, a course team button

  Background:
    Given the "format_mtube" plugin is installed
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Tia       | Teacher  |
      | teacher2 | Tom       | Tutor    |
      | student1 | Sam       | Student  |
    And the following "courses" exist:
      | fullname   | shortname | format | groupmode | groupmodeforce |
      | MooTube 1  | MT1       | mtube  | 1         | 1              |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | MT1    | editingteacher |
      | teacher2 | MT1    | teacher        |
      | student1 | MT1    | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | MT1    | GA       |
      | Group B | MT1    | GB       |
    And the following "group members" exist:
      | user     | group |
      | student1 | GA    |

  Scenario: A student mails a course contact from the envelope beside the name
    Given the MooTube course "MT1" lists "teacher1" as contacts with mail "contact"
    When I am on the "MT1" "Course" page logged in as "student1"
    And I click on "Send mail to Tia Teacher" "button"
    And I set the following fields to these values:
      | Subject | Question about the course |
      | Message | When is the deadline?     |
    And I click on "Send" "button" in the "Send mail" "dialogue"
    Then I should see "Message sent"
    And "Contact the course team" "button" should not exist
    And I log out
    And I log in as "teacher1"
    And I visit "/local/mail/view.php?t=inbox"
    And I should see "Question about the course"

  Scenario: A student writes to the whole course team at once
    # A non-editing teacher sees only their own groups, so Tom shares Sam's.
    Given the following "group members" exist:
      | user     | group |
      | teacher2 | GA    |
    And the MooTube course "MT1" lists "teacher1, teacher2" as contacts with mail "contactandteam"
    When I am on the "MT1" "Course" page logged in as "student1"
    And I click on "Contact the course team" "button"
    Then "Send mail" "dialogue" should be visible
    And the field "Send as" matches value "To"
    And I set the following fields to these values:
      | Subject | Group work      |
      | Message | Can we swap in? |
    And I click on "Send" "button" in the "Send mail" "dialogue"
    And I should see "Message sent"
    And I log out
    And I log in as "teacher2"
    And I visit "/local/mail/view.php?t=inbox"
    And I should see "Group work"

  Scenario: A tutor of another group gets no envelope, and the team falls back to the teachers
    # Without moodle/site:accessallgroups, a non-editing teacher's default, Tom reaches Group B only.
    Given the following "group members" exist:
      | user     | group |
      | teacher2 | GB    |
    And the MooTube course "MT1" lists "teacher2" as contacts with mail "contactandteam"
    When I am on the "MT1" "Course" page logged in as "student1"
    Then "Send mail to Tom Tutor" "button" should not exist
    And I click on "Contact the course team" "button"
    And I should see "Tia Teacher" in the "Send mail" "dialogue"
    And I should not see "Tom Tutor" in the "Send mail" "dialogue"

  Scenario: The course switches the envelopes off
    Given the MooTube course "MT1" lists "teacher1" as contacts with mail "none"
    When I am on the "MT1" "Course" page logged in as "student1"
    Then I should see "Tia Teacher"
    And "Send mail to Tia Teacher" "button" should not exist
