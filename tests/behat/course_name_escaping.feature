@local @local_mail @javascript
Feature: Course names are shown as text in the mailbox
  In order to read my mail safely
  As a course participant
  I need course names that look like markup to be shown as text, never run as HTML

  Scenario: An entity-encoded course name stays text in the course selector
    Given the following "courses" exist:
      | fullname                                                    | shortname                                                   |
      | &lt;img src=x onerror=document.body.dataset.mailxss=1&gt; | &lt;img src=x onerror=document.body.dataset.mailxss=1&gt; |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course                                                      | role    |
      | student1 | &lt;img src=x onerror=document.body.dataset.mailxss=1&gt; | student |
    And I log in as "student1"
    When I visit "/local/mail/view.php?t=inbox"
    And I click on "All courses" "button"
    Then I should see "<img src=x onerror=document.body.dataset.mailxss=1>" in the ".local-mail-select-course-item" "css_element"
    And "body[data-mailxss]" "css_element" should not exist
