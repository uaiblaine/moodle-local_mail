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
 * Opens a modal that sends a mail to a fixed set of course users.
 *
 * This is the entry point other code calls (the profile, the participants page, the MooTube
 * provider). The form, its rules and the send all live server-side in
 * local_mail\form\quick_compose.
 *
 * @module     local_mail/quick_compose
 * @copyright  2026 Anderson Blaine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

/**
 * Opens the quick-compose modal.
 *
 * @param {Object} options
 * @param {Number} options.courseid Course the mail belongs to.
 * @param {Array<Number|String>} options.userids Recipients; the sender's own id is ignored.
 * @param {String} [options.role] "to" or "bcc"; by default "to" for one recipient, "bcc" for more.
 * @param {HTMLElement} [options.returnFocus] Element that gets the focus back when the modal closes.
 * @returns {ModalForm}
 */
export const open = ({courseid, userids, role = '', returnFocus = null}) => {
    const modalForm = new ModalForm({
        formClass: 'local_mail\\form\\quick_compose',
        args: {
            courseid,
            recipients: userids.join(','),
            role,
        },
        modalConfig: {
            title: getString('sendmail', 'local_mail'),
            large: true,
        },
        saveButtonText: getString('send', 'local_mail'),
        returnFocus,
    });

    modalForm.addEventListener(modalForm.events.FORM_SUBMITTED, async(event) => {
        const [sent, view] = await Promise.all([
            getString('messagesent', 'local_mail'),
            getString('viewmessage', 'local_mail'),
        ]);
        const url = new URL(M.cfg.wwwroot + '/local/mail/view.php');
        url.searchParams.set('t', 'sent');
        url.searchParams.set('m', String(event.detail.messageid));
        // The toast takes HTML: the strings go in as text nodes, so a translation cannot add markup.
        const toast = document.createElement('span');
        const link = document.createElement('a');
        link.href = url.href;
        link.textContent = view;
        toast.append(`${sent} `, link);
        addToast(toast.innerHTML);
    });

    modalForm.show();

    return modalForm;
};
