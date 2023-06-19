import Navbar from './components/Navbar.svelte';

import './global.css';

async function init() {
    const target = document.getElementById('local-mail-navbar');
    if (target) {
        // Remove fallback link created in local_mail_render_navbar_output.
        target.innerHTML = '';

        // Get needed data from script tag, to avoid doing web service requests.
        const data = (window as any).local_mail_navbar_data || {};

        new Navbar({
            target,
            props: {
                settings: data.settings,
                strings: data.strings,
                unread: data.unread,
                drafts: data.drafts,
                courses: data.courses,
                labels: data.labels,
            },
        });
    }
}

init();
