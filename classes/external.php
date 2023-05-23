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

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/local/mail/locallib.php");

class local_mail_external extends external_api {

    public static function get_info_parameters() {
        return new external_function_parameters([]);
    }

    public static function get_info_returns() {
        foreach (self::string_identifiers() as $id) {
            $stringkeys[$id] = new external_value(PARAM_RAW, 'Localized content of language string"' . $id . '"');
        }
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'User id'),
            'preferences' => new external_single_structure([
                'perpage' => new external_value(PARAM_INT, 'Number of messages to display per page.'),
                'markasread' => new external_value(PARAM_BOOL, 'Mark new messages as read if a notification is sent.'),
            ]),
            'strings' => new external_single_structure($stringkeys),
        ]);
    }

    public static function get_info() {
        global $USER;

        self::validate_context(context_system::instance());

        foreach (self::string_identifiers() as $id) {
            $strings[$id] = get_string($id, 'local_mail');
        }

        return [
            'userid' => $USER->id,
            'preferences' => [
                'perpage' => get_user_preferences('local_mail_mailsperpage', 10),
                'markasread' => (bool) get_user_preferences('local_mail_markasread', 1),
            ],
            'strings' => $strings,
        ];
    }

    public static function get_unread_count_parameters() {
        return new external_function_parameters([]);
    }

    public static function get_unread_count_returns() {
        return new external_value(PARAM_INT, 'Number of unread messages');
    }

    public static function get_unread_count() {
        global $USER;

        self::validate_context(context_system::instance());

        $count = local_mail_message::count_menu($USER->id);

        return isset($count->inbox) ? $count->inbox : 0;
    }

    public static function get_menu_parameters() {
        return new external_function_parameters([]);
    }

    public static function get_menu_returns() {
        return new external_single_structure([
            'unread' => new external_value(PARAM_INT, 'Number of unread messages'),
            'drafts' => new external_value(PARAM_INT, 'Number of saved drafts'),
            'courses' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the course'),
                    'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                    'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
                    'unread' => new external_value(PARAM_INT, 'Number of unread messages'),
                    'visible' => new external_value(PARAM_BOOL, 'Course visibility'),
                ])
            ),
            'labels' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the label'),
                    'name' => new external_value(PARAM_TEXT, 'Nane of the label'),
                    'color' => new external_value(PARAM_ALPHA, 'Color of the label'),
                    'unread' => new external_value(PARAM_INT, 'Number of unread messages'),
                ])
            ),
        ]);
    }

    public static function get_menu() {
        global $USER;

        self::validate_context(context_system::instance());

        $count = local_mail_message::count_menu($USER->id);
        $result = [
            'unread' => isset($count->inbox) ? $count->inbox : 0,
            'drafts' => isset($count->drafts) ? $count->drafts : 0,
            'courses' => [],
            'labels' => [],
        ];

        foreach (local_mail_get_my_courses() as $course) {
            $result['courses'][] = [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
                'unread' => isset($count->courses[$course->id]) ? $count->courses[$course->id] : 0,
                'visible' => !empty($course->visible),
            ];
        }

        foreach (local_mail_label::fetch_user($USER->id) as $label) {
            $id = $label->id();
            $result['labels'][] = [
                'id' => $id,
                'name' => $label->name(),
                'color' => $label->color(),
                'unread' => isset($count->labels[$id]) ? $count->labels[$id] : 0,
            ];
        }

        return $result;
    }

    public static function get_index_parameters() {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, 'Type of index: inbox, starred, drafts, sent, trash, course or label'),
            'itemid' => new external_value(PARAM_INT, 'ID of the course or label'),
            'offset' => new external_value(PARAM_INT, 'Skip this number of messages'),
            'limit' => new external_value(PARAM_INT, 'Limit of messages to list'),
        ]);
    }

    public static function get_index_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'Total number of messages in the index'),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the message'),
                    'subject' => new external_value(PARAM_TEXT, 'Subject of the message'),
                    'attachments' => new external_value(PARAM_INT, 'Number of attachments'),
                    'draft' => new external_value(PARAM_BOOL, 'Draft status'),
                    'time' => new external_value(PARAM_INT, 'Time of the message'),
                    'shorttime' => new external_value(PARAM_TEXT, 'Formatted short time'),
                    'fulltime' => new external_value(PARAM_TEXT, 'Formatted full time'),
                    'unread' => new external_value(PARAM_BOOL, 'Unread status'),
                    'starred' => new external_value(PARAM_BOOL, 'Starred status'),
                    'deleted' => new external_value(PARAM_BOOL, 'Deleted status'),
                    'course' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Id of the course'),
                        'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                        'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
                    ]),
                    'sender' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Id of the user'),
                        'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                        'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                    ]),
                    'recipients' => new external_multiple_structure(
                        new external_single_structure([
                            'type' => new external_value(PARAM_ALPHA, 'Role of the user: "to", "cc" or "bcc"'),
                            'id' => new external_value(PARAM_INT, 'Id of the user'),
                            'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                            'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                        ])
                    ),
                    'labels' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Id of the label'),
                            'name' => new external_value(PARAM_TEXT, 'Name of the label'),
                            'color' => new external_value(PARAM_ALPHA, 'Color of the label'),
                        ])
                    ),
                ])
            ),
        ]);
    }

    public static function get_index($type, $itemid, $offset, $limit) {
        global $USER;

        $params = ['type' => $type, 'itemid' => $itemid, 'offset' => $offset, 'limit' => $limit];
        $params = self::validate_parameters(self::get_index_parameters(), $params);

        self::validate_context(context_system::instance());

        $courseid = ($params['type'] == 'course' ? $params['itemid'] : SITEID);
        $context = context_course::instance($courseid);

        if ($params['type'] == 'course') {
            require_capability('local/mail:usemail', $context);
        }

        $totalcount = local_mail_message::count_index($USER->id, $params['type'], $params['itemid']);
        $messages = local_mail_message::fetch_index($USER->id, $params['type'], $params['itemid'],
                                                    $params['offset'], $params['limit']);

        $result = [
            'totalcount' => $totalcount,
            'messages' => [],
        ];

        foreach ($messages as $message) {
            $sender = $message->sender();
            $userpicture = new user_picture($sender);
            $userpicture->size = 1;
            $sender = [
                'id' => $sender->id,
                'fullname' => fullname($sender),
                'pictureurl' => self::user_picture_url($sender),
            ];
            $recipients = [];
            foreach (['to', 'cc'] as $type) {
                foreach ($message->recipients($type) as $user) {
                    $userpicture = new user_picture($user);
                    $userpicture->size = 1;
                    $recipients[] = [
                        'type' => $type,
                        'id' => $user->id,
                        'fullname' => fullname($user),
                        'pictureurl' => self::user_picture_url($user),
                    ];
                }
            }
            $labels = [];
            foreach ($message->labels($USER->id) as $label) {
                $labels[] = [
                    'id' => $label->id(),
                    'name' => $label->name(),
                    'color' => $label->color(),
                ];
            }
            $course = $message->course();
            $result['messages'][] = [
                'id' => $message->id(),
                'subject' => $message->subject(),
                'attachments' => $message->attachments(true),
                'draft' => $message->draft(),
                'time' => $message->time(),
                'shorttime' => self::format_time($message->time()),
                'fulltime' => self::format_time($message->time(), true),
                'unread' => $message->unread($USER->id),
                'starred' => $message->starred($USER->id),
                'deleted' => (bool) $message->deleted($USER->id),
                'course' => [
                    'id' => $course->id,
                    'shortname' => $course->shortname,
                    'fullname' => $course->fullname,
                ],
                'sender' => $sender,
                'recipients' => $recipients,
                'labels' => $labels,
            ];
        }

        return $result;
    }

    public static function search_index_parameters() {
        return new external_function_parameters([
            'type' => new external_value(PARAM_ALPHA, 'Type of index: inbox, starred, drafts, sent, trash, course or label'),
            'itemid' => new external_value(PARAM_INT, 'ID of the course or label of the index'),
            'query' => new external_single_structure([
                'beforeid' => new external_value(
                    PARAM_INT, 'ID of the message where to start searching older messages', VALUE_OPTIONAL),
                'afterid' => new external_value(
                    PARAM_INT, 'ID of the message where to start searching newer messages', VALUE_OPTIONAL),
                'content' => new external_value(
                    PARAM_TEXT, 'Text to search then contents of the message', VALUE_OPTIONAL),
                'sender' => new external_value(
                    PARAM_TEXT, 'Text to search the name of the sender', VALUE_OPTIONAL),
                'recipients' => new external_value(
                    PARAM_TEXT, 'Text to search the names of the recipients', VALUE_OPTIONAL),
                'unread' => new external_value(
                    PARAM_BOOL, 'Search only unread messsages', VALUE_OPTIONAL),
                'attachments' => new external_value(
                    PARAM_BOOL, 'Search only messages with attachments', VALUE_OPTIONAL),
                'time' => new external_value(
                    PARAM_INT, 'Search only messages older than this timestamp', VALUE_OPTIONAL),
                'limit' => new external_value(
                    PARAM_INT, 'Maximum number of messages to return', VALUE_OPTIONAL),
            ]),
        ]);
    }

    public static function search_index_returns() {
        return new external_single_structure([
            'totalcount' => new external_value(PARAM_INT, 'Total number of messages in the index'),
            'messages' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the message'),
                    'subject' => new external_value(PARAM_TEXT, 'Subject of the message'),
                    'attachments' => new external_value(PARAM_INT, 'Number of attachments'),
                    'draft' => new external_value(PARAM_BOOL, 'Draft status'),
                    'time' => new external_value(PARAM_INT, 'Time of the message'),
                    'shorttime' => new external_value(PARAM_TEXT, 'Formatted short time'),
                    'fulltime' => new external_value(PARAM_TEXT, 'Formatted full time'),
                    'unread' => new external_value(PARAM_BOOL, 'Unread status'),
                    'starred' => new external_value(PARAM_BOOL, 'Starred status'),
                    'deleted' => new external_value(PARAM_BOOL, 'Deleted status'),
                    'course' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Id of the course'),
                        'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                        'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
                    ]),
                    'sender' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Id of the user'),
                        'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                        'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                    ]),
                    'recipients' => new external_multiple_structure(
                        new external_single_structure([
                            'type' => new external_value(PARAM_ALPHA, 'Role of the user: "to", "cc" or "bcc"'),
                            'id' => new external_value(PARAM_INT, 'Id of the user'),
                            'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                            'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                        ])
                    ),
                    'labels' => new external_multiple_structure(
                        new external_single_structure([
                            'id' => new external_value(PARAM_INT, 'Id of the label'),
                            'name' => new external_value(PARAM_TEXT, 'Name of the label'),
                            'color' => new external_value(PARAM_ALPHA, 'Color of the label'),
                        ])
                    ),
                ])
            ),
        ]);
    }

    public static function search_index($type, $itemid, $query) {
        global $USER;

        $params = ['type' => $type, 'itemid' => $itemid, 'query' => $query];
        $params = self::validate_parameters(self::search_index_parameters(), $params);

        self::validate_context(context_system::instance());

        $courseid = ($params['type'] == 'course' ? $params['itemid'] : SITEID);
        $context = context_course::instance($courseid);

        if ($params['type'] == 'course') {
            require_capability('local/mail:usemail', $context);
        }

        $totalcount = local_mail_message::count_index($USER->id, $params['type'], (int) $params['itemid']);

        $query = [];
        if (!empty($params['query']['beforeid'])) {
            $query['before'] = (int) $params['query']['beforeid'];
        }
        if (!empty($params['query']['afterid']) && empty($params['query']['beforeid'])) {
            $query['after'] = (int) $params['query']['afterid'];
        }
        if (!empty($params['query']['content'])) {
            $query['pattern'] = $params['query']['content'];
        }
        if (!empty($params['query']['sender'])) {
            $query['searchfrom'] = $params['query']['sender'];
        }
        if (!empty($params['query']['recipients'])) {
            $query['searchto'] = $params['query']['recipients'];
        }
        if (!empty($params['query']['unread'])) {
            $query['unread'] = true;
        }
        if (!empty($params['query']['attachments'])) {
            $query['attach'] = true;
        }
        if (!empty($params['query']['time'])) {
            $query['time'] = $params['query']['time'];
        }
        if (!empty($params['query']['limit'])) {
            $query['limit'] = (int) $params['query']['limit'];
        }

        $messages = local_mail_message::search_index($USER->id, $params['type'], (int) $params['itemid'], $query);

        $result = [
            'totalcount' => $totalcount,
            'messages' => [],
        ];

        foreach ($messages as $message) {
            $sender = $message->sender();
            $userpicture = new user_picture($sender);
            $userpicture->size = 1;
            $sender = [
                'id' => $sender->id,
                'fullname' => fullname($sender),
                'pictureurl' => self::user_picture_url($sender),
            ];
            $recipients = [];
            foreach (['to', 'cc'] as $type) {
                foreach ($message->recipients($type) as $user) {
                    $userpicture = new user_picture($user);
                    $userpicture->size = 1;
                    $recipients[] = [
                        'type' => $type,
                        'id' => $user->id,
                        'fullname' => fullname($user),
                        'pictureurl' => self::user_picture_url($user),
                    ];
                }
            }
            $labels = [];
            foreach ($message->labels($USER->id) as $label) {
                $labels[] = [
                    'id' => $label->id(),
                    'name' => $label->name(),
                    'color' => $label->color(),
                ];
            }
            $course = $message->course();
            $result['messages'][] = [
                'id' => $message->id(),
                'subject' => $message->subject(),
                'attachments' => $message->attachments(true),
                'draft' => $message->draft(),
                'time' => $message->time(),
                'shorttime' => self::format_time($message->time()),
                'fulltime' => self::format_time($message->time(), true),
                'unread' => $message->unread($USER->id),
                'starred' => $message->starred($USER->id),
                'deleted' => (bool) $message->deleted($USER->id),
                'course' => [
                    'id' => $course->id,
                    'shortname' => $course->shortname,
                    'fullname' => $course->fullname,
                ],
                'sender' => $sender,
                'recipients' => $recipients,
                'labels' => $labels,
            ];
        }

        return $result;
    }

    public static function get_message_parameters() {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'ID of the message'),
        ]);
    }

    public static function get_message_returns() {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Id of the message'),
            'subject' => new external_value(PARAM_TEXT, 'Subject of the message'),
            'content' => new external_value(PARAM_RAW, 'Content of the message'),
            'format' => new external_format_value('Format of the message content'),
            'draft' => new external_value(PARAM_BOOL, 'Draft status'),
            'time' => new external_value(PARAM_INT, 'Time of the message'),
            'shorttime' => new external_value(PARAM_TEXT, 'Formatted short time'),
            'fulltime' => new external_value(PARAM_TEXT, 'Formatted full time'),
            'unread' => new external_value(PARAM_BOOL, 'Unread status'),
            'starred' => new external_value(PARAM_BOOL, 'Starred status'),
            'deleted' => new external_value(PARAM_BOOL, 'Deleted status'),
            'course' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of the course'),
                'shortname' => new external_value(PARAM_TEXT, 'Short name of the course'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name of the course'),
            ]),
            'sender' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of the user'),
                'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
            ]),
            'recipients' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'Role of the user: "to", "cc" or "bcc"'),
                    'id' => new external_value(PARAM_INT, 'Id of the user'),
                    'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                    'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                ])
            ),
            'attachments' => new external_multiple_structure(
                new external_single_structure([
                    'filename' => new external_value(PARAM_FILE, 'File name'),
                    'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
                    'filesize' => new external_value(PARAM_INT, 'File size'),
                    'fileurl'  => new external_value(PARAM_URL, 'Download URL'),
                ])
            ),
            'references' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the message'),
                    'subject' => new external_value(PARAM_TEXT, 'Subject of the message'),
                    'content' => new external_value(PARAM_RAW, 'Content of the message'),
                    'format' => new external_format_value('Format of the message content'),
                    'time' => new external_value(PARAM_INT, 'Time of the message'),
                    'shorttime' => new external_value(PARAM_TEXT, 'Formatted short time'),
                    'fulltime' => new external_value(PARAM_TEXT, 'Formatted full time'),
                    'sender' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Id of the user'),
                        'fullname' => new external_value(PARAM_RAW, 'Full name of the user'),
                        'pictureurl' => new external_value(PARAM_URL, 'User image profile URL'),
                    ]),
                    'attachments' => new external_multiple_structure(
                        new external_single_structure([
                            'filename' => new external_value(PARAM_FILE, 'File name'),
                            'mimetype' => new external_value(PARAM_RAW, 'Mime type'),
                            'filesize' => new external_value(PARAM_INT, 'File size'),
                            'fileurl'  => new external_value(PARAM_URL, 'Download URL'),
                        ])
                    ),
                ])
            ),
            'labels' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Id of the label'),
                    'name' => new external_value(PARAM_TEXT, 'Name of the label'),
                    'color' => new external_value(PARAM_ALPHA, 'Color of the label'),
                ])
            ),
        ]);
    }

    public static function get_message($id) {
        global $USER;

        $params = ['id' => $id];
        $params = self::validate_parameters(self::get_message_parameters(), $params);

        self::validate_context(context_system::instance());

        $message = local_mail_message::fetch($id);

        if (!$message || !$message->viewable($USER->id)) {
            throw new moodle_exception('invalidmessage', 'local_mail');
        }

        $course = $message->course();
        $context = context_course::instance($course->id);

        list($content, $format) = external_format_text($message->content(), $message->format(), $context->id,
                                                       'local_mail', 'message', $message->id());

        $result = [
            'id' => $message->id(),
            'subject' => $message->subject(),
            'content' => $content,
            'format' => $format,
            'draft' => $message->draft(),
            'time' => $message->time(),
            'shorttime' => self::format_time($message->time()),
            'fulltime' => self::format_time($message->time(), true),
            'unread' => $message->unread($USER->id),
            'starred' => $message->starred($USER->id),
            'deleted' => (bool) $message->deleted($USER->id),
            'course' => [
                'id' => $course->id,
                'shortname' => $course->shortname,
                'fullname' => $course->fullname,
            ],
            'sender' => [
                'id' => $message->sender()->id,
                'fullname' => fullname($message->sender()),
                'pictureurl' => self::user_picture_url($message->sender()),
            ],
            'recipients' => [],
            'attachments' => [],
            'references' => [],
            'labels' => [],
        ];

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_mail', 'message', $message->id(), 'filename', false);
        foreach ($files as $file) {
            $url = moodle_url::make_webservice_pluginfile_url($context->id, 'local_mail', 'message', $message->id(),
                                                              $file->get_filepath(), $file->get_filename());
            $result['attachments'][] = [
                'filename' => $file->get_filename(),
                'filesize' => (int) $file->get_filesize(),
                'mimetype' => $file->get_mimetype(),
                'fileurl' => $url->out(false),
            ];
        }

        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ($message->recipients($type) as $user) {
                if ($type == 'bcc' && $USER->id != $user->id && $USER->id != $message->sender()->id) {
                    continue;
                }
                $result['recipients'][] = [
                    'type' => $type,
                    'id' => $user->id,
                    'fullname' => fullname($user),
                    'pictureurl' => self::user_picture_url($user),
                ];
            }
        }

        foreach ($message->references() as $reference) {
            list($content, $format) = external_format_text($reference->content(), $reference->format(), $context->id,
                                                           'local_mail', 'message', $reference->id());

            $attachments = [];
            $files = $fs->get_area_files($context->id, 'local_mail', 'message', $reference->id(), 'filename', false);

            foreach ($files as $file) {
                $url = moodle_url::make_webservice_pluginfile_url($context->id, 'local_mail', 'message', $reference->id(),
                                                                  $file->get_filepath(), $file->get_filename());
                $attachments[] = [
                    'filename' => $file->get_filename(),
                    'filesize' => (int) $file->get_filesize(),
                    'mimetype' => $file->get_mimetype(),
                    'fileurl' => $url->out(false),
                ];
            }

            $result['references'][] = [
                'id' => $reference->id(),
                'subject' => $reference->subject(),
                'content' => $content,
                'format' => $format,
                'time' => $reference->time(),
                'shorttime' => self::format_time($reference->time()),
                'fulltime' => self::format_time($reference->time(), true),
                'sender' => [
                    'id' => $reference->sender()->id,
                    'fullname' => fullname($reference->sender()),
                    'pictureurl' => self::user_picture_url($reference->sender()),
                ],
                'attachments' => $attachments,
            ];
        }

        foreach ($message->labels($USER->id) as $label) {
            $result['labels'][] = [
                'id' => $label->id(),
                'name' => $label->name(),
                'color' => $label->color(),
            ];
        }

        return $result;
    }

    public static function set_unread_parameters() {
        return new external_function_parameters([
            'messageid' => new external_value(PARAM_INT, 'ID of the message'),
            'unread' => new external_value(PARAM_BOOL, 'New unread status'),
        ]);
    }

    public static function set_unread_returns() {
        return null;
    }

    public static function set_unread($messageid, $unread) {
        global $USER;

        $params = ['messageid' => $messageid, 'unread' => $unread];
        $params = self::validate_parameters(self::set_unread_parameters(), $params);

        self::validate_context(context_system::instance());

        $message = local_mail_message::fetch($params['messageid']);

        if (!$message || !$message->viewable($USER->id)) {
            throw new moodle_exception('invalidmessage', 'local_mail');
        }

        $message->set_unread($USER->id, $params['unread']);

        return null;
    }

    public static function set_starred_parameters() {
        return new external_function_parameters([
            'messageid' => new external_value(PARAM_INT, 'ID of the message'),
            'starred' => new external_value(PARAM_BOOL, 'New starred status'),
        ]);
    }

    public static function set_starred_returns() {
        return null;
    }

    public static function set_starred($messageid, $starred) {
        global $USER;

        $params = ['messageid' => $messageid, 'starred' => $starred];
        $params = self::validate_parameters(self::set_starred_parameters(), $params);

        self::validate_context(context_system::instance());

        $message = local_mail_message::fetch($params['messageid']);

        if (!$message || !$message->viewable($USER->id)) {
            throw new moodle_exception('invalidmessage', 'local_mail');
        }

        $message->set_starred($USER->id, $params['starred']);

        return null;
    }

    public static function set_deleted_parameters() {
        return new external_function_parameters([
            'messageid' => new external_value(PARAM_INT, 'ID of the message'),
            'deleted' => new external_value(PARAM_INT,
                'New deleted status: 0 (not deleted), 1 (deleted), 2 (deleted forever)'),
        ]);
    }

    public static function set_deleted_returns() {
        return null;
    }

    public static function set_deleted($messageid, $deleted) {
        global $USER;

        $params = ['messageid' => $messageid, 'deleted' => $deleted];
        $params = self::validate_parameters(self::set_deleted_parameters(), $params);

        self::validate_context(context_system::instance());

        $message = local_mail_message::fetch($params['messageid']);

        if (!$message || !$message->viewable($USER->id)) {
            throw new moodle_exception('invalidmessage', 'local_mail');
        }

        $message->set_deleted($USER->id, $params['deleted']);

        return null;
    }

    public static function empty_trash_parameters() {
        return new external_function_parameters([]);
    }

    public static function empty_trash_returns() {
        return null;
    }

    public static function empty_trash() {
        global $USER;

        self::validate_context(context_system::instance());

        \local_mail_message::empty_trash($USER->id);

        return null;
    }

    public static function create_label_parameters() {
        $colors = implode(', ',  \local_mail_label::valid_colors());
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Name of the label.'),
            'color' => new external_value(PARAM_ALPHA, "Color of the label. Valid values: $colors.", VALUE_DEFAULT, ''),
        ]);
    }

    public static function create_label_returns() {
        return new external_value(PARAM_INT, 'ID of the label');
    }

    public static function create_label($name, $color = '') {
        global $USER;

        $params = ['name' => $name, 'color' => $color];
        $params = self::validate_parameters(self::create_label_parameters(), $params);

        self::validate_context(context_system::instance());

        $normalizedname = \local_mail_label::nromalized_name($params['name']);
        if (strlen($normalizedname) == 0) {
            throw new moodle_exception('erroremptylabelname', 'local_mail');
        }

        $labels = \local_mail_label::fetch_user($USER->id);
        foreach ($labels as $label) {
            if ($label->name() == $normalizedname) {
                throw new moodle_exception('errorrepeatedlabelname', 'local_mail');
            }
        }

        if ($params['color'] && !in_array($params['color'], \local_mail_label::valid_colors())) {
            throw new moodle_exception('errorinvalidcolor', 'local_mail');
        }

        $label = \local_mail_label::create($USER->id, $normalizedname, $params['color']);

        return $label->id();
    }

    public static function update_label_parameters() {
        $colors = implode(', ',  \local_mail_label::valid_colors());
        return new external_function_parameters([
            'labelid' => new external_value(PARAM_INT, 'ID of the label'),
            'name' => new external_value(PARAM_TEXT, 'Name of the label.'),
            'color' => new external_value(PARAM_ALPHA, "Color of the label. Valid values: $colors.", VALUE_DEFAULT, ''),
        ]);
    }

    public static function update_label_returns() {
        return null;
    }

    public static function update_label($labelid, $name, $color = '') {
        global $USER;

        $params = ['labelid' => $labelid, 'name' => $name, 'color' => $color];
        $params = self::validate_parameters(self::update_label_parameters(), $params);

        self::validate_context(context_system::instance());

        $label = \local_mail_label::fetch($params['labelid']);
        if (!$label || $label->userid() != $USER->id) {
            throw new moodle_exception('invalidlabel', 'local_mail');
        }

        $normalizedname = \local_mail_label::nromalized_name($params['name']);
        if (strlen($normalizedname) == 0) {
            throw new moodle_exception('erroremptylabelname', 'local_mail');
        }

        $userlabels = \local_mail_label::fetch_user($USER->id);
        foreach ($userlabels as $userlabel) {
            if ($userlabel->id() != $params['labelid'] && $userlabel->name() == $normalizedname) {
                throw new moodle_exception('errorrepeatedlabelname', 'local_mail');
            }
        }

        if ($params['color'] && !in_array($params['color'], \local_mail_label::valid_colors())) {
            throw new moodle_exception('errorinvalidcolor', 'local_mail');
        }

        $label->save($normalizedname, $params['color']);

        return null;
    }

    public static function delete_label_parameters() {
        return new external_function_parameters([
            'labelid' => new external_value(PARAM_INT, 'ID of the label'),
        ]);
    }

    public static function delete_label_returns() {
        return null;
    }

    public static function delete_label($labelid) {
        global $USER;

        $params = ['labelid' => $labelid];
        $params = self::validate_parameters(self::delete_label_parameters(), $params);

        self::validate_context(context_system::instance());

        $label = \local_mail_label::fetch($params['labelid']);
        if (!$label || $label->userid() != $USER->id) {
            throw new moodle_exception('invalidlabel', 'local_mail');
        }

        $label->delete();

        return null;
    }

    public static function set_labels_parameters() {
        return new external_function_parameters([
            'messageid' => new external_value(PARAM_INT, 'ID of the message'),
            'labelids' => new external_multiple_structure(
                new external_value(PARAM_INT,
                    'ID of a label'),
                )
            ]);
    }

    public static function set_labels_returns() {
        return null;
    }

    public static function set_labels($messageid, $labelids) {
        global $USER;

        $params = ['messageid' => $messageid, 'labelids' => $labelids];
        $params = self::validate_parameters(self::set_labels_parameters(), $params);

        self::validate_context(context_system::instance());

        $message = local_mail_message::fetch($params['messageid']);

        if (!$message || !$message->viewable($USER->id)) {
            throw new moodle_exception('invalidmessage', 'local_mail');
        }

        $userlabels = [];
        foreach (local_mail_label::fetch_user($USER->id) as $label) {
            $userlabels[$label->id()] = $label;
        }

        foreach ($params['labelids'] as $labelid) {
            if (!isset($userlabels[$labelid])) {
                throw new moodle_exception('invalidlabel', 'local_mail');
            }
        }

        foreach ($userlabels as $label) {
            if (in_array($label->id(), $params['labelids'])) {
                $message->add_label($label);
            } else {
                $message->remove_label($label);
            }
        }

        return null;
    }

    public static function format_time(int $timestamp, $forcefull = false): string {
        $tz = core_date::get_user_timezone();
        $date = new DateTime('now', new DateTimeZone($tz));
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

    public static function string_identifiers() {
        return array_keys(\get_string_manager()->load_component_strings('local_mail', 'en'));
    }

    private static function user_picture_url($user) {
        global $PAGE;
        $userpicture = new user_picture($user);
        $userpicture->size = 1;
        return $userpicture->get_url($PAGE)->out(false);
    }
}
