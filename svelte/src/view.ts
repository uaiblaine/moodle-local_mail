/*
 * SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 * SPDX-FileCopyrightText: 2024 Albert Gasset <albertgasset@fsfe.org>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import View from './components/View.svelte';
import type { Preferences, Settings, Strings } from './lib/state';
import { createStore } from './lib/store';

import './global.css';

async function init() {
    const viewTarget = document.getElementById('local-mail-view');

    // Get initial data from script tag.
    const data = window.local_mail_view_data;
    if (!data) {
        return;
    }

    const store = await createStore({
        userid: data.userid as number,
        settings: data.settings as Settings,
        preferences: data.preferences as Preferences,
        strings: data.strings as Strings,
        mobile: Boolean(data.mobile),
    });

    if (viewTarget) {
        new View({ target: viewTarget, props: { store } });
    }
}

init();
