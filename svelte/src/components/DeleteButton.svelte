<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';
    import Message from './Message.svelte';

    export let store: Store;
    export let transparent = false;
</script>

<button
    type="button"
    class="local-mail-action-delete btn flex-grow-0"
    class:btn-secondary={!transparent}
    class:disabled={!$store.selectedMessages.size}
    disabled={!$store.selectedMessages.size}
    title={$store.strings.delete}
    on:click={() =>
        store.setDeleted(Array.from($store.selectedMessages.keys()), DeletedStatus.Deleted, true)}
>
    <i class="fa fa-fw fa-trash" />
</button>
