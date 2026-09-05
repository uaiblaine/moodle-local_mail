# Claude instructions for `local_mail`

This file is auto-loaded as context whenever Claude works in this plugin's
directory tree. **Fleet-wide standards live in `~/dev/CLAUDE.md`** (coding
style, CI gates, lang-string rules, the `mdl` environment, git rules) — do not
repeat them here. This file keeps only what is true for this plugin.

Plugin context: a Moodle **local** plugin ("Mail") providing a course-scoped
mailbox — messages, per-user labels, attachments, a Svelte single-page
frontend and a full external-function API. It owns five tables
(`local_mail_messages`, `local_mail_message_users`, `local_mail_message_labels`,
`local_mail_message_refs`, `local_mail_labels`) and stores personal data, so it
carries a full metadata + request + userlist privacy provider. The sibling
plugin `message_localmail` (`~/dev/moodle-message_localmail`) is a **message
processor that delivers core notifications into this mailbox** and depends on
this plugin. Supports Moodle **4.5 through 5.2** (`$plugin->requires =
2022112800`, `$plugin->supported = [405, 502]`); CI runs four jobs (5.02 full
matrix, then 5.01 / 5.00 / 4.05 with `one-db-only`) — **update those jobs when
`supported` changes**. Mounted into m405, m501 and m502 at `local/mail`.

**This is a fork of `gitlab.com/moodle-local_mail/moodle-local_mail`, and as of
2026-08-13 it diverges permanently.** Diff size against upstream is no longer a
design constraint; restructuring upstream files is fair game. The upstream SPDX
`@copyright` / `@author` headers stay on every file that already carries them —
that is the licence, not style.

## Agent orchestration budget (fleet rule, repeated here on purpose)

This is section 6 of `~/dev/CLAUDE.md`, mirrored into every repo of the fleet.
It is the one fleet rule these files are allowed to duplicate: a session opened
inside a plugin directory does not always carry the fleet file in context, and
the cost of missing this rule is paid immediately, in tokens, before anyone
notices it was missing.

**Every `Agent` call and every `agent()` inside a Workflow sets `model`
explicitly.** An omitted `model` runs that subagent on the session model — the
most expensive one — and is a defect, not a default:

- `sonnet` — readers, graders, refuters, verifiers, measurers, stale-reference
  sweeps, mechanical renames, test files written against a stated contract.
- `opus` — implementers of non-trivial code, ADR and documentation drafters,
  consolidators, critics, estimators.
- the session model — only for work done inline in the main loop, never for a
  subagent.

Multi-agent workflows stay opt-in and lean whatever mode is on: size the fan-out
to the question (roughly 10 to 25 agents), one refuter per finding and only for
blocking findings, no open-ended "investigate every gap" rounds. Stop and resume
with `resumeFromRunId` rather than relaunching, so completed agents stay cached.
State which model each role got when reporting a launch.

Measured 2026-09-02 on the hub category-context gap analysis: 7 lenses x 2
refuters x 2 measurers plus a critic round, every one of them on the session
model, had to be interrupted for cost — 36 agents with the refuters on Sonnet
produced the same verified result. The rule has been restated three times
(2026-09-01, 2026-09-02, 2026-09-04), the last time over implementers launched
without `model` while the reviewers around them were correctly downgraded.

## Commands

```sh
mdl ci moodle-local_mail                 # full CI locally before any push
mdl phpunit m501 local_mail              # targeted tests
mdl phpunit m501 local/mail/tests/x.php  # one file
mdl behat m501 @local_mail               # Behat smoke tests
mdl purge m501                           # after PHP changes that affect output
cd svelte && npm run build               # rebuild the mailbox bundle (see below)
```

The host carries no node, so the Svelte gate reproduces through a container. This
runs what `.github/workflows/svelte.yml` runs, from the plugin root — the mount
path puts the plugin where its TinyMCE type import expects it, and the bundle it
writes into `svelte/build/` is the one to commit:

```sh
docker run --rm -v "$PWD":/moodle/public/local/mail \
  -v "$HOME/dev/moodle-502/public/lib/editor/tiny/js/tinymce/tinymce.d.ts":/moodle/public/lib/editor/tiny/js/tinymce/tinymce.d.ts:ro \
  -w /moodle/public/local/mail/svelte node:22-alpine \
  sh -c 'npm ci && npm run lint && npm run check && npm run build'
git status --porcelain -- svelte/build/   # anything listed here must be committed
```

`cli/generate.php` seeds bulk demo mail; it is a development tool, not a
maintenance script.

## Code layout

```
classes/message.php        The model and the ONLY state-transition API.
classes/message_data.php   Input DTO for create()/update(); reply()/forward().
classes/message_search.php Every listing and count query; per-user by design.
classes/external.php       ~40 external functions; the whole UI talks to these.
classes/output/strings.php Ships lang strings to the JS app.
svelte/src/                The mailbox SPA (TypeScript + Svelte 4, no runes).
svelte/build/              COMMITTED compiled bundle; view.php reads manifest.json.
amd/src/navbar.js          Site-wide envelope badge, loaded on EVERY page.
templates/                 Only the notification email templates, not the UI.
```

## Architecture gotchas

**`svelte/build/` is a committed artefact, gated by its own workflow.**
`.github/workflows/ci.yml` has no npm step and moodle-plugin-ci's grunt leg only
sees `amd/src` and `styles.css`, so `.github/workflows/svelte.yml` carries the
Svelte side alone: it rebuilds from `svelte/src` and fails when the result
differs from what is committed. The rule it enforces is unchanged — a
`svelte/src` edit must ship its rebuild, its new hashed files, the updated
`manifest.json` and a `git rm` of the superseded files, all in the same commit —
but forgetting it is now an error instead of silence. It used to be silence
because the filenames are content-hashed and a stale manifest loads the
**previous** bundle rather than erroring.

Two things about that workflow are load-bearing. It checks the plugin out at
`local/mail`, because `svelte/src/lib/amd.ts` imports the TinyMCE types five
levels up, which is the Moodle root on 4.5 and `public/` on 5.x — at the
workspace root that path escapes the checkout, and `npm run check` reports two
errors that are an artefact of the layout rather than a defect. And it detects
staleness with `git status`, not `git diff`: vite empties the output directory,
so a rebuild leaves the new bundle **untracked**, which `git diff` does not
report. `npm run check` and `npm run lint` both fail on warnings, per the fleet
zero-warning policy.

**Adding a member to the `Tray` union produces zero compile errors.** Every
tray dispatch is a ternary with a silent fallback (`store.ts` builds the query,
`View.svelte` the heading), and `url.ts` casts the raw `t=` query parameter to
`Tray` with no validation — an unknown value yields an unfiltered listing under
a blank heading.

**`set_deleted()` is the only state-transition API, and it never deletes a sent
message.** `$fulldelete` requires `$this->draft`, so a sent message every
participant set to `DELETED_FOREVER` keeps its row, every per-user row and all
its attachment bytes. Physical removal of a sent message happens in exactly two
other places: `delete_course_data()` on course deletion, and `message::purge()`,
which the retention task calls once every participant has let go. Both work in
raw SQL over ids and build no message object, because `get_many()` resolves each
message's course and throws when one has gone — which is the state some of those
rows are in.

**`DELETED_CONTENT` (3) is not a fourth per-user state.** Its assert requires
`ROLE_FROM`: it is the sender's global content erase, blanking subject/content
for everyone and deleting the file area. It is also the only existing code path
that frees attachment bytes. Never write it from a sweep, and never write
*over* it: a row already at 3 fails every clause of the assert, and `assert()`
is compiled out in production, so execution falls through and corrupts the
placeholder substitution in `get_many()`.

**A field omitted from `update()`'s `$messagerecord` is immutable, not
vulnerable.** That record names nine properties and `draft` is not among them,
yet drafts survive autosave, because `update_record` emits `SET` only for
present properties. This is the mechanism that makes write-once provenance work.

**`message_search` cannot serve a cross-user sweep.** Its constructor takes a
`user` and `get_base_sql()` hardcodes that userid and scopes courses through
`enrol_get_users_courses` + `local/mail:usemail`. Background tasks must query
`local_mail_message_users` directly, or they will never see rows belonging to
users who lost course access.

**`external::get_message_response()` renders every reference with no visibility
check** — subject, full body, sender and live attachment URLs — and
`user::can_view_files()` grants access if any forward reference is viewable.
Combined with `message::create()` **flattening** the ancestry (each message
holds a row to every ancestor, and inherits the reference's refs *and* labels),
anything hidden from a user remains readable through any descendant reply.

**Three separate unread counters, not one code path.** The sidebar Inbox badge
sums `course.unread` from `get_courses`' `count_per_course()` sweep; label
badges come from `count_per_label()`; the site-wide navbar envelope is its own
hardcoded `count_messages` call in `amd/src/navbar.js`. They must move
together or the badge disagrees with the list. Note `count_per_course` selects
`courseid` first and `get_records_sql` keys on the first column, so adding a
second `GROUP BY` dimension silently overwrites rows — copy `count_per_label`'s
`SELECT MIN(i.id)` idiom instead.

**`tests/upgrade_test.xml` is the pre-2.0 *input* schema, not a snapshot of the
expected result.** Keep it frozen: adding a column there would place it before
the upgrade runs, so the new step would never be exercised.

**`$dbman->add_index()` throws on a duplicate — it does not skip** — and
`index_exists()` never reads the index name, it compares the column *set*,
order-insensitively. Two consequences: always guard `add_index` with
`index_exists`, and know that re-ordering columns within an existing index is
invisible to that guard, so any re-slot must be an unconditional drop-then-add.
Also, long composite index names truncate to the same generated name, so an
index rebuild must drop before it adds or the new index gets a counter suffix
that diverges from a fresh install forever.

**Companion plugin behaviour that surprises everyone**: `message_localmail`
sets `$data->to = [$recipient]` — **one `local_mail` message per recipient** —
and substitutes the configured system sender **only** when the original sender
is a deleted/noreply stub. Ordinary notifications therefore carry the real
author as `ROLE_FROM`, so one forum post to a large course creates one message
row and one `ROLE_FROM` row per student, all in that teacher's Sent tray. It
also calls `message::create()`/`send()` directly, bypassing the
`local_mail_markasread` preference, so delivered mail is always unread.

## Language packs

**There is only `lang/en`.** Translations come from AMOS upstream, so the
fleet's en ↔ pt_br lockstep rule has nothing to lock to here and a pt_br pack
must not be added — it would fight AMOS. Alphabetical ordering still applies.

Note also that the repo's `.phpcs.xml` is dead config: moodle-plugin-ci always
runs `--standard=moodle` and ignores it.

## Testing notes

`classes/test/testcase.php` carries the generator; `generate_random_data()`
builds every message through the public API, so any new message field it does
not set stays at its default across the whole fixture — which silently blinds
`tests/backup_test.php`, whose strongest assertion compares whole DB records
old-vs-new. Teach the generator to vary a new field in the same change that
adds it.

`tests/message_search_test.php` re-implements the entire filter set in PHP as
its expected-value oracle. A filter added to the SQL but not to that chain
makes every new assertion vacuously green.

**Test metadata stays in doc-comments here, against the fleet standard, and the
reason is the 4.05 leg.** The phpcs standard Moodle 4.5 vendors does not read PHP
attributes for coverage, so `#[CoversClass]` is invisible to it and every method
in the file raises `moodle.PHPUnit.TestCaseCovers.Missing` — a warning, which
`--max-warnings 0` turns into a failed build. It passes on 5.00 through 5.02,
so a run on the default branch will not catch it. Use `@covers` at class level
until `$plugin->supported` drops 405. PHPUnit 11.5 reports the doc-comments as
deprecations (48 on a full run) but does not fail on them.

## When in doubt

Follow the patterns in existing files. The codebase is internally
consistent — if a new file feels like it matches no existing shape,
re-examine the approach.
