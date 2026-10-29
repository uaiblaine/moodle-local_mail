@local @local_mail @javascript
Feature: Mail in a frozen course is read only
  In order to keep the mail of a course after it is frozen
  As a course participant
  I need to read its messages while nothing can be written there

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teresa    | Teacher  |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And "teacher1" has sent mail "Exam dates" to "student1" in course "C1"

  Scenario: A message in a frozen course opens with a notice instead of the reply buttons
    Given the "C1" "Course" is context frozen
    And I log in as "student1"
    When I visit "/local/mail/view.php?t=inbox"
    And I click on "Exam dates" "link"
    Then I should see "Mail in this course is read only"
    And "Reply" "button" should not exist in the ".local-mail-view" "css_element"
    And "Forward" "button" should not exist in the ".local-mail-view" "css_element"

  Scenario: The same message offers the reply buttons while the course is not frozen
    Given I log in as "student1"
    When I visit "/local/mail/view.php?t=inbox"
    And I click on "Exam dates" "link"
    Then "Reply" "button" should exist in the ".local-mail-view" "css_element"
    And "Forward" "button" should exist in the ".local-mail-view" "css_element"
    And I should not see "Mail in this course is read only"

  # Phone width on purpose: the top toolbar offers its own Send button below the medium
  # breakpoint. The steps are scoped to the mailbox because the core message drawer on every
  # page carries a "Send message" button.
  Scenario: A draft in a frozen course opens as a message that cannot be sent
    Given "student1" has a mail draft "Unsent question" to "teacher1" in course "C1"
    And the "C1" "Course" is context frozen
    And I change viewport size to "mobile"
    And I log in as "student1"
    When I visit "/local/mail/view.php?t=drafts"
    And I click on "Unsent question" "link"
    Then I should see "Mail in this course is read only"
    And "Send" "button" should not exist in the ".local-mail-view" "css_element"

  Scenario: An unfrozen draft keeps its Send button at phone width
    Given "student1" has a mail draft "Unsent question" to "teacher1" in course "C1"
    And I change viewport size to "mobile"
    And I log in as "student1"
    When I visit "/local/mail/view.php?t=drafts"
    And I click on "Unsent question" "link"
    Then "Send" "button" should exist in the ".local-mail-view" "css_element"
