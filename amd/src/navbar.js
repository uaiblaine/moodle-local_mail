// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Renders the unread-message badge on the Mail envelope icon in the navbar.
 *
 * @module     local_mail/navbar
 * @copyright  2026 Albert Gasset <albertgasset@fsfe.org>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const init = () => {
    const badge = document.querySelector('#local-mail-navbar .local-mail-navbar-count');
    if (!badge) {
        return;
    }

    const request = {
        methodname: 'local_mail_count_messages',
        args: {
            query: {
                roles: ['to', 'cc', 'bcc'],
                unread: true,
            },
        },
    };

    Ajax.call([request])[0].then((count) => {
        if (count > 0) {
            const locale = window.M && window.M.str && window.M.str.langconfig
                ? window.M.str.langconfig.localecldr
                : undefined;
            badge.textContent = new Intl.NumberFormat(locale).format(count);
            badge.hidden = false;
        }
        return count;
    }).catch(() => {
        // Leave the badge hidden on failure; core/ajax already logs the error.
    });
};
