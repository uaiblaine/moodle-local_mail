<!--
SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { DeletedStatus } from '../lib/state';
    import type { Store } from '../lib/store';
    import ModalDialog from './ModalDialog.svelte';

    export let store: Store;
    export let transparent = false;

    const confirm = () => {
        store.hideDialog();
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
    on:click={() => store.showDialog('restore')}
>
    <i class="fa fa-fw fa-undo" /></button
>

{#if $store.params.dialog == 'restore'}
    <ModalDialog
        title={$store.strings.restore}
        cancelText={$store.strings.cancel}
        confirmText={$store.strings.restore}
        onCancel={() => store.hideDialog()}
        onConfirm={confirm}
    >
        {$store.strings.restoremessageconfirm}
    </ModalDialog>
{/if}
