<svelte:options immutable={true} />

<script lang="ts">
    import MenuComponent from './Menu.svelte';
    import { type Menu } from '../lib/services';
    import { type ViewParams } from '../lib/store';
    import ComposeButton from './ComposeButton.svelte';
    import PreferencesButton from './PreferencesButton.svelte';

    export let strings: Readonly<{ [id: string]: string }>;
    export let menu: Menu;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;
</script>

<div class="dropdown h-100">
    <button
        data-toggle="dropdown"
        aria-expanded="false"
        aria-label={strings.togglemailmenu}
        class="btn h-100 position-relative d-flex align-items-center px-2 py-0"
    >
        <i class="fa fa-envelope-o" aria-label={strings.plugginname} />
        <div class="count-container">{menu.unread}</div>
    </button>
    <div class="local-mail-navbar-dropdown dropdown-menu dropdown-menu-right p-0">
        <div class="d-flex justify-content-between pl-3 pr-2 py-2">
            <ComposeButton {strings} />
            <PreferencesButton {strings} />
        </div>
        <hr class="m-0" />
        <MenuComponent {strings} {menu} {params} {onClick} flush={true} />
    </div>
</div>

<style>
</style>
