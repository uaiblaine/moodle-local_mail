<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_mail;

use invalid_parameter_exception;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/local/mail/locallib.php");

class external extends \external_api {

    const ROLES = [
        message::ROLE_FROM => 'from',
        message::ROLE_TO => 'to',
        message::ROLE_CC => 'cc',
        message::ROLE_BCC => 'bcc',
    ];

    public static function get_settings_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_settings() {
        self::validate_context(\context_system::instance());

        return self::get_settings_raw();
    }

    public static function get_settings_raw() {
        $globaltrays = get_config('local_mail', 'globaltrays');
        if ($globaltrays === false) {
            $globaltrays = ['starred', 'sent', 'drafts', 'trash'];
        } else if ($globaltrays == '') {
            $globaltrays = [];
        } else {
            $globaltrays = explode(',', $globaltrays);
        }

        return [
            'globaltrays' => $globaltrays,
            'coursetrays' => get_config('local_mail', 'coursetrays') ?: 'all',
            'coursetraysname' => get_config('local_mail', 'coursetraysname') ?: 'fullname',
            'coursebadges' => get_config('local_mail', 'coursebadges') ?: 'fullname',
            'coursebadgeslength' => (int) get_config('local_mail', 'coursebadgeslength') ?: 20,
            'filterbycourse' => get_config('local_mail', 'filterbycourse') ?: 'fullname',
            'incrementalsearch' => (bool) get_config('local_mail', 'incrementalsearch'),
            'incrementalsearchlimit' => (int) get_config('local_mail', 'incrementalsearchlimit') ?: 1000,
        ];
    }

    public static function get_settings_returns() {
        return new \external_single_structure([
            'globaltrays' => new \external_multiple_structure(
                new \external_value(PARAM_ALPHA, 'Type of tray: "starred", "sent", "drafts" or "trash"'),
                'Global trays to display'
            ),
            'coursetrays' => new \external_value(
                PARAM_ALPHA,
                'Course trays to display: "none", "unread", or "all"'
            ),
            'coursetraysname' => new \external_value(
                PARAM_ALPHA,
                'Name of course trays to display: "shortname" or "fullname"'
            ),
            'coursebadges' => new \external_value(
                PARAM_ALPHA,
                'Type of course badges: "hidden", "shortname", or "fullname"'
            ),
            'coursebadgeslength' => new \external_value(
                PARAM_INT,
                'Trunate course badges to this length in characters.'
            ),
            'filterbycourse' => new \external_value(
                PARAM_ALPHA,
                'Type of filter by course: "hidden", "shortname", or "fullname"'
            ),
            'incrementalsearch' => new \external_value(
                PARAM_BOOL,
                'Enables displaying results while the user is typing in the search box',
            ),
            'incrementalsearchlimit' => new \external_value(
                PARAM_INT,
                'Maximum number of recent messages included in incremental search',
            ),
        ]);
    }

    public static function get_strings_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_strings() {
        self::validate_context(\context_system::instance());

        return self::get_strings_raw();
    }

    public static function get_strings_raw() {
        global $CFG;

        $lang ??= current_language();

        // Ignore language packages from AMOS for Catalan and Spanish.
        if ($lang == 'ca' || $lang == 'es') {
            $string = [];

            // First load english pack.
            include("$CFG->dirroot/local/mail/lang/en/local_mail.php");

            // And then corresponding local english if present.
            if (file_exists("$CFG->langlocalroot/en_local/local_mail.php")) {
                include("$CFG->langlocalroot/en_local/local_mail.php");
            }

            // Legacy location - used by contrib only.
            include("$CFG->dirroot/local/mail/lang/$lang/local_mail.php");

            // Local customisations.
            if (file_exists("$CFG->langlocalroot/{$lang}_local/local_mail.php")) {
                include("$CFG->langlocalroot/{$lang}_local/local_mail/file.php");
            }

            return $string;
        }

        return get_string_manager()->load_component_strings('local_mail', $lang);
    }

    public static function get_strings_returns() {
        $stringkeys = [];
        foreach (array_keys(get_string_manager()->load_component_strings('local_mail', 'en')) as $id) {
            $stringkeys[$id] = new \external_value(PARAM_RAW, 'Localized content of language string "' . $id . '"');
        }
        return new \external_single_structure($stringkeys);
    }

    public static function get_preferences_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_preferences() {
        self::validate_context(\context_system::instance());

        return self::get_preferences_raw();
    }

    public static function get_preferences_raw() {
        return [
            'perpage' => max(5, min(100, (int) get_user_preferences('local_mail_mailsperpage', 10))),
            'markasread' => (bool) get_user_preferences('local_mail_markasread', 0),
        ];
    }

    public static function get_preferencs_returns() {
        return new \external_single_structure([
            'perpage' => new \external_value(PARAM_INT, 'Number of messages to display per page (5-100)'),
            'markasread' => new \external_value(PARAM_BOOL, 'Mark new messages as read if a notification is sent'),
        ]);
    }

    public static function set_preferences_parameters() {
        return new \external_function_parameters([
            'preferences' => new \external_single_structure([
                'perpage' => new \external_value(
                    PARAM_INT,
                    'Number of messages to display per page (5-100)',
                    VALUE_OPTIONAL
                ),
                'markasread' => new \external_value(
                    PARAM_BOOL,
                    'Mark new messages as read if a notification is sent',
                    VALUE_OPTIONAL
                ),
            ]),
        ]);
    }

    public static function set_preferences($preferences) {
        $params = ['preferences' => $preferences];
        $params = self::validate_parameters(self::set_preferences_parameters(), $params);

        if (isset($params['preferences']['perpage'])) {
            if ($params['preferences']['perpage'] < 5 || $params['preferences']['perpage'] > 100) {
                throw new \invalid_parameter_exception('"perpage" must be between 5 and 100');
            }
        }

        self::validate_context(\context_system::instance());

        self::set_preferences_raw($params['preferences']);

        return null;
    }

    public static function set_preferences_raw($preferences) {
        if (isset($preferences['perpage'])) {
            set_user_preference('local_mail_mailsperpage', $preferences['perpage']);
        }

        if (isset($preferences['markasread'])) {
            set_user_preference('local_mail_markasread', $preferences['markasread']);
        }
    }

    public static function set_preferences_returns() {
        return null;
    }

    public static function get_courses_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_courses() {
        self::validate_context(\context_system::instance());

        return self::get_courses_raw();
    }

    public static function get_courses_raw() {
        $user = user::current();
        $courses = $user->get_courses();

        if (!$courses) {
            return [];
        }

        $search = new search($user);
        $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
        $search->unread = true;
        $unread = $search->count_per_course();

        $result = [];
        foreach ($courses as $course) {
            $result[] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'visible' => $course->visible,
                'unread' => $unread[$course->id] ?? 0,
            ];
        }

        return $result;
    }

    public static function get_courses_returns() {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id of the course'),
                'shortname' => new \external_value(PARAM_TEXT, 'Short name of the course'),
                'fullname' => new \external_value(PARAM_TEXT, 'Full name of the course'),
                'unread' => new \external_value(PARAM_INT, 'Number of unread messages'),
                'visible' => new \external_value(PARAM_BOOL, 'Course visibility'),
            ])
        );
    }

    public static function get_labels_parameters() {
        return new \external_function_parameters([]);
    }

    public static function get_labels() {
        self::validate_context(\context_system::instance());

        return self::get_labels_raw();
    }

    public static function get_labels_raw() {
        $result = [];

        $user = user::current();
        $courses = $user->get_courses();

        $search = new search($user);
        $search->roles = [message::ROLE_TO, message::ROLE_CC, message::ROLE_BCC];
        $search->unread = true;
        $unread = $search->count_per_label();

        foreach (label::fetch_by_user($user) as $label) {
            $result[] = [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
                'unread' => $unread[$label->id] ?? 0,
            ];
        }

        return $result;
    }

    public static function get_labels_returns() {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id of the label'),
                'name' => new \external_value(PARAM_TEXT, 'Nane of the label'),
                'color' => new \external_value(PARAM_ALPHA, 'Color of the label'),
                'unread' => new \external_value(PARAM_INT, 'Number of unread messages'),
            ])
        );
    }

    private static function query_parameters() {
        return new \external_single_structure([
            'courseid' => new \external_value(
                PARAM_INT,
                'Search messages in this course',
                VALUE_DEFAULT,
                0
            ),
            'labelid' => new \external_value(
                PARAM_INT,
                'Search messages with this label',
                VALUE_DEFAULT,
                0
            ),
            'draft' => new \external_value(
                PARAM_BOOL,
                'Search messages with this draft status',
                VALUE_OPTIONAL
            ),
            'roles' => new \external_multiple_structure(
                new \external_value(PARAM_ALPHA, 'Role: "from", "to", "cc" or "bcc"'),
                'Search messages in which the user has one of these roles',
                VALUE_DEFAULT,
                []
            ),
            'unread' => new \external_value(
                PARAM_BOOL,
                'Search messages with this unread status',
                VALUE_OPTIONAL
            ),
            'starred' => new \external_value(
                PARAM_BOOL,
                'Search messages with this starred status',
                VALUE_OPTIONAL
            ),
            'deleted' => new \external_value(
                PARAM_BOOL,
                'Search deleted messages.',
                VALUE_DEFAULT,
                false
            ),
            'content' => new \external_value(
                PARAM_TEXT,
                'Search messages with this text in ',
                VALUE_DEFAULT,
                ''
            ),
            'sendername' => new \external_value(
                PARAM_TEXT,
                'Text to search the name of the sender',
                VALUE_DEFAULT,
                ''
            ),
            'recipientname' => new \external_value(
                PARAM_TEXT,
                'Text to search the names of the recipients',
                VALUE_DEFAULT,
                ''
            ),
            'withfilesonly' => new \external_value(
                PARAM_BOOL,
                'Search only messages with attachments',
                VALUE_DEFAULT,
                false
            ),
            'maxtime' => new \external_value(
                PARAM_INT,
                'Searh only messages older than this timestamp',
                VALUE_DEFAULT,
                0
            ),
            'startid' => new \external_value(
                PARAM_INT,
                'Start searching from the position of this message (excluded).',
                VALUE_DEFAULT,
                0
            ),
            'stopid' => new \external_value(
                PARAM_INT,
                'Stop serching at the position of this message (excluded).',
                VALUE_DEFAULT,
                0
            ),
            'reverse' => new \external_value(
                PARAM_BOOL,
                'Search messages from older to newer instead of from newer to older.',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    private static function validate_query_parameter($query): search {
        $user = user::current();

        $search = new search($user);

        if ($query['courseid']) {
            $search->course = course::fetch($query['courseid']);
            if (!$search->course || !$user->can_use_mail($search->course)) {
                throw new exception('errorcoursenotfound', 'local_mail');
            }
        }

        if ($query['labelid']) {
            $search->label = label::fetch($query['labelid']);
            if (!$search->label || $search->label->user->id != $user->id) {
                throw new exception('errorlabelnotfound', 'local_mail');
            }
        }

        if (isset($query['draft'])) {
            $search->draft = $query['draft'];
        }

        foreach ($query['roles'] as $rolename) {
            $role = array_search($rolename, self::ROLES);
            if ($role === false) {
                throw new \invalid_parameter_exception('invalid role: ' . $rolename);
            }
            $search->roles[] = $role;
        }

        if (isset($query['unread'])) {
            $search->unread = $query['unread'];
        }

        if (isset($query['starred'])) {
            $search->starred = $query['starred'];
        }

        $search->deleted = $query['deleted'];
        $search->content = $query['content'];
        $search->sendername = $query['sendername'];
        $search->recipientname = $query['recipientname'];
        $search->withfilesonly = $query['withfilesonly'];
        $search->maxtime = $query['maxtime'];

        if ($query['startid']) {
            $search->start = message::fetch($query['startid']);
            if (!$search->start) {
                throw new \invalid_parameter_exception('invalid startid: ' . $query['startid']);
            }
        }

        if ($query['stopid']) {
            $search->stop = message::fetch($query['stopid']);
            if (!$search->stop) {
                throw new \invalid_parameter_exception('invalid stopid: ' . $query['stopid']);
            }
        }

        $search->reverse = $query['reverse'];

        return $search;
    }

    public static function count_messages_parameters() {
        return new \external_function_parameters([
            'query' => self::query_parameters(),
        ]);
    }

    public static function count_messages($query) {
        $params = ['query' => $query];
        $params = self::validate_parameters(self::count_messages_parameters(), $params);

        $search = self::validate_query_parameter($params['query']);

        self::validate_context(\context_system::instance());

        return $search->count();
    }

    public static function count_messages_returns() {
        return new \external_value(PARAM_INT, 'Number of messages');
    }

    public static function search_messages_parameters() {
        return new \external_function_parameters([
            'query' => self::query_parameters(),
            'offset' => new \external_value(
                PARAM_INT,
                'Skip this number of messages',
                VALUE_DEFAULT,
                0
            ),
            'limit' => new \external_value(
                PARAM_INT,
                'Maximum number of messages',
                VALUE_DEFAULT,
                0
            ),
        ]);
    }

    public static function search_messages($query, $offset = 0, $limit = 0) {
        $params = ['query' => $query, 'offset' => $offset, 'limit' => $limit];
        $params = self::validate_parameters(self::search_messages_parameters(), $params);

        $search = self::validate_query_parameter($params['query']);

        self::validate_context(\context_system::instance());

        $messages = $search->fetch($params['offset'], $params['limit']);

        return self::search_messages_response($search->user->id, $messages);
    }

    public static function search_messages_response(int $userid, array $messages) {
        $result = [];

        foreach ($messages as $message) {
            $sender = $message->sender();
            $recipients = [];
            foreach ($message->recipients(message::ROLE_TO, message::ROLE_CC) as $user) {
                $recipients[] = [
                    'type' => self::ROLES[$message->roles[$user->id]],
                    'id' => $user->id,
                    'fullname' => $user->fullname(),
                    'pictureurl' => $user->picture_url(),
                    'profileurl' => $user->profile_url(),
                ];
            }
            $labels = [];
            foreach ($message->labels[$userid] as $label) {
                $labels[] = [
                    'id' => $label->id,
                    'name' => $label->name,
                    'color' => $label->color,
                ];
            }
            $result[] = [
                'id' => $message->id,
                'subject' => $message->subject,
                'numattachments' => $message->attachments,
                'draft' => $message->draft,
                'time' => $message->time,
                'shorttime' => self::format_time($message->time),
                'fulltime' => self::format_time($message->time, true),
                'unread' => $message->unread[$userid],
                'starred' => $message->starred[$userid],
                'deleted' => $message->deleted[$userid] != message::NOT_DELETED,
                'course' => [
                    'id' => $message->course->id,
                    'shortname' => $message->course->shortname,
                    'fullname' => $message->course->fullname,
                ],
                'sender' => [
                    'id' => $sender->id,
                    'fullname' => $sender->fullname(),
                    'pictureurl' => $sender->picture_url(),
                    'profileurl' => $sender->profile_url(),
                ],
                'recipients' => $recipients,
                'labels' => $labels,
            ];
        }

        return $result;
    }

    public static function search_messages_returns() {
        return new \external_multiple_structure(
            new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id of the message'),
                'subject' => new \external_value(PARAM_TEXT, 'Subject of the message'),
                'numattachments' => new \external_value(PARAM_INT, 'Number of attachments'),
                'draft' => new \external_value(PARAM_BOOL, 'Draft status'),
                'time' => new \external_value(PARAM_INT, 'Time of the message'),
                'shorttime' => new \external_value(PARAM_TEXT, 'Formatted short time'),
                'fulltime' => new \external_value(PARAM_TEXT, 'Formatted full time'),
                'unread' => new \external_value(PARAM_BOOL, 'Unread status'),
                'starred' => new \external_value(PARAM_BOOL, 'Starred status'),
                'deleted' => new \external_value(PARAM_BOOL, 'Deleted status'),
                'course' => new \external_single_structure([
                    'id' => new \external_value(PARAM_INT, 'Id of the course'),
                    'shortname' => new \external_value(PARAM_TEXT, 'Short name of the course'),
                    'fullname' => new \external_value(PARAM_TEXT, 'Full name of the course'),
                ]),
                'sender' => new \external_single_structure([
                    'id' => new \external_value(PARAM_INT, 'Id of the user'),
                    'fullname' => new \external_value(PARAM_RAW, 'Full name of the user'),
                    'pictureurl' => new \external_value(PARAM_URL, 'User image URL'),
                    'profileurl' => new \external_value(PARAM_URL, 'User profile URL'),
                ]),
                'recipients' => new \external_multiple_structure(
                    new \external_single_structure([
                        'type' => new \external_value(PARAM_ALPHA, 'Role of the user: "to", "cc" or "bcc"'),
                        'id' => new \external_value(PARAM_INT, 'Id of the user'),
                        'fullname' => new \external_value(PARAM_RAW, 'Full name of the user'),
                        'pictureurl' => new \external_value(PARAM_URL, 'User image URL'),
                        'profileurl' => new \external_value(PARAM_URL, 'User profile URL'),
                    ])
                ),
                'labels' => new \external_multiple_structure(
                    new \external_single_structure([
                        'id' => new \external_value(PARAM_INT, 'Id of the label'),
                        'name' => new \external_value(PARAM_TEXT, 'Name of the label'),
                        'color' => new \external_value(PARAM_ALPHA, 'Color of the label'),
                    ])
                ),
            ])
        );
    }

    public static function get_message_parameters() {
        return new \external_function_parameters([
            'messageid' => new \external_value(PARAM_INT, 'ID of the message'),
        ]);
    }

    public static function get_message($messageid) {
        $params = ['messageid' => $messageid];
        $params = self::validate_parameters(self::get_message_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();

        $message = message::fetch($params['messageid']);

        if (!$message || !$user->can_view_message($message)) {
            throw new exception('errormessagenotfound');
        }

        return self::get_message_response($user->id, $message);
    }

    public static function get_message_response(int $userid, message $message) {
        $contextid = $message->course->context()->id;

        list($content, $format) = \external_format_text(
            $message->content,
            $message->format,
            $contextid,
            'local_mail',
            'message',
            $message->id
        );

        $sender = $message->sender();

        $result = [
            'id' => $message->id,
            'subject' => $message->subject,
            'content' => $content,
            'format' => $format,
            'numattachments' => $message->attachments,
            'draft' => $message->draft,
            'time' => $message->time,
            'shorttime' => self::format_time($message->time),
            'fulltime' => self::format_time($message->time, true),
            'unread' => $message->unread[$userid],
            'starred' => $message->starred[$userid],
            'deleted' => (bool) $message->deleted[$userid],
            'course' => [
                'id' => $message->course->id,
                'shortname' => $message->course->shortname,
                'fullname' => $message->course->fullname,
            ],
            'sender' => [
                'id' => $sender->id,
                'fullname' => $sender->fullname(),
                'pictureurl' => $sender->picture_url(),
                'profileurl' => $sender->profile_url(),
            ],
            'recipients' => [],
            'attachments' => [],
            'references' => [],
            'labels' => [],
        ];

        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, 'local_mail', 'message', $message->id, 'filename', false);
        foreach ($files as $file) {
            $result['attachments'][] = [
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'fileurl' => self::file_url($file),
                'iconurl' => self::file_icon_url($file),
            ];
        }

        foreach ($message->recipients() as $user) {
            $role = $message->roles[$user->id];
            if ($role == message::ROLE_BCC && $userid != $user->id && $userid != $sender->id) {
                continue;
            }
            $result['recipients'][] = [
                'type' => self::ROLES[$role],
                'id' => $user->id,
                'fullname' => $user->fullname(),
                'pictureurl' => $user->picture_url(),
                'profileurl' => $user->profile_url(),
            ];
        }

        foreach ($message->fetch_references() as $ref) {
            list($content, $format) = \external_format_text(
                $ref->content,
                $ref->format,
                $contextid,
                'local_mail',
                'message',
                $ref->id
            );

            $attachments = [];
            $files = $fs->get_area_files($contextid, 'local_mail', 'message', $ref->id, 'filename', false);

            foreach ($files as $file) {
                $attachments[] = [
                    'filepath' => $file->get_filepath(),
                    'filename' => $file->get_filename(),
                    'filesize' => (int) $file->get_filesize(),
                    'mimetype' => $file->get_mimetype(),
                    'fileurl' => self::file_url($file),
                    'iconurl' => self::file_icon_url($file),
                ];
            }

            $refsender = $ref->sender();

            $result['references'][] = [
                'id' => $ref->id,
                'subject' => $ref->subject,
                'content' => $content,
                'format' => $format,
                'time' => $ref->time,
                'shorttime' => self::format_time($ref->time),
                'fulltime' => self::format_time($ref->time, true),
                'sender' => [
                    'id' => $refsender->id,
                    'fullname' => $refsender->fullname(),
                    'pictureurl' => $refsender->picture_url(),
                    'profileurl' => $refsender->profile_url(),
                ],
                'attachments' => $attachments,
            ];
        }

        foreach ($message->labels[$userid] as $label) {
            $result['labels'][] = [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
            ];
        }

        return $result;
    }


    public static function get_message_returns() {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Id of the message'),
            'subject' => new \external_value(PARAM_TEXT, 'Subject of the message'),
            'content' => new \external_value(PARAM_RAW, 'Content of the message'),
            'format' => new \external_format_value('Format of the message content'),
            'numattachments' => new \external_value(PARAM_INT, 'Number of attachments'),
            'draft' => new \external_value(PARAM_BOOL, 'Draft status'),
            'time' => new \external_value(PARAM_INT, 'Time of the message'),
            'shorttime' => new \external_value(PARAM_TEXT, 'Formatted short time'),
            'fulltime' => new \external_value(PARAM_TEXT, 'Formatted full time'),
            'unread' => new \external_value(PARAM_BOOL, 'Unread status'),
            'starred' => new \external_value(PARAM_BOOL, 'Starred status'),
            'deleted' => new \external_value(PARAM_BOOL, 'Deleted status'),
            'course' => new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id of the course'),
                'shortname' => new \external_value(PARAM_TEXT, 'Short name of the course'),
                'fullname' => new \external_value(PARAM_TEXT, 'Full name of the course'),
            ]),
            'sender' => new \external_single_structure([
                'id' => new \external_value(PARAM_INT, 'Id of the user'),
                'fullname' => new \external_value(PARAM_RAW, 'Full name of the user'),
                'pictureurl' => new \external_value(PARAM_URL, 'User image URL'),
                'profileurl' => new \external_value(PARAM_URL, 'User profile URL'),
            ]),
            'recipients' => new \external_multiple_structure(
                new \external_single_structure([
                    'type' => new \external_value(PARAM_ALPHA, 'Role of the user: "to", "cc" or "bcc"'),
                    'id' => new \external_value(PARAM_INT, 'Id of the user'),
                    'fullname' => new \external_value(PARAM_RAW, 'Full name of the user'),
                    'pictureurl' => new \external_value(PARAM_URL, 'User image URL'),
                    'profileurl' => new \external_value(PARAM_URL, 'User profile URL'),

                ])
            ),
            'attachments' => new \external_multiple_structure(
                new \external_single_structure([
                    'filepath' => new \external_value(PARAM_PATH, 'File directory'),
                    'filename' => new \external_value(PARAM_FILE, 'File name'),
                    'mimetype' => new \external_value(PARAM_RAW, 'Mime type'),
                    'filesize' => new \external_value(PARAM_INT, 'File size'),
                    'fileurl'  => new \external_value(PARAM_URL, 'Download URL'),
                    'iconurl'  => new \external_value(PARAM_URL, 'Icon URL'),
                ])
            ),
            'references' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new \external_value(PARAM_INT, 'Id of the message'),
                    'subject' => new \external_value(PARAM_TEXT, 'Subject of the message'),
                    'content' => new \external_value(PARAM_RAW, 'Content of the message'),
                    'format' => new \external_format_value('Format of the message content'),
                    'time' => new \external_value(PARAM_INT, 'Time of the message'),
                    'shorttime' => new \external_value(PARAM_TEXT, 'Formatted short time'),
                    'fulltime' => new \external_value(PARAM_TEXT, 'Formatted full time'),
                    'sender' => new \external_single_structure([
                        'id' => new \external_value(PARAM_INT, 'Id of the user'),
                        'fullname' => new \external_value(PARAM_RAW, 'Full name of the user'),
                        'pictureurl' => new \external_value(PARAM_URL, 'User image URL'),
                        'profileurl' => new \external_value(PARAM_URL, 'User profile URL'),
                    ]),
                    'attachments' => new \external_multiple_structure(
                        new \external_single_structure([
                            'filepath' => new \external_value(PARAM_PATH, 'File directory'),
                            'filename' => new \external_value(PARAM_FILE, 'File name'),
                            'mimetype' => new \external_value(PARAM_RAW, 'Mime type'),
                            'filesize' => new \external_value(PARAM_INT, 'File size'),
                            'fileurl'  => new \external_value(PARAM_URL, 'Download URL'),
                            'iconurl'  => new \external_value(PARAM_URL, 'Icon URL'),
                        ])
                    ),
                ])
            ),
            'labels' => new \external_multiple_structure(
                new \external_single_structure([
                    'id' => new \external_value(PARAM_INT, 'Id of the label'),
                    'name' => new \external_value(PARAM_TEXT, 'Name of the label'),
                    'color' => new \external_value(PARAM_ALPHA, 'Color of the label'),
                ])
            ),
        ]);
    }


    public static function set_unread_parameters() {
        return new \external_function_parameters([
            'messageid' => new \external_value(PARAM_INT, 'ID of the message'),
            'unread' => new \external_value(PARAM_BOOL, 'New unread status'),
        ]);
    }

    public static function set_unread($messageid, $unread) {
        $params = ['messageid' => $messageid, 'unread' => $unread];
        $params = self::validate_parameters(self::set_unread_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();
        $message = message::fetch($params['messageid']);

        if (!$message || !$user->can_view_message($message)) {
            throw new exception('errormessagenotfound');
        }

        $message->set_unread($user, $params['unread']);

        return null;
    }

    public static function set_unread_returns() {
        return null;
    }

    public static function set_starred_parameters() {
        return new \external_function_parameters([
            'messageid' => new \external_value(PARAM_INT, 'ID of the message'),
            'starred' => new \external_value(PARAM_BOOL, 'New starred status'),
        ]);
    }

    public static function set_starred($messageid, $starred) {
        $params = ['messageid' => $messageid, 'starred' => $starred];
        $params = self::validate_parameters(self::set_starred_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();
        $message = message::fetch($params['messageid']);

        if (!$message || !$user->can_view_message($message)) {
            throw new exception('errormessagenotfound');
        }

        $message->set_starred($user, $params['starred']);

        return null;
    }

    public static function set_starred_returns() {
        return null;
    }

    public static function set_deleted_parameters() {
        return new \external_function_parameters([
            'messageid' => new \external_value(PARAM_INT, 'ID of the message'),
            'deleted' => new \external_value(
                PARAM_INT,
                'New deleted status: 0 (not deleted), 1 (deleted), 2 (deleted forever)'
            ),
        ]);
    }

    public static function set_deleted($messageid, $deleted) {
        $params = ['messageid' => $messageid, 'deleted' => $deleted];
        $params = self::validate_parameters(self::set_deleted_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();
        $message = message::fetch($params['messageid']);

        if (!$message || !$user->can_view_message($message)) {
            throw new exception('errormessagenotfound');
        }

        $message->set_deleted($user, $params['deleted']);

        return null;
    }

    public static function set_deleted_returns() {
        return null;
    }

    public static function empty_trash_parameters() {
        return new \external_function_parameters([]);
    }

    public static function empty_trash() {
        self::validate_context(\context_system::instance());

        $user = user::current();

        message::empty_trash($user, $user->get_courses());

        return null;
    }

    public static function empty_trash_returns() {
        return null;
    }

    public static function create_label_parameters() {
        $colors = implode(', ', label::COLORS);
        return new \external_function_parameters([
            'name' => new \external_value(PARAM_TEXT, 'Name of the label'),
            'color' => new \external_value(PARAM_ALPHA, "Color of the label. Valid values: $colors", VALUE_DEFAULT, ''),
        ]);
    }

    public static function create_label($name, $color = '') {
        $params = ['name' => $name, 'color' => $color];
        $params = self::validate_parameters(self::create_label_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();

        $normalizedname = label::nromalized_name($params['name']);
        if (strlen($normalizedname) == 0) {
            throw new exception('erroremptylabelname');
        }

        foreach (label::fetch_by_user($user) as $label) {
            if ($label->name == $normalizedname) {
                throw new exception('errorrepeatedlabelname');
            }
        }

        if ($params['color'] && !in_array($params['color'], label::COLORS)) {
            throw new exception('errorinvalidcolor');
        }

        $label = label::create($user, $normalizedname, $params['color']);

        return $label->id;
    }

    public static function create_label_returns() {
        return new \external_value(PARAM_INT, 'ID of the label');
    }

    public static function update_label_parameters() {
        $colors = implode(', ', label::COLORS);
        return new \external_function_parameters([
            'labelid' => new \external_value(PARAM_INT, 'ID of the label'),
            'name' => new \external_value(PARAM_TEXT, 'Name of the label'),
            'color' => new \external_value(PARAM_ALPHA, "Color of the label: $colors", VALUE_DEFAULT, ''),
        ]);
    }

    public static function update_label($labelid, $name, $color = '') {
        $params = ['labelid' => $labelid, 'name' => $name, 'color' => $color];
        $params = self::validate_parameters(self::update_label_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();

        $label = label::fetch($params['labelid']);
        if (!$label || $label->user->id != $user->id) {
            throw new exception('errorlabelnotfound');
        }

        $normalizedname = label::nromalized_name($params['name']);
        if (strlen($normalizedname) == 0) {
            throw new exception('erroremptylabelname');
        }

        foreach ($label::fetch_by_user($user) as $userlabel) {
            if ($userlabel->id != $params['labelid'] && $userlabel->name == $normalizedname) {
                throw new exception('errorrepeatedlabelname');
            }
        }

        if ($params['color'] && !in_array($params['color'], label::COLORS)) {
            throw new exception('errorinvalidcolor');
        }

        $label->update($normalizedname, $params['color']);

        return null;
    }

    public static function update_label_returns() {
        return null;
    }

    public static function delete_label_parameters() {
        return new \external_function_parameters([
            'labelid' => new \external_value(PARAM_INT, 'ID of the label'),
        ]);
    }

    public static function delete_label($labelid) {
        $params = ['labelid' => $labelid];
        $params = self::validate_parameters(self::delete_label_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();

        $label = label::fetch($params['labelid']);
        if (!$label || $label->user->id != $user->id) {
            throw new exception('errorlabelnotfound');
        }

        $label->delete();

        return null;
    }

    public static function delete_label_returns() {
        return null;
    }

    public static function set_labels_parameters() {
        return new \external_function_parameters([
            'messageid' => new \external_value(PARAM_INT, 'ID of the message'),
            'labelids' => new \external_multiple_structure(
                new \external_value(
                    PARAM_INT,
                    'ID of a label'
                ),
            )
        ]);
    }

    public static function set_labels($messageid, $labelids) {
        $params = ['messageid' => $messageid, 'labelids' => $labelids];
        $params = self::validate_parameters(self::set_labels_parameters(), $params);

        self::validate_context(\context_system::instance());

        $user = user::current();
        $message = message::fetch($params['messageid']);

        if (!$message || !$user->can_view_message($message)) {
            throw new exception('errormessagenotfound');
        }

        $labels = label::fetch_many($params['labelids']);
        foreach ($params['labelids'] as $id) {
            if (!isset($labels[$id]) || $labels[$id]->user->id != $user->id) {
                throw new exception('errorlabelnotfound');
            }
        }

        $message->set_labels($user, $labels);

        return null;
    }

    public static function set_labels_returns() {
        return null;
    }

    public static function format_time(int $timestamp, $forcefull = false): string {
        $tz = \core_date::get_user_timezone();
        $date = new \DateTime('now', new \DateTimeZone($tz));
        $offset = ($date->getOffset() - dst_offset_on(time(), $tz)) / (3600.0);
        $time = ($offset < 13) ? $timestamp + $offset : $timestamp;
        $now = ($offset < 13) ? time() + $offset : time();
        $daysago = floor($now / 86400) - floor($time / 86400);
        $yearsago = (int) date('Y', $now) - (int) date('Y', $time);

        if ($forcefull) {
            return  userdate($time, get_string('strftimedatetime', 'langconfig'));
        } else if ($daysago == 0) {
            return userdate($time, get_string('strftimetime'));
        } else if ($yearsago == 0) {
            return userdate($time, get_string('strftimedateshortmonthabbr', 'langconfig'));
        } else {
            return userdate($time, get_string('strftimedatefullshort', 'langconfig'));
        }
    }

    private static function file_icon_url(\stored_file $file) {
        global $OUTPUT;
        return $OUTPUT->image_url(file_file_icon($file, 24))->out(false);
    }

    private static function file_url(\stored_file $file) {
        $fileurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
        return $fileurl->out(false);
    }
}
