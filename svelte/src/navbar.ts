import Navbar from './components/Navbar.svelte';
import type { Course, Label, Settings, Strings } from './lib/services';

import './global.css';

async function init() {
    const target = document.getElementById('local-mail-navbar');
    if (target) {
        // Remove fallback link created in local_mail_render_navbar_output.
        target.innerHTML = '';

        // Get needed data from script tag, to avoid doing web service requests.
        const data = window.local_mail_navbar_data;
        if (!data) {
            return;
        }

        new Navbar({
            target,
            props: {
                settings: data.settings as Settings,
                strings: data.strings as Strings,
                unread: data.unread as number,
                drafts: data.drafts as number,
                courses: data.courses as Course[],
                labels: data.labels as Label[],
            },
        });
    }
}

init();
