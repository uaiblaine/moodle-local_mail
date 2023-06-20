<svelte:options immutable={true} />

<script lang="ts">
    import BackButton from './BackButton.svelte';
    import DeleteButton from './DeleteButton.svelte';
    import DeleteForeverButton from './DeleteForeverButton.svelte';
    import LabelsButton from './LabelsButton.svelte';
    import MoreActionsButton from './MoreActionsButton.svelte';
    import PagingButtons from './PagingButtons.svelte';
    import RestoreButton from './RestoreButton.svelte';
    import SelectAllButton from './SelectAllButton.svelte';
    import { ViewSize, type Store } from '../lib/store';
    import FilterByCourseInput from './FilterByCourseInput.svelte';

    export let store: Store;
</script>

<div role="toolbar" class="local-mail-toolbar d-flex w-100">
    {#if $store.message}
        <BackButton {store} />
    {:else}
        <SelectAllButton {store} />
    {/if}
    {#if $store.viewSize >= ViewSize.MD}
        <div class="btn-group" role="group">
            {#if $store.params.tray == 'trash'}
                <RestoreButton {store} />
                <DeleteForeverButton {store} />
            {:else}
                <LabelsButton {store} />
                <DeleteButton {store} />
            {/if}
            <MoreActionsButton {store} />
        </div>
    {/if}
    {#if ['shortname', 'fullname'].includes($store.settings.filterbycourse)}
        <FilterByCourseInput {store} />
    {/if}
    {#if $store.viewSize >= ViewSize.MD}
        <PagingButtons {store} />
    {/if}
</div>

<style>
    .local-mail-toolbar {
        column-gap: 1rem;
    }
</style>
