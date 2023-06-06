<svelte:options immutable={true} />

<script lang="ts">
    import MenuComponent from './Menu.svelte';
    import { type Menu, type Settings, type Strings } from '../lib/services';
    import { type ViewParams } from '../lib/store';
    import ComposeButton from './ComposeButton.svelte';
    import PreferencesButton from './PreferencesButton.svelte';
    import { viewUrl } from '../lib/url';

    export let settings: Settings;
    export let strings: Strings;
    export let menu: Menu;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;

    let viewportWidth: number;

    $: displayMenu = settings.globaltrays.length > 0 || menu.labels.length > 0;

    $: handleClick = (event: Event) => {
        if (displayMenu) {
            event.preventDefault();
        } else if (onClick) {
            event.preventDefault();
            onClick({ type: 'inbox' });
        }
    };
</script>

<svelte:window bind:innerWidth={viewportWidth} />

<div class="local-mail-navbar dropdown h-100" class:position-static={viewportWidth < 768}>
    <a
        data-toggle={displayMenu ? 'dropdown' : undefined}
        aria-expanded="false"
        aria-label={strings.togglemailmenu}
        class="btn h-100 position-relative d-flex align-items-center px-2 py-0"
        href={viewUrl({ type: 'inbox' })}
        on:click={handleClick}
    >
        <i class="fa fa-fw fa-envelope-o" aria-label={strings.plugginname} />
        {#if menu.unread > 0}
            <div class="local-mail-navbar-count count-container">{menu.unread}</div>
        {/if}
    </a>
    <div class="local-mail-navbar-dropdown dropdown-menu dropdown-menu-right p-0">
        <div class="d-flex justify-content-between pl-3 pr-2 py-2">
            <ComposeButton {strings} />
            <PreferencesButton {strings} />
        </div>
        <hr class="m-0" />
        <MenuComponent {settings} {strings} {menu} {params} {onClick} flush={true} />
    </div>
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
