<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let transparent = false;
</script>

<button
    type="button"
    class="local-mail-action-delete btn flex-grow-0"
    class:btn-secondary={!transparent}
    class:disabled={!$store.targetMessageIds.size}
    disabled={!$store.targetMessageIds.size}
    title={$store.strings.delete}
    on:click={() =>
        store.setDeleted(Array.from($store.targetMessageIds.values()), DeletedStatus.Deleted, true)}
>
    <i class="fa fa-fw fa-trash" />
</button>
