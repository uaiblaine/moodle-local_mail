<svelte:options immutable={true} />

<script lang="ts">
    import ModalDialog from './ModalDialog.svelte';
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let transparent = false;

    let modalOpen = false;

    const open = () => {
        modalOpen = true;
    };

    const cancel = () => {
        console.log('cancel');
        modalOpen = false;
    };

    const confirm = () => {
        modalOpen = false;
        store.setDeleted(
            Array.from($store.selectedMessages.keys()),
            DeletedStatus.DeletedForever,
            true,
        );
    };
</script>

<button
    type="button"
    class="local-mail-action-delete-forever btn flex-grow-0"
    class:btn-secondary={!transparent}
    class:disabled={!$store.selectedMessages.size}
    disabled={!$store.selectedMessages.size}
    title={$store.strings.deleteforever}
    on:click={open}
>
    <i class="fa fa-fw fa-trash" />
</button>

{#if modalOpen}
    <ModalDialog
        title={$store.strings.deleteforever}
        cancelText={$store.strings.cancel}
        confirmText={$store.strings.deleteforever}
        confirmClass="btn-danger"
        handleCancel={cancel}
        handleConfirm={confirm}
    >
        {replaceStringParams($store.strings.messagesdeleteconfirm, $store.selectedMessages.size)}
    </ModalDialog>
{/if}
