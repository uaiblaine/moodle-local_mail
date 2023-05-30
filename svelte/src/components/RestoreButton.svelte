<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus, type MessageSummary } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-action-delete btn btn-secondary"
    class:disabled={!$store.selectedIds.size}
    disabled={!$store.selectedIds.size}
    title={$store.strings.restore}
    on:click={() =>
        store.setDeleted(Array.from($store.selectedIds.values()), DeletedStatus.NotDeleted, true)}
>
    <i class="fa fa-fw fa-undo" /></button
>
