<svelte:options immutable={true} />

<script lang="ts">
    import BackButton from './BackButton.svelte';
    import CourseSelect from './CourseSelect.svelte';
    import DeleteButton from './DeleteButton.svelte';
    import DeleteForeverButton from './DeleteForeverButton.svelte';
    import LabelsButton from './LabelsButton.svelte';
    import MoreActionsButton from './MoreActionsButton.svelte';
    import PagingButtons from './PagingButtons.svelte';
    import RestoreButton from './RestoreButton.svelte';
    import SelectAllButton from './SelectAllButton.svelte';
    import { ViewSize, type Store } from '../lib/store';
    import SendButton from './SendButton.svelte';
    import { truncate } from '../actions/truncate';

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
    {#if !$store.message?.draft && ['shortname', 'fullname'].includes($store.settings.filterbycourse)}
        <div
            class="d-flex flex-grow-1 ml-auto mr-0 ml-md-0 mr-md-auto"
            style="max-width: 20rem; min-width: 0"
        >
            <CourseSelect
                {store}
                label={$store.strings.filterbycourse}
                selected={$store.params.courseid}
                readonly={$store.params.tray == 'course'}
                onChange={(id) => store.selectCourse(id)}
                primary={true}
                align={$store.viewSize >= ViewSize.MD ? 'left' : 'right'}
            />
        </div>
    {/if}
    {#if $store.message?.draft && $store.draftSaved}
        <div class="align-self-center" use:truncate={$store.strings.draftsaved}>
            {$store.strings.draftsaved}
        </div>
    {/if}

    {#if $store.viewSize >= ViewSize.MD}
        <PagingButtons {store} />
    {:else if $store.message?.draft}
        <SendButton {store} />
    {/if}
</div>

<style>
    .local-mail-toolbar {
        column-gap: 1rem;
    }
</style>
