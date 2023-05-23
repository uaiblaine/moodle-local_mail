<svelte:options immutable={true} />

<script lang="ts">
    import ComposeButton from './ComposeButton.svelte';
    import DeleteButton from './DeleteButton.svelte';
    import DeleteForeverButton from './DeleteForeverButton.svelte';
    import LabelsButton from './LabelsButton.svelte';
    import MoreActionsButton from './MoreActionsButton.svelte';
    import PagingButtons from './PagingButtons.svelte';
    import RestoreButton from './RestoreButton.svelte';
    import SelectAllBUtton from './SelectAllButton.svelte';
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<div class="row mb-3 btn-toolbar" role="toolbar">
    <div class="col-auto col-lg-3 pr-0">
        <ComposeButton {store} />
    </div>
    <div class="col col-lg-9 d-flex">
        <SelectAllBUtton {store} />
        <div class="btn-group mr-3" role="group">
            {#if $store.params.type == 'trash'}
                <RestoreButton {store} />
                <DeleteForeverButton {store} />
            {:else}
                <LabelsButton {store} />
                <DeleteButton {store} />
            {/if}
            <MoreActionsButton {store} />
        </div>
        <PagingButtons {store} />
    </div>
</div>
