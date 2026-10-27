# Plan: course-contextual entry points for Mail, and the MooTube messaging seam

Status: **plan, nothing implemented** (2026-10-27). Two repositories: `moodle-local_mail`
(this one) and `moodle-format_mtube-503` (private, vendor code with local patches).

## 1. Goal

A student must be able to reach a teacher through Mail from the places the teacher appears in a
course, and a teacher must be able to mail selected participants, without leaving the page. Mail is
course-scoped: every entry point exists only inside a course, never on the global user pages.

## 2. Decisions (owner, 2026-10-27)

| Id | Decision |
|---|---|
| A1 | Profile entry point: a node in the profile's contact section, plus a header button. |
| A2 | Participants page: an option inside core's "With selected users..." dropdown (not a separate button). |
| A3 | Each core-page entry point can be switched off in the Mail settings. |
| B2/D2 | Compose in a modal (subject + message) that sends straight to the recipient's Mail inbox; no "open the mailbox" first step. |
| B3/D3 | MooTube gets a new extension type, `messaging_provider`, in its existing `extensions_registration` seam; local_mail registers it. |
| B4/D4 | A "Contact the course team" button that mails every listed course contact at once, switched on per course in the MooTube course format options. |
| D1 | Only in course context: `/user/view.php?course=<id>` and `/user/index.php?id=<id>` with a non-site course. `/user/profile.php` (global profile) and the site course get nothing. |
| D5 | MooTube routes its message actions to the provider when one is registered; a MooTube site setting can send them back to core messaging. |
| D6 | Remove the course contact's `mailto:` e-mail link from the MooTube template now. |

## 3. Verified facts the plan rests on

- **F1. Nothing to configure back.** The old "Send mail" buttons on `/user/view.php`,
  `/user/index.php` and `/blocks/completion_progress/overview.php` were injected by
  `svelte/src/navigation.ts`, deleted in this fork by `a257fd7` (2.17) for performance; `lib.php`'s
  `local_mail_render_navbar_output()` docblock records the page-scoped reimplementation as pending.
  The profile button only ever appeared with `course=` in the URL.
- **F2. Send path.** `external::send_message()` validates recipients with
  `user_search(sender, course)->include = ids` (role visibility, `local/mail:mailsamerole`, groups),
  enforces `maxrecipients` and a non-empty subject, sends, fires `message_sent`, then sends one
  notification per recipient (`db/messages.php`: e-mail on by default). `create.php` creates a
  draft with `course`, `recipients`, `role` and redirects to it.
- **F3. Participants page (4.5 and 5.3 identical).** `user/index.php` builds the dropdown
  (`#formactionid`) itself; plugins can only add enrolment bulk operations, and
  `user/action_redir.php` accepts `bulkchange.php` and nothing else. `core_user/participants`
  handles any value containing `#` client-side: `preventDefault()`, collects the checked
  `user<id>` boxes, ignores an unknown `#` value, and resets the select **in the same handler**.
  So an injected `#local_mail_compose` option is safe from a server round-trip, but our handler must
  read the value and the checked ids before core's handler resets it.
- **F4. Profile.** `user/view.php` redirects `course=SITEID` to `profile.php`, and sets the page
  type to `course-view-<format>` (so `course-view-mtube` on a MooTube course): guard by script
  path, not page type. `core_myprofile_navigation()` receives `$course = null` on `profile.php`.
  Core's own "Message" button lives in `context_header()`'s user buttons, which plugins cannot
  extend; `$PAGE->add_header_action()` renders in `full_header()`'s `headeractions` instead.
- **F5. MooTube is licence-hashed.** `tools/delivery/check_hashed_files.sh` requires `lib.php`,
  `format.php` and `classes/task/validate.php` to stay byte-identical to the vendor delivery, on
  `main` and `dev`. Nothing in this plan touches them.
- **F6. MooTube course options are one JSON blob** (`formatoptionstext`) that
  `classes/form/formatoptions_form.php` saves whole; `get_format_options()` decodes it, fills the
  defaults and keeps unknown keys (`format_mtube_scalarize_format_options()` drops only `mform_*`).
  A new option is added in the form alone and read with a default.
- **F7. Course contacts** are built in `format.php` (hashed) and rendered by
  `templates/coursecontact.mustache` (not hashed) with `userid`, `name`, `email`, `url`,
  `description`, `profile`, `isguestuser`, and no course id. With no description the template prints
  a `mailto:` link to every enrolled non-guest, regardless of `maildisplay` or the identity fields.
- **F8. MooTube JS is build-only.** A change to a vendor AMD module means rebuilding from the map
  source and replaying it after every delivery (mtube `CLAUDE.md`, timeline note). The plan adds no
  vendor JS change: the provider's AMD module lives in local_mail.
- **F9. MooTube's own message actions use core messaging**: the hover card
  (`get_participant_card.php`, `canmessage = !empty($CFG->messaging) && ...`), the user report
  modal header button and the participants modal bulk action all call core's `showSendMessage`.
  m503b runs with `messaging = 0`, so all of them are hidden there.
- **F10. local_mail supports `[405, 503]` from one `main` branch**: Bootstrap 4/5 dual rules,
  `@covers` docblocks rather than attributes.

## 4. Design

### L1. local_mail: the quick-compose modal

- `local_mail\form\quick_compose`, a `core_form\dynamic_form` opened with `core_form/modalform`.
  Fields: subject, message (`editor`, TinyMCE, no file uploads in v1, P4), hidden `courseid`,
  `recipients`, `role` (`to`/`bcc`, P1), and a static recipient summary (names; "N recipients"
  past a handful).
  - `check_access_for_dynamic_submission()`: `require_login()`, the course exists and
    `user->can_use_mail($course)`; the course id is never `SITEID`.
  - `process_dynamic_submission()`: recipients validated **before** any row is written (the
    `user_search` include check from F2 against the submitted ids), then `message::create()` and the
    send routine. Returns the message id for the success toast's "View in Sent" link.
- The send routine of `external::send_message()` is extracted into one method both callers use, so
  the rules of F2 have a single home.
- `local_mail/quick_compose` AMD module: `open({courseid, userids, role})`. It is the public
  entry other code calls; the modal footer carries "Open in Mail" (posts to `create.php` with the
  same recipients) for attachments, Cc and the full editor.
- Recipient reachability for buttons comes from a helper
  `user_search` with `include` (one query for a list), so a button never appears for a recipient the
  send would reject.

### L2. local_mail: profile (A1)

- `local_mail_myprofile_navigation()`: a node in the `contact` category when `$course` is a
  non-site course, the profile is not the viewer's own, the viewer can use Mail there and the user
  is reachable. Its `href` is the no-JS fallback (`create.php` with `sesskey`); JS opens the modal.
- Header button: not `$PAGE->add_header_action()` (S0c: on `user/view.php` it would sit beside the
  course name). A `before_footer_html_generation` callback, guarded by script `/user/view.php`, a
  non-site course and the same conditions, calls the AMD init, which puts the button in the user's
  own header (`.userprofile .page-context-header .header-button-group`, created when core printed
  none), where core's "Message" button lives and where the pre-2.17 button was.
- Both behind the A3 setting.

### L3. local_mail: participants (A2)

- A `before_footer_html_generation` callback, guarded by script `/user/index.php`, a non-site
  course, the A3 setting and `can_use_mail`, calls `local_mail/participants` `init`.
- The module appends `<option value="#local_mail_compose">` to `#formactionid` and reads the value
  and the checked ids before core's handler resets the select (F3; mechanism fixed by spike S1),
  removes the viewer's own id, and opens the modal with `role` per decision P1.

### L4. local_mail: settings (A3)

`local_mail/entrypoints`, an `admin_setting_configmulticheckbox` with `profilenode`,
`profileheader`, `participants`, all on by default (it restores the pre-2.17 behaviour). The MooTube
integration is switched in MooTube (D5, B4), not here.

### M1. MooTube: the `messaging_provider` seam (non-hashed files only)

- `classes/local/extensions/messaging_provider.php`:
  - `get_id()`, `get_label()`, `get_icon()`;
  - `is_available(\stdClass $course, \context_course $context): bool`: the viewer can use the
    channel in this course;
  - `filter_reachable(\stdClass $course, \context_course $context, array $userids): array`: the
    provider, not MooTube, decides whom the viewer can reach;
  - `get_amd_module(): string`: exports `initCourseContacts(config)` and `compose(config)`.
- `extensions_registration::register_messaging_provider()` and
  `manager::get_messaging_provider()`, first registration wins, as for the other types.
- Site setting `format_mtube/messagingchannel` (`provider` default, `core`) for D5.
- Course option `coursecontactsmessaging` in `formatoptions_form.php` (F6): `none`, `contact`
  (envelope per contact, default), `contactandteam` (plus the B4 button).
- `classes/hook_callbacks.php` (not hashed), on `page-course-view-mtube` outside edit mode: when a
  provider is available and the option is not `none`, `js_call_amd()` on the provider's module with
  the course id, the reachable contact ids and whether to show the team button.
- `templates/coursecontact.mustache`: stable hooks (`data-region="mtube-coursecontacts"`,
  `data-region="mtube-coursecontact"` with `data-userid`, an empty actions slot per contact and one
  team slot), and the `mailto:` block removed (D6). Slot spacing in `styles.css`.
- Hover card (F9), if spike S2 allows: `get_participant_card.php` emits a provider action when the
  channel is `provider`; `participant_card.mustache` renders it; the provider module handles it by
  delegated click. The user report header button and the participants modal bulk action stay on
  core messaging in v1 (vendor JS); they go to the vendor as a patch proposal.
- An entry in mtube `CLAUDE.md` "Local patches"; `check_hashed_files.sh` passes on `main` and `dev`.

### M2. local_mail: the MooTube provider

- `db/hooks.php` listens to `\format_mtube\hook\extensions_registration`; the provider class
  implements the MooTube interface and is only autoloaded when that hook is dispatched, so
  local_mail keeps no hard dependency on `format_mtube` (verified in S0). README names the optional
  integration.
- `local_mail/mtube_contacts`: fills the slots with an envelope icon button
  (`aria-label` "Send Mail to {name}") and the team button, both opening L1's modal.

## 5. Stages

| Stage | Repo | Content | Gate |
|---|---|---|---|
| S0 | both | Spikes, done 2026-10-27: results in section 7 | section 7 |
| 1 | local_mail | L1 + send routine extraction + rule G1 in that routine | PHPUnit, `mdl ci --matrix` |
| 2 | local_mail | L2, L3, L4 | PHPUnit + Behat smoke (profile node, participants bulk) |
| 3 | mtube-503 | M1 + D6 | PHPUnit, Behat, hashed-file check |
| 4 | local_mail | M2 | Behat: student mails the teacher from a MooTube course; the teacher sees it in the inbox |
| 5 | mtube-503 | Hover card (if S2) and the vendor patch proposal in `tools/vendor-patches/` | as 3 |

Before each push: `security-diff-read` (the diff sends mail on a user's behalf, `RISK_SPAM`).

Tests that pin the decisions: no entry point on `profile.php`, on the site course or on one's own
profile; a recipient outside the viewer's `user_search` gets no button and a forged submission
is rejected with no draft left; `maxrecipients`; the A3 switches; the MooTube option `none`; no
`mailto:` in the contacts block. Every guard mutation-checked.

## 6. Smaller decisions (owner, 2026-10-27)

- **P1.** Bulk from participants: Bcc by default when there is more than one recipient.
- **P2.** B4 recipients: the contacts listed on the course page; when there are none, every
  teacher of the course (editing and non-editing). Either way only the ones the sender may reach,
  and separate groups are respected: a teacher limited to a group is reachable from that group
  only (rule G1, section 7).
- **P3.** MooTube 5.3 only (`moodle-format_mtube-503`); the 5.2 twin is not patched.
- **P4.** Message body: the HTML editor (TinyMCE) in the modal; attachments and Cc through
  "Open in Mail".
- **P5.** Offer the seam to the MooTube vendor once everything here is tested.

## 7. S0 results (2026-10-27)

- **S0a. A listener for an absent hook is silent.** `core\hook\manager::add_component_callbacks()`
  stores callbacks under the hook class name without `class_exists()`; the callback is validated
  only on dispatch, and an absent `format_mtube` never dispatches. Run on m501 (no `format_mtube`):
  a `db/hooks.php` entry for `\format_mtube\hook\extensions_registration` registered one callback
  and printed no debugging. Same code on 4.5. local_mail needs no dependency on `format_mtube`; the
  provider class (which implements the MooTube interface) is autoloaded only from that callback,
  and its PHPUnit test skips when `format_mtube` is absent.
- **S0b. Participants dropdown: the mechanism works, mouse and keyboard.** Core's
  `accessibleChange` handler is bound on the select itself and resets it before the event bubbles,
  and binding order between two AMD inits is not guaranteed. So L3 uses two listeners: a
  capture-phase native `change` listener on `document` that only records the select's value, and a
  delegated `cie:accessibleChange` handler on `document` that acts when the recorded value is ours,
  then clears it. Throwaway Behat on m503b (Chrome on Linux, the non-Mac path; 2 scenarios, 16
  steps passed, each ending in a deliberate failure that printed the state):
  - mouse (`I set the field`): recorded `#local_mail_compose`, checked ids `[4]`, form not submitted;
  - keyboard (focus, End, Enter): recorded `#local_mail_compose`, ids read, form not submitted;
  - control: the select's own value inside our handler was already `''` in both, so reading
    `select.value` there would never fire;
  - after the reset, core fires `accessibleChange` once more with `''` on the mouse path; our
    handler must ignore any recorded value that is not ours (it did).
  On Mac and touch devices core triggers on `change` directly; the capture listener still runs
  before it. Stage 2's Behat feature pins this for real.
- **S0c. No `add_header_action()` on the profile.** `user/view.php` sets the page heading to the
  course name and prints the user's header itself (`context_header()`, line 191), so header actions
  render beside the course name. The user's header has a `.header-button-group` (core
  `context_header.mustache`, same in 4.5; not overridden by Boost Union, theme_dimensions or Moove):
  L2 places the button there by JS.
- **S0d. MooTube's hover card swallows every `[data-action]` click** inside `.mtube-hover-card`:
  a bubble-phase `document` listener calls `preventDefault()`, `stopPropagation()`, disposes the
  card and ignores an action it does not know (vendor `hover_card.js`, read from the map). A
  provider action works without touching vendor JS when the provider's module listens on
  `document` in the capture phase; the card then closes as for any other action.

### Rule G1: separate groups (found while answering P2)

**local_mail does not enforce separate groups when it sends.** `external::search_users()` refuses
group 0 to a user without `moodle/site:accessallgroups` in a separate-groups course
(`user::can_view_group()`), so the composer only lists members of the sender's groups. But the send
path checks recipients with `user_search` and `include` alone (`external::send_message()`), and
`user_search::get_base_sql()` filters by group only when a `groupid` is given: an id typed into a
request, a `create.php?recipients=` link or a draft's recipient list reaches anyone enrolled with
`local/mail:usemail`, across groups. The composer's group filter is the convenient fact; the rule
the course promises is not recorded anywhere the send checks.

G1, proposed for the shared send routine (stage 1) and for every reachability check in this plan:
in a course whose group mode is separate groups, a recipient is reachable when the sender holds
`moodle/site:accessallgroups`, or the recipient does, or both share a group. The second clause
keeps a teacher who sees all groups (the editing teacher, usually in no group) reachable by every
student, and leaves a non-editing teacher limited to group A unreachable from group B, which is
what P2 asks for. To decide with the owner: whether the composer's user list follows G1 too (it
would show teachers with `accessallgroups` under every group), so what it lists and what it sends
agree.

## 8. Stage 1 status (2026-10-27, branch `feature/contextual-entry-points-stage1`, not committed)

- Rule G1 in `user_search` (composer list and send alike); `local\sender` holds the send rules
  and `external::send_message()` calls it; `sender::send()` checks itself that the sender may
  edit the draft.
- `form\quick_compose` + `amd/src/quick_compose.js`: TinyMCE body, To/Bcc (Bcc by default for
  more than one recipient), "Open in the full editor" link, toast with a link to the sent message.
  The recipient summary names only users the sender may mail; any other requested id is counted,
  never named (found by the security read: names were fetched for any site user id).
- Two oracle cases of `user_search_cases()` in course 3 (separate groups, nobody with
  `accessallgroups`) now expect no users.
- Version 2026102700, CHANGELOG updated.
- Gates: full suite 178 tests on m503; `mutations/recipients.conf`, 15 mutants, every one red on
  pgsql and MariaDB with every named test red; `mdl ci moodle-local_mail --matrix`, 15 of 15 legs
  passed on the final tree.
