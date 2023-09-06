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
    export let unread: number;
    export let drafts: number;
    export let courses: ReadonlyArray<Course>;
    export let labels: ReadonlyArray<Label>;
    export let params: ViewParams | undefined = undefined;
    export let onClick: (params: ViewParams) => void;
    export let onComposeClick: (courseid: number) => void;

    let expanded = false;
    let viewportWidth: number;

    const closeMenu = () => {
        expanded = false;
    };

    const handleComposeClick = () => {
        expanded = false;
        onComposeClick(params?.courseid || courses[0].id);
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
        expanded = false;
        onClick(params);
    };

    const handlePreferencesClick = () => {
        expanded = false;
        onClick({
            ...(params || { tray: 'inbox' }),
            dialog: 'preferences',
        });
    };
</script>

<svelte:window bind:innerWidth={viewportWidth} />

<div
    class="local-mail-navbar dropdown h-100"
    class:position-static={viewportWidth < 768}
    use:blur={closeMenu}
>
    <a
        aria-expanded={expanded}
        aria-label={strings.togglemailmenu}
        class="btn h-100 position-relative d-flex align-items-center px-2 py-0"
        href={viewUrl({ tray: 'inbox' })}
        on:click={handleIconClick}
    >
        <i class="fa fa-fw fa-envelope-o" aria-label={strings.plugginname} />
        {#if unread > 0}
            <div class="local-mail-navbar-count count-container">{unread}</div>
        {/if}
    </a>
    {#if expanded}
        <div
            class="local-mail-navbar-dropdown dropdown-menu dropdown-menu-right show p-0 overflow-auto"
        >
            <div class="d-flex justify-content-between pl-3 pr-2 py-2">
                <ComposeButton {strings} onClick={handleComposeClick} />
                <PreferencesButton {strings} onClick={handlePreferencesClick} />
            </div>
            <hr class="m-0" />
            <MenuComponent
                {settings}
                {strings}
                {unread}
                {drafts}
                {courses}
                {labels}
                {params}
                onClick={handleMenuClick}
                flush={true}
            />
        </div>
    {/if}
</div>

<style>
    .local-mail-navbar :global(.fa) {
        font-size: 16px;
    }
    .local-mail-navbar-count {
        top: 50%;
        transform: translateY(-16px);
    }
    .local-mail-navbar-dropdown {
        width: 100vw;
        max-width: 20rem;
        background-color: var(--light);
        box-shadow: -2px 2px 4px rgba(0, 0, 0, 0.1);
    }
    .local-mail-navbar-dropdown :global(.list-group-item) {
        background-color: var(--light);
    }
</style>
