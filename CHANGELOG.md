# Changelog

## [Unreleased]

### Added

- New responsive user interface.
- Support for the Moodle app.
- Auto-save of message dratfs.
- Instant search results displayed while user is typing in the search box.
- Pop-up notifications when sending, deleting and restoring messages.
- Preference: Enable or disable email and mobile push notifications.
- Setting: Maximum number of recipients per message.
- Setting: Maximum number of results displayed in the user search.
- Setting: Hide starred, sent, drafts or trash trays.
- Setting: Display course trays or display only course trays with unread messages.
- Setting: Use full name for course trays.
- Setting: Show selector to filter trays and messages by course.
- Setting: Hide or use full name for course badges.
- Setting: Limit the length of course badges.
- Setting: Enable or disabled instant search.
- Setting: Maximum number of recent messages included in instant search.
- Setting: Display a link to the curret course at the top of the page.
- New test data generator script (for developers).

### Changed

- E-mail notifications now include all the content of the message.
- Forwarded messages are embedded in the new message instead of being included as a reference.
- New way of filtering messages by course (course trays are still available but disabled by default).
- Redesigned web service functions that covers all the functionality of the plugin.

### Fixed

- Creating and restoring course backups with mail data.
- Messages from courses not visible by the user are no longer displayed.
