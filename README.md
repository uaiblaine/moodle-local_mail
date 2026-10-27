# Local Mail plugin for Moodle

This plugin allows users to send messages to each other, using an
interface and features similar to web-based mail clients.

Messages are tied to courses, so users can only send messages to other
participants in courses they are enrolled in.

Users can access all mail features through the the envelope icon
present in the header of the site, or the main menu of the Moodle app.

Project page: https://moodle.org/plugins/local_mail

## Compatibility

This fork is developed and tested against Moodle 4.5, 5.0, 5.1, 5.2 and 5.3
(`$plugin->supported = [405, 503]`), and requires Moodle 4.5.

## Installation

Unpack archive inside `/path/to/moodle/local/mail`

For general instructions on installing plugins see:
https://docs.moodle.org/503/en/Installing_plugins

## Settings

All settings are stored per plugin (`local_mail/<name>`) and are found under
*Site administration > Plugins > Local plugins > Mail*.

- `enablebackup` (default on): includes mail messages and labels in course
  backups and restores them.
- `maxrecipients` (default 100), `usersearchlimit` (default 100), `maxfiles`
  (default 20), `maxbytes` (default: the site's upload limit) and
  `autosaveinterval` (default 5 seconds): limits of the message form.
- `globaltrays`, `coursetrays`, `coursetraysname`, `filterbycourse`,
  `coursebadges`, `coursebadgeslength` and `courselink`: which trays and course
  names the mailbox shows.
- `entrypoints` (all on by default): where "Send mail" opens a short message
  form inside a course: a link in the user details of a course profile, a
  button beside the user's name there, and an option of the participants
  page's "With selected users..." menu. Never on the global profile or the
  site course, and only towards users the sender may mail in that course.
- `incrementalsearch` (default on) and `incrementalsearchlimit` (default 1000):
  results while typing, over that many recent messages.
- The retention policy, off by default (`retentionenabled`):
  `retentionupdatesdays` (default 30) moves generated mail ("Updates") to the
  trash, `retentionupdatestrashdays` (default 90) and `retentiontrashdays`
  (default 0, keep) remove mail from the trash for good, and `retentionpurge`
  (default off) deletes the messages no participant holds any more, with their
  attachments. The nightly *Apply the mail retention policy* task carries it
  out; `cli/retention.php --dry-run` shows what it would do.

## Capabilities

- `local/mail:usemail` — use the mailbox in a course: read, write and send
  messages to its participants (students, teachers and managers by default;
  carries `RISK_SPAM`).
- `local/mail:mailsamerole` — also send mail to users who hold the same role
  in the course (same defaults; carries `RISK_SPAM`).

## Data stored

The plugin keeps personal data in five tables:

- `local_mail_messages`, one row per message or draft: the course, subject,
  content and format, number of attachments, whether it is a draft, the time,
  normalised copies of subject and content for searching, and the component
  that generated it, if any.
- `local_mail_message_users`, one row per participant of a message: the user,
  their role (sender, to, cc, bcc), whether they have read, starred or deleted
  it, its category (generated "Updates" or correspondence) and when they
  deleted it.
- `local_mail_message_labels`, one row per label applied to a message.
- `local_mail_message_refs`, the earlier messages a reply or forward refers to.
- `local_mail_labels`, the labels each user created: owner, name and colour.

Attachments are stored as files of the plugin (area `message` in the course
context). Two user preferences are kept: `local_mail_mailsperpage` and
`local_mail_markasread`.

Mail is kept until it is removed: a draft is deleted by its author; a sent
message stays while any participant still holds it, and is deleted only by the
retention policy (when `retentionpurge` is on) or with its course, when every
table row and attachment of that course goes. Labels are kept until their owner
deletes them. Uninstalling the plugin removes its tables, its files and both
preferences.

All of it is covered by the Privacy API. A data request exports the user's
labels, the two preferences and every message of the user in each course
(subject, content, attachments, sender, recipients, references and the user's
read, starred, deleted and label state; Bcc recipients only to the sender and
to themselves). A deletion request removes the user's labels; in each course
it erases the subject, content and attachments of the messages the user sent,
for every recipient, and removes the messages they received from their view.
Deleting a course context removes all its mail; deleting a user context
removes that user's labels.

Notifications of new mail go through Moodle's messaging (message provider
`mail`), whose data belongs to core.

## Contributing

See: [CONTRIBUTING.md](CONTRIBUTING.md)

## Credits

Maintainers:

- Marc Català <reskit@gmail.com>
- Albert Gasset <albertgasset@fsfe.org>

Contributors:

- Daniel Barnett
- Manuel Cagigas
- Russell Smith
- Gaël B
- Jorge Matamala

Version 2.0 of the project implemented by the "Recovery, Transformation and Resilience Plan". Funded by the European Union - Next Generation EU. Produced by the UNIMOODLE University Group: Universities of Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca, Illes Balears, València, Rey Juan Carlos, La Laguna, Zaragoza, Málaga, Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria and Burgos.

## Copyright

© 2012-2014 Institut Obert de Catalunya <https://ioc.gencat.cat>

© 2014-2023 Marc Català <reskit@gmail.com>

© 2016-2026 Albert Gasset <albertgasset@fsfe.org>

© 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

## Translations

The English strings ship in the package. Translations are contributed through
[AMOS](https://lang.moodle.org/) and reach sites as language packs, as the
plugins directory requires; a translation directory added to this repository
(`lang/pt_br`, for one) is left out of the release package.

## License

This plugin is distributed under the terms of the GNU General Public License,
version 3 or later.

See the [LICENSE](LICENSE) file (also shipped as `COPYING.txt`) and
[LICENSES/GPL-3.0-or-later.txt](LICENSES/GPL-3.0-or-later.txt) for details.
