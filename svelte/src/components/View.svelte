<svelte:options immutable={true} />

<script lang="ts">
    import ErrorModal from './ErrorModal.svelte';
    import List from './List.svelte';
    import Menu from './Menu.svelte';
    import Message from './Message.svelte';
    import PerPageSelect from './PerPageSelect.svelte';
    import Toasts from './Toasts.svelte';
    import ToolBar from './ToolBar.svelte';
    import type { Store } from '../lib/store';
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
            ? $store.menu.courses.find((c) => c.id == $store.params.courseid)?.shortname || ''
            : '';

    $: title = $store.message ? $store.message.subject : heading;
</script>

<svelte:window on:popstate={() => store.navigate(getViewParamsFromUrl())} />
<svelte:head>
    <title>{title} - {$store.strings.pluginname}</title>
</svelte:head>

<div class="container-fluid local-mail-container" class:local-mail-loading={$store.loading}>
    <h1 class="h2 my-4">
        {$store.strings.pluginname} <i class="fa fa-angle-right mx-1" aria-hidden="true" />
        {heading}
    </h1>
    <ToolBar {store} />
    <div class="row">
        <div class="d-none d-lg-block col-3">
            <Menu
                strings={$store.strings}
                menu={$store.menu}
                params={$store.params}
                onClick={(params) => store.navigate(params)}
            />
        </div>
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

<Toasts {store} />
<ErrorModal {store} />

<style>
    :global(#page-local-mail-view #topofscroll) {
        padding-top: 0;
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

    .local-mail-loading :global(*) {
        cursor: wait;
    }
</style>
