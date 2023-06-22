<svelte:options immutable={true} />

<script lang="ts">
    import { blur } from '../actions/blur';
    import type { Course, Label, Settings, Strings } from '../lib/services';
    import type { ViewParams } from '../lib/store';
    import ComposeButton from './ComposeButton.svelte';
    import MenuComponent from './Menu.svelte';
    import PreferencesButton from './PreferencesButton.svelte';
    import { viewUrl } from '../lib/url';

    export let settings: Settings;
    export let strings: Strings;
    export let unread: number;
    export let drafts: number;
    export let courses: ReadonlyArray<Course>;
    export let labels: ReadonlyArray<Label>;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;

    let expanded = false;
    let viewportWidth: number;

    const closeMenu = () => {
        expanded = false;
    };

    const handleIconClick = (event: Event) => {
        if (settings.globaltrays.length > 0 || labels.length > 0) {
            expanded = !expanded;
            event.preventDefault();
        } else if (onClick) {
            event.preventDefault();
            onClick({ tray: 'inbox' });
        }
    };

    const handleMenuClick = (params: ViewParams) => {
        expanded = false;
        onClick?.(params);
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
                <ComposeButton {strings} />
                <PreferencesButton {strings} />
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
                onClick={onClick ? handleMenuClick : undefined}
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
    }
</style>
