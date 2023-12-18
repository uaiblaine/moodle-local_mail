/*
 * SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import Navbar from './components/Navbar.svelte';
import View from './components/View.svelte';
import type { Preferences, Settings, Strings } from './lib/state';
import { createStore } from './lib/store';

import './global.css';

async function init() {
    const viewTarget = document.getElementById('local-mail-view');
    const navbarTarget = document.getElementById('local-mail-navbar');

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

    if (navbarTarget) {
        // Remove fallback link created in local_mail_render_navbar_output.
        navbarTarget.innerHTML = '';

        // Instantiate Navbar component with current store data.
        const state = store.get();
        const navbar = new Navbar({
            target: navbarTarget,
            props: {
                settings: state.settings,
                strings: state.strings,
                courses: state.courses,
                labels: state.labels,
                params: state.params,
                onClick: store.navigate,
                onComposeClick: store.createMessage,
                onCourseChange: store.selectCourse,
            },
        });

        // Update properties when store data changes.
        store.subscribe((state) => {
            navbar.$set({
                settings: state.settings,
                strings: state.strings,
                courses: state.courses,
                labels: state.labels,
                params: state.params,
            });
        });
    }
}

init();
