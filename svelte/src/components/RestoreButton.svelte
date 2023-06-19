<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus, type MessageSummary } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let transparent = false;
</script>

<button
    type="button"
    class="local-mail-action-delete btn"
    class:btn-secondary={!transparent}
    class:disabled={!$store.selectedMessages.size}
    disabled={!$store.selectedMessages.size}
    title={$store.strings.restore}
    on:click={() =>
        store.setDeleted(
            Array.from($store.selectedMessages.keys()),
            DeletedStatus.NotDeleted,
            true,
        )}
>
    <i class="fa fa-fw fa-undo" /></button
>
