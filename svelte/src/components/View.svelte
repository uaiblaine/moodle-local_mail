<svelte:options immutable={true} />

<script lang="ts">
    import { onMount, afterUpdate } from 'svelte';

    import ComposeButton from './ComposeButton.svelte';
    import ErrorModal from './ErrorModal.svelte';
    import BottomToolBar from './BottomToolBar.svelte';
    import List from './List.svelte';
    import Menu from './Menu.svelte';
    import Message from './Message.svelte';
    import PerPageSelect from './PerPageSelect.svelte';
    import SearchBox from './SearchBox.svelte';
    import Toasts from './Toasts.svelte';
    import TopToolBar from './TopToolBar.svelte';
    import { ViewSize, type Store } from '../lib/store';
    import { getViewParamsFromUrl } from '../lib/url';

    export let store: Store;

    let viewNode: HTMLElement;
    let prevNavigationId = 0;

    $: heading =
        $store.params.tray == 'inbox'
            ? $store.strings.inbox
            : $store.params.tray == 'starred'
            ? $store.strings.starredmail
            : $store.params.tray == 'sent'
            ? $store.strings.sentmail
            : $store.params.tray == 'drafts'
            ? $store.strings.drafts
            : $store.params.tray == 'trash'
            ? $store.strings.trash
            : $store.params.tray == 'label'
            ? $store.labels.find((l) => l.id == $store.params.labelid)?.name || ''
            : $store.params.tray == 'course'
            ? $store.courses.find((c) => c.id == $store.params.courseid)?.fullname || ''
            : '';

    $: title = $store.message ? $store.message.subject : heading;

    onMount(() => {
        store.setViewportSize(window.innerWidth);
    });

    afterUpdate(() => {
        if (prevNavigationId != $store.navigationId) {
            prevNavigationId = $store.navigationId;
            viewNode.scrollIntoView();
        }
    });
</script>

<svelte:window
    on:resize={() => store.setViewportSize(window.innerWidth)}
    on:popstate={() => store.navigate(getViewParamsFromUrl())}
/>
<svelte:head>
    <title>{title} - {$store.strings.pluginname}</title>
</svelte:head>

<div
    class="local-mail-view container-fluid py-4"
    class:local-mail-loading={$store.loading}
    bind:this={viewNode}
>
    <!-- Heading / search / compose button -->
    <div class="row align-items-center">
        <h1 class="h2 local-mail-view-side-column text-truncate mb-4">
            {$store.strings.pluginname}
            {#if $store.viewSize < ViewSize.LG}
                <i class="fa fa-angle-right mx-1" aria-hidden="true" />
                {heading}
            {/if}
        </h1>

        <div class="local-mail-view-main-column d-flex mb-4">
            <div class="local-mail-view-search flex-shrink-1">
                <SearchBox {store} />
            </div>
            {#if $store.viewSize < ViewSize.LG}
                <div class="flex-shrink-1 text-truncate d-flex">
                    <ComposeButton strings={$store.strings} courseid={$store.params.courseid} />
                </div>
            {/if}
        </div>
    </div>

    <!-- Toolbar -->
    <div class="row mb-3">
        {#if $store.viewSize >= ViewSize.LG}
            <div class="local-mail-view-side-column">
                <ComposeButton strings={$store.strings} courseid={$store.params.courseid} />
            </div>
        {/if}
        <div class="local-mail-view-main-column d-flex">
            <TopToolBar {store} />
        </div>
    </div>

    <!-- List / Messaege -->
    <div class="row mb-3">
        {#if $store.viewSize >= ViewSize.LG}
            <div class="local-mail-view-side-column">
                <Menu
                    settings={$store.settings}
                    strings={$store.strings}
                    unread={$store.unread}
                    drafts={$store.drafts}
                    courses={$store.courses}
                    labels={$store.labels}
                    params={$store.params}
                    onClick={(params) => store.navigate(params)}
                />
            </div>
        {/if}
        <div class="local-mail-view-main-column">
            {#if $store.message}
                <Message {store} message={$store.message} />
            {:else}
                <List {store} />
                <PerPageSelect {store} />
            {/if}
        </div>
    </div>

    {#if $store.viewSize < ViewSize.MD}
        <BottomToolBar {store} />
    {/if}

    <Toasts {store} />
    <ErrorModal {store} />
</div>

<style>
    :global(#page-local-mail-view #topofscroll) {
        padding: 0;
        margin-bottom: 0;
    }
    :global(#page-local-mail-view #region-main-box) {
        padding-left: 0;
        padding-right: 0;
    }

    :global(#page-local-mail-view #page-header) {
        display: none;
    }

    :global(#page-local-mail-view #page.drawers) {
        padding-left: 0;
        padding-right: 0;
    }

    :global(#page-local-mail-view #page.drawers .main-inner) {
        margin-top: 0;
    }

    :global(#page-local-mail-view .btn-footer-popover) {
        position: static;
        margin: 0 2rem 2rem auto;
    }

    .local-mail-view :global(.dropdown-menu) {
        z-index: 1040;
    }

    .local-mail-view :global(.dropdown-item:not(:focus):hover) {
        color: inherit;
        background-color: #eee;
    }

    .local-mail-view {
        max-width: 100rem;
    }

    .local-mail-view :global(.fa) {
        font-size: 16px;
    }

    .local-mail-view-main-column {
        padding-right: 15px;
        padding-left: 15px;
        flex-basis: 100%;
        flex-grow: 1;
        min-width: 0;
        column-gap: 1rem;
    }

    .local-mail-view-side-column {
        padding-right: 15px;
        padding-left: 15px;
        flex-basis: 100%;
        flex-grow: 1;
        min-width: 0;
    }

    .local-mail-view-search {
        flex-grow: 1;
        margin-right: auto;
    }

    .local-mail-loading :global(*) {
        cursor: wait;
    }

    @media (min-width: 992px) {
        .local-mail-view-main-column {
            flex-basis: 75%;
            flex-shrink: 1;
        }
        .local-mail-view-side-column {
            flex-basis: 25%;
            flex-shrink: 1;
            max-width: 18rem;
        }
        .local-mail-view-search {
            max-width: 30rem;
        }
    }
</style>
