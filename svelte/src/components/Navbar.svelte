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
    let viewportWidth: number;

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

<svelte:window bind:innerWidth={viewportWidth} />

<div
    class="local-mail local-mail-navbar dropdown h-100"
    class:position-static={viewportWidth < 768}
    use:blur={closeMenu}
>
    <a
        aria-expanded={expanded}
        aria-label={strings.togglemailmenu}
        class="btn h-100 position-relative d-flex align-items-center px-2 py-0 rounded-0"
        href={viewUrl({ tray: 'inbox' })}
        on:click={handleIconClick}
    >
        <i class="fa fa-fw fa-envelope-o" aria-label={strings.plugginname} />
        {#if unread > 0}
            <div class="local-mail-navbar-count count-container">{unread}</div>
        {/if}
    </a>
    {#if expanded}
        <div class="local-mail-navbar-dropdown dropdown-menu dropdown-menu-right show p-0">
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

<style>
    .local-mail-navbar-count {
        top: 50%;
        transform: translateY(-16px);
    }
    .local-mail-navbar-dropdown {
        width: 100vw;
        max-width: 20rem;
    }
</style>
