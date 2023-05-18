<svelte:options immutable={true} />

<script lang="ts">
    import SelectAllBUtton from './SelectAllButton.svelte';
    import PagingButtons from './PagingButtons.svelte';

    import type { Store } from '../lib/store';
    import { createUrl } from '../lib/url';

    export let store: Store;
</script>

<div class="row mb-3 btn-toolbar" role="toolbar">
    <div class="col col-lg-3">
        <a
            class="btn btn-primary px-3 text-truncate"
            role="button"
            href={createUrl($store.params.courseid)}
        >
            <i class="icon fa fa-fw fa-edit mr-1" />
            {$store.strings.compose}</a
        >
    </div>
    <div class="col col-lg-9 d-flex">
        <SelectAllBUtton {store} />
        <PagingButtons {store} />
    </div>
</div>
