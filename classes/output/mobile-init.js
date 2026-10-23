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

/*
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024-2026 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Registers the link and main menu handlers of the mailbox in the Moodle App.
 *
 * {@see \local_mail\output\mobile::init()} sends this file to the app as JavaScript to run.
 *
 * @copyright  2023-2026 Proyecto UNIMOODLE, Albert Gasset
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const that = this;

class AddonLocalMailLinkHandler extends that.CoreContentLinksHandlerBase {
    name = 'AddonLocalMailLinkHandler';
    pattern = new RegExp('/local/mail/view.php');

    getActions(siteIds, url, params) {
        const action = {
            action(siteId) {
                const page = `siteplugins/content/local_mail/view/0`;
                const pageParams = {
                    title: 'plugin.local_mail.pluginname',
                    args: params,
                };
                that.CoreNavigatorService.navigateToSitePath(page, { params: pageParams, siteId });
            },
        };

        return [action];
    }
}

class AddonLocalMaiMainMenuHandler {
    name = 'AddonLocalMailMainMenuHandler';

    async isEnabled() {
        return true;
    }

    getDisplayData() {
        return {
            title: 'plugin.local_mail.pluginname',
            icon: 'far-envelope',
            page: 'siteplugins/content/local_mail/view/0',
            get pageParams() {
                const root = document.documentElement;
                return {
                    title: 'plugin.local_mail.pluginname',
                    args: {
                        appzoom: parseFloat(root.style.getPropertyValue('--zoom-ratio')),
                        appdarkmode: root.classList.contains('dark') ? 1 : 0,
                    },
                };
            },
        };
    }
}

that.CoreMainMenuDelegate.registerHandler(new AddonLocalMaiMainMenuHandler());
that.CoreContentLinksDelegate.registerHandler(new AddonLocalMailLinkHandler());
