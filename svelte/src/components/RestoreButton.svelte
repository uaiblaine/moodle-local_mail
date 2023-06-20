<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';
    import ModalDialog from './ModalDialog.svelte';

    export let store: Store;
    export let transparent = false;

    let modalOpen = false;

    const open = () => {
        modalOpen = true;
    };

    const cancel = () => {
        modalOpen = false;
    };

    const confirm = () => {
        modalOpen = false;
        store.setDeleted(
            Array.from($store.selectedMessages.keys()),
            DeletedStatus.NotDeleted,
            true,
        );
    };
</script>

<button
    type="button"
    class="local-mail-action-delete btn"
    class:btn-secondary={!transparent}
    class:disabled={!$store.selectedMessages.size}
    disabled={!$store.selectedMessages.size}
    title={$store.strings.restore}
    on:click={open}
>
    <i class="fa fa-fw fa-undo" /></button
>

{#if modalOpen}
    <ModalDialog
        title={$store.strings.restore}
        cancelText={$store.strings.cancel}
        confirmText={$store.strings.restore}
        handleCancel={cancel}
        handleConfirm={confirm}
    >
        {$store.strings.messagerestoreconfirm}
    </ModalDialog>
{/if}
