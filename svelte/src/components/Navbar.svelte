<!--
SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { blur } from '../actions/blur';
    import type { Course, Label, Settings, Strings, ViewParams } from '../lib/state';
    import { viewUrl } from '../lib/url';
    import ComposeButton from './ComposeButton.svelte';
    import MenuComponent from './Menu.svelte';
    import PreferencesButton from './PreferencesButton.svelte';

    export let settings: Settings;
    export let strings: Strings;
    export let courses: ReadonlyArray<Course>;
    export let labels: ReadonlyArray<Label>;
    export let params: ViewParams;
    export let onClick: (params: ViewParams) => void;
    export let onComposeClick: (courseid: number) => void;
    export let onCourseChange: (courseid?: number) => void;

    let expanded = false;

    $: unread = courses.reduce((acc, course) => acc + course.unread, 0);

    const closeMenu = () => {
        expanded = false;
    };

    const handleComposeClick = () => {
        closeMenu();
        onComposeClick(params.courseid || courses[0].id);
    };

    const handleIconClick = (event: Event) => {
        if (settings.globaltrays.length > 0 || labels.length > 0) {
            expanded = !expanded;
            event.preventDefault();
        } else {
            event.preventDefault();
            onClick({ tray: 'inbox' });
        }
    };

    const handleMenuClick = (params: ViewParams) => {
        closeMenu();
        onClick(params);
    };

    const handlePreferencesClick = () => {
        closeMenu();
        onClick({ ...params, dialog: 'preferences' });
    };
</script>

<div
    class="local-mail local-mail-navbar pop-over-region h-100"
    class:popover-region-toggle={expanded}
    use:blur={closeMenu}
>
    <a
        aria-expanded={expanded}
        class="nav-link btn h-100 position-relative d-flex align-items-center px-2 py-0 rounded-0"
        href={viewUrl({ tray: 'inbox' })}
        title={strings.pluginname}
        on:click={handleIconClick}
    >
        <i class="icon fa fa-fw fa-envelope-o m-0" aria-label={strings.plugginname} />
        {#if unread > 0}
            <div class="local-mail-navbar-count count-container">{unread}</div>
        {/if}
    </a>
    {#if expanded}
        <div class="local-mail-navbar-popover popover-region-container">
            <div class="d-flex justify-content-between p-2">
                <ComposeButton {strings} onClick={handleComposeClick} />
                <PreferencesButton {strings} onClick={handlePreferencesClick} />
            </div>
            <MenuComponent
                {settings}
                {strings}
                {courses}
                {labels}
                {params}
                navbar={true}
                onClick={handleMenuClick}
                {onCourseChange}
            />
        </div>
    {/if}
</div>

<style global>
    .local-mail-navbar-count {
        top: 50% !important;
        transform: translateY(-16px);
    }

    .local-mail-navbar.popover-region-toggle::after {
        border-bottom-color: var(--light);
    }

    .local-mail-navbar-popover {
        width: 20rem;
        height: auto;
        max-height: 80vh;
        overflow-y: auto;
        background-color: var(--light);
    }

    .local-mail-navbar-popover :global(.list-group-item:not(.list-group-item-primary)) {
        background-color: transparent;
    }

    .local-mail-navbar-popover :global(.list-group-item:not(.list-group-item-primary):hover) {
        background-color: rgba(0, 0, 0, 0.025);
    }

    @media (max-width: 480px) {
        .local-mail-navbar-popover {
            max-height: none;
        }
    }
</style>
