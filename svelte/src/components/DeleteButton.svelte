<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus } from '../lib/state';
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
    title={$store.strings.delete}
    on:click={() =>
        store.setDeleted(Array.from($store.selectedMessages.keys()), DeletedStatus.Deleted, true)}
>
    <i class="fa fa-fw fa-trash" />
</button>
