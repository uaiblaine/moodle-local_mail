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

class local_mail_renderer extends plugin_renderer_base {

    /**
     * Returns the URL of the icon representing the format of a file.
     *
     * @param stored_file $file File object.
     * @return string
     */
    public function file_icon_url(stored_file $file): string {
        return $this->image_url(file_file_icon($file, 24))->out(false);
    }

    /**
     * Returns the URL of a file.
     *
     * @param stored_file $file File object.
     * @return string
     */
    public function file_url(stored_file $file): string {
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
        return $fileurl->out(false);
    }

    /**
     * Returns a formatted date and time.
     *
     * The format used depends on the current date:
     * - Same day: hour and minute.
     * - Same year: abbreviated month and day.
     * - Previous years: year, month number and day.
     *
     * @param int $time Unix timestamp.
     * @param bool $full Return full date and time.
     * @param ?int $now Unix timestamp to use as "now" time.
     * @return string
     */
    public function formatted_time(int $time, $full = false, $now = null): string {
        $now = $now ?? time();
        $tz = new DateTimeZone(core_date::get_user_timezone());

        $today = new DateTime();
        $today->setTimezone($tz);
        $today->setTimestamp($now);
        $today->setTime(0, 0, 0);

        $year = new DateTime();
        $year->setTimezone($tz);
        $year->setTimestamp($now);
        $year->setDate($year->format('Y'), 1, 1);
        $year->setTime(0, 0, 0);

        if ($full) {
            return  userdate($time, get_string('strftimedatetime', 'langconfig'));
        } else if ($time > $now) {
            return userdate($time, get_string('strftimedatefullshort', 'langconfig'));
        } else if ($time >= $today->getTimestamp()) {
            return userdate($time, get_string('strftimetime', 'langconfig'));
        } else if ($time >= $year->getTimestamp()) {
            return userdate($time, get_string('strftimedateshortmonthabbr', 'langconfig'));
        } else {
            return userdate($time, get_string('strftimedatefullshort', 'langconfig'));
        }
    }

    /**
     * Returns the script tags needed for a svelte entry script.
     *
     * CSS files are included in the head.
     *
     * @param $file Source file name, e.g. "src/view.ts"
     * @return string
     */
    public function svelte_script(string $file): string {
        global $CFG;

        $html = '';

        if (!empty($CFG->local_mail_devserver)) {
            $jsurl = $CFG->local_mail_devserver . '/' . $file;
        } else {
            $manifestpath = $CFG->dirroot . '/local/mail/svelte/build/manifest.json';
            $manifest = json_decode(file_get_contents($manifestpath), true);
            if (!$manifest || empty($manifest[$file])) {
                throw new coding_exception('local_mail: invalid svelte manifest or script name "' . $file . '"');
            }
            $jsurl = $CFG->wwwroot . '/local/mail/svelte/build/' . $manifest[$file]['file'];
            $chunks = [$file];
            $cssurls = [];
            while ($file = array_pop($chunks)) {
                foreach ($manifest[$file]['imports'] ?? [] as $jsfile) {
                    $chunks[] = $jsfile;
                }
                foreach ($manifest[$file]['css'] ?? [] as $cssfile) {
                    $cssurls[] = new moodle_url('/local/mail/svelte/build/' . $cssfile);
                }
            }
            foreach ($cssurls as $cssurl) {
                if ($this->page->requires->is_head_done()) {
                    // Head already written, add CSS using javascript.
                    $html .= html_writer::script('(function() {
                        var doc = document.getElementsByTagName("head")[0];
                        var link = document.createElement("link");
                        link.rel = "stylesheet";
                        link.href = "' . $cssurl->out(false) . '";
                        doc.appendChild(link);
                    })();');
                } else {
                    $this->page->requires->css($cssurl);
                }
            }
        }

        $html .= html_writer::tag('script', '', ['type' => 'module', 'src' => $jsurl]);

        return $html;
    }
}
