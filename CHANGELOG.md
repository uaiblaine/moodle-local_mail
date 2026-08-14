# Changelog

## [Unreleased]

### Added

- Message provenance. Messages carry a "component" field recording the
  frankenstyle component that generated them, or nothing at all when a person
  composed them. It is written once, at creation, and is deliberately absent
  from the record that an update builds, so autosaving a draft cannot alter it
  and no later call can relabel a message. A matching "category" is
  denormalized onto the per-user rows, taken from the message so that the two
  can never disagree, and it joins the covering index of those rows. This is
  the groundwork for separating generated mail from human correspondence; on
  its own it changes nothing anyone can see.
- Per-user rows record when a message was moved to the trash, in a
  "timedeleted" field, and clear it again when the message is restored. The
  message time is the send time, so without this a retention policy would act
  on the age of the content rather than on how long it had been thrown away.
- CI now checks the compiled mailbox bundle. The Svelte sources compile to
  "svelte/build", which is committed and is what browsers load, but no gate had
  ever looked at it: the filenames carry a content hash and the manifest that
  resolves them silently falls back to the previous bundle, so a source edit
  committed without its rebuild produced working software that was quietly one
  revision behind. A new workflow rebuilds from source on every push and fails
  when the result differs from what is committed, alongside the formatter, the
  linter and the type-checker, all three of which now fail on warnings rather
  than only on errors. Nothing that reaches a site changes.
- The repository carries a CLAUDE.md and a .gitattributes. The latter keeps
  the Svelte sources, the toolchain and the editor configuration out of the
  release zip, which previously shipped all of them; the compiled bundle under
  "svelte/build" still ships, since that is what the browser loads.

### Changed

- Course backups no longer carry generated mail. Notifications are a log of what
  happened in a course rather than correspondence between people, and restoring
  them produced data that was wrong in ways nobody could see: restore rewrites
  the message time, which is the field a retention policy acts on, so moving a
  course start date landed every restored notification either already expired or
  years from expiring, and a course import copied them in as fresh rows
  describing activities whose ids had changed. Human correspondence is backed up
  exactly as before. The whole feature still switches off through the existing
  backup setting.

### Fixed

- Deleting a message now also removes it from the threads that quote it. A
  message being answered was rendered inside the reply with its subject, its
  whole body, its sender and working links to its attachments, with no check of
  whether the reader was allowed to see it — and because each message holds a
  reference to every one of its ancestors, a long thread exposed all of them and
  not merely the parent. Anyone who deleted a message therefore kept reading it
  through their own reply, which would have made a retention policy pointless.
  Being brought into a thread you were never part of still shows you its
  history: somebody chose to reply and include you. Attachments follow the same
  rule, so a link that is no longer shown no longer serves the file either.

- A restored reference whose target was not in the backup is now dropped instead
  of being stored pointing at message zero, where it became a phantom in every
  thread that walked it. References across courses have existed in the wild —
  there is an upgrade step that deletes them — and they are the case that cannot
  be filtered when the backup is written, because the target row is there and
  only the restore can discover that nothing maps to it.

- The upgrade test asserted nothing about the resulting schema. It called
  Moodle's schema check and discarded the return value, and that method
  collects its findings into an array rather than throwing, so a column added
  to install.xml with no matching upgrade step passed the build. The return
  value is now asserted, with the check narrowed to this plugin's own tables.

## [2.17.3] - 2026-08-10

### Changed

- Every PHP file now carries the Moodle GPL boilerplate and full phpdoc, on top
  of the SPDX headers, which are kept as the copyright record. This resolves the
  known issue recorded under 2.17.1: the Moodle Code Checker gates the build
  again, with "phpcs-continue-on-error" removed from all four CI jobs. Apart from
  the version bump itself, the only non-comment edits are a blank line removed
  after a class opening brace and an "implements" list put in alphabetical order,
  both of them required by the checker.
- The "analyze" NPM script builds with source maps before running
  source-map-explorer. Source maps are off by default since 2.17.1, so the script
  could not work as written.

### Removed

- Dead code left behind when the navbar popover was replaced in 2.17: the
  "UserListSendButton" and "UserProfileSendButton" Svelte components, the
  "createUrl" helper that only they used, the "navbar" property of the menu
  component with the CSS it enabled, the styles keyed on the popover class, and
  "local_mail\output\strings::get_many()". The bundle shrinks by about 0.4 kB of
  JavaScript and 0.4 kB of CSS.
- The "classpath" key of all 27 web service registrations. It pointed at
  local/mail/externallib.php, a file that has never existed in this project.
  Moodle only reads that key when the class cannot be autoloaded, so it was
  inert, but it would have turned an autoloading failure into an error about a
  file that was never there.
- Unused declarations: the "XL" member of the viewport size enumeration, the
  "MessageProcessorPreference" interface, the store's "get" accessor, two
  "global $DB" declarations and one unused parameter in the generator script.
- The "tsconfig.node.json" TypeScript project, its reference from
  "tsconfig.json" and the "vite.config.d.ts" it emitted. The project included
  "vite.config.ts", which was renamed to .js upstream, so it had resolved to an
  empty file set ever since.

## [2.17.2] - 2026-08-10

### Fixed

- Alternative full name not shown to users with the `moodle/site:viewfullnames`
  capability (e.g. teachers). Backported from the upstream project, commit
  ec047eb by Jorge Matamala.

## [2.17.1] - 2026-08-10

### Changed

- CI moved to the moodle-an-hochschulen reusable workflow, with one job per
  supported Moodle branch (4.5, 5.0, 5.1, 5.2) and the declared support range
  recorded in version.php.
- The Svelte bundle is no longer built with source maps. The map embedded the
  full node_modules sources, shipping about 460 kB of dev-only data with the
  plugin, and CI rejects the marker words those vendored sources contain.

### Fixed

- Missing upgrade savepoint for the 2024031400 upgrade step.
- Notification templates had no example context and used HTML attributes that
  are obsolete in HTML5 ("border", "cellpadding", "cellspacing", "hr size").
- Incomplete or mismatched phpdoc parameter lists in local_mail\label,
  local_mail\message, local_mail\output\renderer and the test helpers.
- The navbar badge stylesheet no longer relies on "!important".

### Known issues

- The Moodle Code Checker (phpcs) runs with "phpcs-continue-on-error: true" in
  every CI job. moodle-plugin-ci always invokes phpcs with "--standard=moodle"
  and ignores the repository's own .phpcs.xml, so the upstream file headers
  (SPDX instead of the Moodle GPL boilerplate) and the absent class, function
  and constant docblocks produce about 450 errors that only a full reformat of
  the upstream tree would clear. The step still reports its findings; every
  other check gates normally. The reformat is deferred so that merges from the
  upstream repository stay reviewable.

## [2.17] - 2026-05-15

### Added

- GitHub Actions CI workflow.

### Changed

- Replaced the navbar popover with a lightweight unread-count badge.

### Fixed

- Error viewing inbox with language packs missing the "localecldr" string.
- Parameters validation for external service "local_mail_update_message".

## [2.16] - 2026-02-16

### Fixed

- Error viewing inbox when the sender of a message is missing in the database.
- Typos in privacy provider metadata.

## [2.15] - 2025-05-08

### Added

- Privacy provider implementation.

### Changed

- Refactored cache usage to improve performance and reduce memory usage.
- Links to profiles of deleted users are no longer displayed.

### Fixed

- Error when sending messages to a large number of users from the participants page.
- BCC recipients not displayed in the message list.
- Styling issue with deleted users in the message form in Moodle 5.0.
- Error caused by database references to an invalid user with ID 0.

## [2.14] - 2025-04-14

### Added

- Compatibility with Moodle 5.0.

### Fixed

- CORS error in the development server.

## [2.13] - 2025-03-13

### Fixed

- Message not found error when a site administrator opens a message in a hidden course.
- Unsupported text editors are now ignored.
- The user preference for the text editor is now taken into account.

### Changed

- The names of deleted users are now hidden for privacy reasons.

## [2.12] - 2025-01-22

### Changed

- Language strings for Basque, Catalan, Galician, and Spanish are now downloaded from AMOS.

## [2.11] - 2024-10-06

### Fixed

- Spelling error in string 'Not starred'.
- Alignment of icons inside buttons.
- Deprecation warnings in Moodle 4.5.

## [2.10] - 2024-08-06

### Fixed

- The group dropdown was filtered by the default grouping of the course.

## [2.9] - 2024-03-18

### Fixed

- Autosave was reverting changes in subject and recipients.

## [2.8] - 2024-03-15

### Fixed

- Upgrade to versions 2.6/2.7 failed in MySQL.
- The course filter was not kept to "All courses" when creating a message.
- Drafts were marked as changed just after opening them.

## [2.7] - 2024-03-14

### Changed

- Take into account the capability to access all groups when searching users.
- Require only that recipients are enrolled in the course when sending messages.
- Disable and lock web notification output by default.
- Hide disabled and locked notification outputs in the preferences dialog.

### Fixed

- Selected course was not updated when the course of a draft was changed.
- Compose button not working from site pages.
- Error modal hiding immediately after showing up.

## [2.6] - 2024-03-08

### Added

- New setting to configure the autosave interval in seconds.

### Fixed

- The selected course is no longer changed when creating a new messge.
- It was possible to change the course of a reply.
- Tiny editor autosave was enabled although it is redundant.

## [2.5] - 2024-03-04

### Fixed

- Disable interactions while loading new page to prevent double clicks.

## [2.4] - 2024-03-03

### Added

- New button in the message form to save the draft and go back to the list of messages.

### Changed

- The external function `create_label` can assign the created label to a specified list of messages.

### Fixed

- Excessive number of web service calls to autosave drafts.
- Web service requests are now performed sequentially to prevent potential race conditions.
- Superfluous padding in role and group selectors. 

## [2.3] - 2024-02-12

### Added

- A spinner is displayed while waiting for server responses.
- The number of unread messages of each course is now displayed in the course selector.

### Changed

- Numbers are now displayed with thousands separators.
- The text size in the Moodle app now follows the rest of the app.
- The contrast betweem emabled and disabled buttons has been increased.
- The language string about invalid recipients is now more explicit.
- The number of total messages is no longer displayed in small screens.
- The menu entry in the Moodle app is no longer restricted to the "more" tab.

### Fixed

- The toolbar was not always displayed at the bottom in the Moodle app.
- The course selector sometimes exceeded the screen boundaries in the Moodle app.
- The size of form controls and buttons was not always consistent in the Moodle App.
- Language strings for cache definitions were missing.

## [2.2] - 2024-02-03

### Fixed

- Displaying messages sent or received by deleted users.

## [2.1] - 2024-02-02

### Fixed

- Content of references.

## [2.0] - 2024-01-29

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
