<svelte:options immutable={true} />

<script lang="ts">
    import { onMount } from 'svelte';

    import BackButton from './BackButton.svelte';
    import SelectAllButton from './SelectAllButton.svelte';
    import ComposeButton from './ComposeButton.svelte';
    import ErrorModal from './ErrorModal.svelte';
    import List from './List.svelte';
    import Menu from './Menu.svelte';
    import Message from './Message.svelte';
    import PerPageSelect from './PerPageSelect.svelte';
    import SearchInput from './SearchInput.svelte';
    import Toasts from './Toasts.svelte';
    import ToolBar from './ToolBar.svelte';
    import { ViewSize, type Store } from '../lib/store';
    import { getViewParamsFromUrl } from '../lib/url';

    export let store: Store;

    $: heading =
        $store.params.type == 'inbox'
            ? $store.strings.inbox
            : $store.params.type == 'starred'
            ? $store.strings.starredmail
            : $store.params.type == 'sent'
            ? $store.strings.sentmail
            : $store.params.type == 'drafts'
            ? $store.strings.drafts
            : $store.params.type == 'trash'
            ? $store.strings.trash
            : $store.params.type == 'label'
            ? $store.menu.labels.find((l) => l.id == $store.params.labelid)?.name || ''
            : $store.params.type == 'course'
            ? $store.menu.courses.find((c) => c.id == $store.params.courseid)?.fullname || ''
            : '';

    $: title = $store.message ? $store.message.subject : heading;

    onMount(() => {
        store.setViewportSize(window.innerWidth);
    });
</script>

<svelte:window
    on:resize={() => store.setViewportSize(window.innerWidth)}
    on:popstate={() => store.navigate(getViewParamsFromUrl())}
/>
<svelte:head>
    <title>{title} - {$store.strings.pluginname}</title>
</svelte:head>

<div class="container-fluid my-4" class:local-mail-loading={$store.loading}>
    <!-- Heading / search / compose button -->
    <div class="row align-items-center">
        <h1 class="h2 col-12 col-lg-3 text-truncate mb-4">
            {$store.strings.pluginname}
            {#if $store.viewSize < ViewSize.LG}
                <i class="fa fa-angle-right mx-1" aria-hidden="true" />
                {heading}
            {/if}
        </h1>

        <div class="col col-lg-6 mb-4">
            <SearchInput {store} />
        </div>
        {#if $store.viewSize < ViewSize.LG}
            <div class="col-12 col-sm-auto mb-4">
                <ComposeButton strings={$store.strings} courseid={$store.params.courseid} />
            </div>
        {/if}
    </div>

    <!-- Toolbar -->
    <div class="row mb-3">
        {#if $store.viewSize >= ViewSize.LG}
            <div class="col-3">
                <ComposeButton strings={$store.strings} courseid={$store.params.courseid} />
            </div>
        {/if}
        <div class="col col-lg-9 d-flex">
            {#if $store.message}
                <BackButton {store} />
            {:else}
                <SelectAllButton {store} />
            {/if}
            {#if $store.viewSize >= ViewSize.MD}
                <ToolBar {store} />
            {/if}
        </div>
    </div>

    <!-- List / Messaege -->
    <div class="row">
        {#if $store.viewSize >= ViewSize.LG}
            <div class="d-none d-lg-block col-3">
                <Menu
                    settings={$store.settings}
                    strings={$store.strings}
                    menu={$store.menu}
                    params={$store.params}
                    onClick={(params) => store.navigate(params)}
                />
            </div>
        {/if}
        <div class="col col-lg-9">
            {#if $store.message}
                <Message {store} message={$store.message} />
            {:else}
                <List {store} />
                <PerPageSelect {store} />
            {/if}
        </div>
    </div>
</div>

{#if $store.viewSize < ViewSize.MD}
    <ToolBar {store} fixed={true} />
{/if}

<Toasts {store} />
<ErrorModal {store} />

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

    .local-mail-loading :global(*) {
        cursor: wait;
    }
</style>
