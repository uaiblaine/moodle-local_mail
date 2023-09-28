/*
 * SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

import Navbar from './components/Navbar.svelte';
import UserListSendButton from './components/UserListSendButton.svelte';
import UserProfileSendButton from './components/UserProfileSendButton.svelte';
import type { Course, Label, Settings, Strings, ViewParams } from './lib/state';
import { createUrl, viewUrl } from './lib/url';
import './global.css';

async function init() {
    // Get needed data from script tag, to avoid doing web service requests.
    const data = window.local_mail_navbar_data;
    if (!data) {
        return;
    }

    initNavbar(data);

    const url = window.location.origin + window.location.pathname;
    const params = new URLSearchParams(window.location.search);

    if (url == window.M.cfg.wwwroot + '/user/view.php') {
        initUserProfile(
            data,
            parseInt(params.get('id') || ''),
            parseInt(params.get('course') || ''),
        );
    } else if (url == window.M.cfg.wwwroot + '/user/index.php') {
        initUserList(data, parseInt(params.get('id') || ''));
    } else if (url == window.M.cfg.wwwroot + '/blocks/completion_progress/overview.php') {
        initUserList(data, parseInt(params.get('courseid') || ''));
    }
}

function initNavbar(data: Record<string, unknown>) {
    const target = document.getElementById('local-mail-navbar');
    if (target) {
        // Remove fallback link created in local_mail_render_navbar_output.
        target.innerHTML = '';

        const navbar = new Navbar({
            target,
            props: {
                settings: data.settings as Settings,
                strings: data.strings as Strings,
                courses: data.courses as Course[],
                labels: data.labels as Label[],
                params: { courseid: data.courseid as number },
                onClick: (params: ViewParams) => {
                    window.location.href = viewUrl(params);
                },
                onComposeClick: (courseid: number) => {
                    window.location.href = createUrl(courseid);
                },
                onCourseChange: (courseid?: number) => {
                    navbar.$set({ params: { courseid } });
                },
            },
        });
    }
}

function initUserList(data: Record<string, unknown>, courseid: number) {
    const target = document.querySelector('#formactionid')?.parentElement;
    const form = document.querySelector('#participantsform') as HTMLFormElement;
    if (courseid && target && form) {
        new UserListSendButton({
            target,
            props: {
                userid: data.userid as number,
                strings: data.strings as Strings,
                courses: data.courses as Course[],
                courseid,
                form: document.querySelector('#participantsform') as HTMLFormElement,
            },
        });
    }
}

function initUserProfile(data: Record<string, unknown>, id: number, courseid: number) {
    const target = document.querySelector('.userprofile .header-button-group');
    if (id && courseid && target) {
        new UserProfileSendButton({
            target,
            props: {
                userid: data.userid as number,
                strings: data.strings as Strings,
                courses: data.courses as Course[],
                id,
                courseid,
            },
        });
    }
}

init();
