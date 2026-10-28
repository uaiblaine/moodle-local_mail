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
 * Mail in a MooTube course: envelopes beside the course contacts, the "Contact the course team"
 * button, and the participant card's "Message via Mail".
 *
 * format_mtube calls initCourseContacts() with the users already filtered by
 * local_mail\local\mtube_provider, and marks where each entry point goes in its
 * format_mtube/coursecontact template (EXTENDING.md, "Messaging provider").
 *
 * @module     local_mail/mtube_contacts
 * @copyright  2026 Anderson Blaine
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';
import Templates from 'core/templates';
import {open} from 'local_mail/quick_compose';

/**
 * Renders the entry points.
 *
 * @param {Object} config
 * @param {Number} config.courseid Course of the page.
 * @param {Array<Number>} config.contacts Contacts the user may mail, one envelope each.
 * @param {Array<Number>} config.team Recipients of the team button; empty when the course hides it.
 */
export const initCourseContacts = async({courseid, contacts, team}) => {
    const icon = await Templates.renderPix('t/email', 'core');

    const slots = contacts.map((userid) => {
        const item = document.querySelector(`[data-region="mtube-coursecontact"][data-userid="${userid}"]`);
        const slot = item?.querySelector('[data-region="mtube-coursecontact-actions"]');
        const name = item?.querySelector('a[data-userid]')?.textContent.trim() ?? '';
        return {userid, slot, name};
    }).filter(({slot}) => slot);
    const labels = await Promise.all(slots.map(({name}) => getString('mtubesendmailto', 'local_mail', name)));

    slots.forEach(({userid, slot}, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link p-0 ms-2 local-mail-mtube-contact';
        button.setAttribute('aria-label', labels[index]);
        button.title = labels[index];
        button.innerHTML = icon;
        button.addEventListener('click', () => open({courseid, userids: [userid], returnFocus: button}));
        slot.append(button);
    });

    const teamslot = document.querySelector('[data-region="mtube-coursecontacts-team"]');
    if (team.length && teamslot) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary btn-sm local-mail-mtube-team';
        button.innerHTML = icon;
        const label = document.createElement('span');
        label.className = 'ms-1';
        label.textContent = await getString('mtubecontactteam', 'local_mail');
        button.append(label);
        // The course staff write back to each other, so they see who else the student wrote to.
        button.addEventListener('click', () => open({courseid, userids: team, role: 'to', returnFocus: button}));
        teamslot.append(button);
    }
};

let cardListening = false;

/**
 * Handles the participant card's "Message via Mail" button.
 *
 * format_mtube's card intercepts every `[data-action]` click on `document` in the bubble phase and
 * closes itself; this capture-phase listener runs first and leaves the event alone, so the card
 * still closes.
 *
 * @param {Object} config
 * @param {Number} config.courseid Course of the card.
 */
export const initParticipantCard = ({courseid}) => {
    if (cardListening) {
        return;
    }
    cardListening = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.mtube-hover-card [data-action="mtube-messaging-compose"]');
        const userid = parseInt(button?.dataset.userid ?? '', 10);
        if (userid > 0) {
            open({courseid, userids: [userid]});
        }
    }, true);
};
