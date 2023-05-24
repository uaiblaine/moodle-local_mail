<svelte:options immutable={true} />

<script lang="ts">
    import List from './List.svelte';
    import Menu from './Menu.svelte';
    import PerPageSelect from './PerPageSelect.svelte';
    import Toasts from './Toasts.svelte';
    import ToolBar from './ToolBar.svelte';
    import type { Store } from '../lib/store';
    import { getViewParams } from '../lib/url';

    export let store: Store;

    $: title =
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
</script>

<svelte:window on:popstate={() => store.navigate(getViewParams())} />
<svelte:head>
    <title>{title} - {$store.strings.pluginname}</title>
</svelte:head>

<div class="container-fluid local-mail-container" class:local-mail-loading={$store.loading}>
    <ToolBar {store} />
    <div class="row">
        <div class="d-none d-lg-block col-3">
            <Menu {store} />
        </div>
        <div class="col col-lg-9">
            <List {store} />
            <PerPageSelect {store} />
        </div>
    </div>
</div>

<Toasts {store} />

<style>
    :global(#page-local-mail-view2 #page.drawers) {
        padding-left: 0;
        padding-right: 0;
    }

    :global(#page-local-mail-view2 #page.drawers .main-inner) {
        margin-top: 0;
    }

    .local-mail-loading :global(*) {
        cursor: wait;
    }
</style>
