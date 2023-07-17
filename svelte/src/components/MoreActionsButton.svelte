<svelte:options immutable={true} />

<script lang="ts">
    import { blur } from '../actions/blur';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';
    import ModalDialog from './ModalDialog.svelte';
    import LabelModal from './LabelModal.svelte';

    export let store: Store;
    export let transparent = false;
    export let dropup = false;

    let expanded = false;
    let editLabelModal = false;
    let deleteLabelModal = false;
    let emptyTrashModal = false;

    $: label =
        $store.params.tray == 'label' && $store.message == null
            ? $store.labels.find((label) => label.id == $store.params.labelid)
            : null;

    $: messages = Array.from($store.selectedMessages.values());
    $: someRead = messages.some((message) => !message.draft && !message.unread);
    $: someUnread = messages.some((message) => !message.draft && message.unread);
    $: someStarred = messages.some((message) => message.starred);
    $: someUnstarred = messages.some((message) => !message.starred);
    $: disabled =
        $store.params.tray == 'trash'
            ? !$store.totalCount
            : !label && !someRead && !someUnread && !someStarred && !someUnstarred;

    const closeMenu = () => {
        expanded = false;
    };

    const toggleMenu = () => {
        expanded = !expanded;
    };

    const setUnread = (unread: boolean) => {
        expanded = false;
        store.setUnread(
            messages.filter((message) => !message.draft).map((message) => message.id),
            unread,
        );
    };

    const setStarred = (starred: boolean) => {
        expanded = false;
        store.setStarred(
            messages.map((message) => message.id),
            starred,
        );
    };

    const openEditLabelModal = () => {
        expanded = false;
        editLabelModal = true;
    };

    const cancelEditLabel = () => {
        editLabelModal = false;
    };

    const updateLabel = async (name: string, color: string) => {
        editLabelModal = false;
        if (label) {
            store.updateLabel(label.id, name, color);
        }
    };

    const openDeleteLabelModal = () => {
        expanded = false;
        deleteLabelModal = true;
    };

    const cancelDeleteLabel = () => {
        deleteLabelModal = false;
    };

    const confirmDeleteLabel = () => {
        deleteLabelModal = false;
        store.deleteLabel($store.params.labelid || 0);
    };

    const openEmptyTrashModal = () => {
        expanded = false;
        emptyTrashModal = true;
    };

    const cancelEmptyTrash = () => {
        emptyTrashModal = false;
    };

    const confirmEmptyTrash = () => {
        emptyTrashModal = false;
        store.emptyTrash();
    };
</script>

<div class="btn-group" class:dropup use:blur={closeMenu}>
    <button
        type="button"
        class="local-mail-action-more-button btn dropdown-toggle"
        class:btn-secondary={!transparent}
        class:disabled
        {disabled}
        aria-expanded={expanded}
        title={$store.strings.moreactions}
        on:click={toggleMenu}
    >
        <i class="fa fa-fw fa-ellipsis-v" />
    </button>
    {#if expanded}
        <div class="dropdown-menu show">
            {#if $store.params.tray == 'trash'}
                <button type="button" class="dropdown-item" on:click={openEmptyTrashModal}>
                    {$store.strings.emptytrash}
                </button>
            {:else}
                {#if someUnread}
                    <button type="button" class="dropdown-item" on:click={() => setUnread(false)}>
                        {$store.strings.markasread}
                    </button>
                {/if}
                {#if someRead}
                    <button type="button" class="dropdown-item" on:click={() => setUnread(true)}>
                        {$store.strings.markasunread}
                    </button>
                {/if}
                {#if someUnstarred}
                    <button type="button" class="dropdown-item" on:click={() => setStarred(true)}>
                        {$store.strings.markasstarred}
                    </button>
                {/if}
                {#if someStarred}
                    <button type="button" class="dropdown-item" on:click={() => setStarred(false)}>
                        {$store.strings.markasunstarred}
                    </button>
                {/if}
                {#if label}
                    {#if someUnread || someRead || someUnstarred || someStarred}
                        <div class="dropdown-divider" />
                    {/if}
                    <button type="button" class="dropdown-item" on:click={openEditLabelModal}>
                        {$store.strings.editlabel}
                    </button>
                    <button type="button" class="dropdown-item" on:click={openDeleteLabelModal}>
                        {$store.strings.deletelabel}
                    </button>
                {/if}
            {/if}
        </div>
    {/if}

    {#if $store.params.tray == 'trash'}
        {#if emptyTrashModal}
            <ModalDialog
                title={$store.strings.emptytrash}
                cancelText={$store.strings.cancel}
                confirmText={$store.strings.emptytrash}
                confirmClass="btn-danger"
                handleCancel={cancelEmptyTrash}
                handleConfirm={confirmEmptyTrash}
            >
                {$store.strings.emptytrashconfirm}
            </ModalDialog>
        {/if}
    {:else if label}
        {#if editLabelModal}
            <LabelModal {store} {label} handleCancel={cancelEditLabel} handleSubmit={updateLabel} />
        {/if}
        {#if deleteLabelModal}
            <ModalDialog
                title={$store.strings.deletelabel}
                cancelText={$store.strings.cancel}
                confirmText={$store.strings.deletelabel}
                confirmClass="btn-danger"
                handleCancel={cancelDeleteLabel}
                handleConfirm={confirmDeleteLabel}
            >
                {replaceStringParams($store.strings.labeldeleteconfirm, label.name)}
            </ModalDialog>
        {/if}
    {/if}
</div>

<style>
    .local-mail-action-more-button::after {
        display: none;
    }
</style>
