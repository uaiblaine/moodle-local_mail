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
 * "Send mail" on a user's course profile and on a course's participants page.
 *
 * The server decides whether each entry point appears (local_mail\hook_callbacks); this module
 * only wires it to the quick-compose modal.
 *
 * @module     local_mail/entry_points
 * @copyright  2026 Anderson Blaine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Only for the participants page's "With selected users..." menu: core/custom_interaction_events
// fires its cie:accessibleChange through jQuery, which no native listener sees (Moodle 4.5 to 5.3).
import $ from 'jquery';
import {getString} from 'core/str';
import Templates from 'core/templates';
import {open} from 'local_mail/quick_compose';

const MAIL_ACTION = '#local_mail_compose';

/**
 * Wires the profile node and adds the header button.
 *
 * @param {Object} config
 * @param {Number} config.courseid Course of the profile.
 * @param {Number} config.userid Owner of the profile.
 * @param {Boolean} config.header Whether to add the header button.
 * @param {String} config.url Full editor URL, the links' target without JavaScript.
 */
export const initProfile = async({courseid, userid, header, url}) => {
    const launch = (event) => {
        event.preventDefault();
        open({courseid, userids: [userid], returnFocus: event.currentTarget});
    };

    document.querySelectorAll('.local-mail-sendmail a').forEach((link) => link.addEventListener('click', launch));

    const contextheader = header ? document.querySelector('.userprofile .page-context-header') : null;
    if (!contextheader) {
        return;
    }

    // Core prints the button group only when it has a button of its own (its "Message" needs
    // core messaging), so it is created with core's classes when missing.
    let group = contextheader.querySelector('.header-button-group');
    if (!group) {
        group = document.createElement('div');
        group.className = 'btn-group header-button-group d-block mx-sm-3';
        contextheader.append(group);
    }

    const [title, icon] = await Promise.all([
        getString('sendmail', 'local_mail'),
        Templates.renderPix('t/email', 'core'),
    ]);
    const button = document.createElement('a');
    button.href = url;
    button.className = 'btn local-mail-sendmail-button';
    button.setAttribute('role', 'button');
    button.innerHTML = icon;
    const label = document.createElement('span');
    label.className = 'header-button-title';
    label.textContent = title;
    button.append(label);
    button.addEventListener('click', launch);
    group.append(button);
};

/**
 * Adds "Send mail" to the bulk action menu of the participants table.
 *
 * Core's handler (core_user/participants) acts on its own `cie:accessibleChange` event and resets
 * the select before the event bubbles further, and two AMD inits run in no guaranteed order. So a
 * capture-phase listener only records the chosen value, and the delegated handler acts on the
 * record. Core ignores a `#` value it does not know, so nothing is submitted.
 *
 * @param {Object} config
 * @param {Number} config.courseid Course of the participants page.
 */
export const initParticipants = async({courseid}) => {
    const select = document.getElementById('formactionid');
    if (!select) {
        return;
    }

    const option = document.createElement('option');
    option.value = MAIL_ACTION;
    option.textContent = await getString('sendmail', 'local_mail');
    select.append(option);

    let chosen = '';
    document.addEventListener('change', (event) => {
        if (event.target === select) {
            chosen = select.value;
        }
    }, true);

    // The custom event is a jQuery event (core/custom_interaction_events), invisible to native listeners.
    $(document).on('cie:accessibleChange', '#formactionid', () => {
        const action = chosen;
        chosen = '';
        if (action !== MAIL_ACTION) {
            return;
        }

        const userids = Array.from(select.form.querySelectorAll('input[type="checkbox"][name^="user"]:checked'))
            .map((checkbox) => parseInt(checkbox.name.replace('user', ''), 10))
            .filter((id) => id > 0);
        if (userids.length) {
            open({courseid, userids, returnFocus: select});
        }
    });
};
