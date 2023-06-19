<svelte:options immutable={true} />

<script lang="ts">
    import ConfirmationModal from './ConfirmationModal.svelte';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';
    import LabelModal from './LabelModal.svelte';

    export let store: Store;
    export let transparent = false;
    export let dropup = false;

    $: label =
        $store.params.tray == 'label' && $store.message == null
            ? $store.labels.find((label) => label.id == $store.params.labelid)
            : null;

    $: messages = Array.from($store.selectedMessages.values());
    $: someRead = messages.some((message) => !message.unread);
    $: someUnread = messages.some((message) => message.unread);
    $: someStarred = messages.some((message) => message.starred);
    $: someUnstarred = messages.some((message) => !message.starred);
    $: disabled =
        $store.params.tray == 'trash'
            ? !$store.totalCount
            : !label && !someRead && !someUnread && !someStarred && !someUnstarred;

    const setUnread = (unread: boolean) => {
        store.setUnread(
            messages.map((message) => message.id),
            unread,
        );
    };

    const setStarred = (starred: boolean) => {
        store.setStarred(
            messages.map((message) => message.id),
            starred,
        );
    };
</script>

<div class="btn-group" class:dropup>
    <button
        type="button"
        class="local-mail-action-more-button btn dropdown-toggle"
        class:btn-secondary={!transparent}
        class:disabled
        {disabled}
        data-toggle="dropdown"
        aria-expanded="false"
        title={$store.strings.moreactions}
    >
        <i class="fa fa-fw fa-ellipsis-v" />
    </button>
    <div class="dropdown-menu">
        {#if $store.params.tray == 'trash'}
            <button
                type="button"
                class="dropdown-item"
                data-toggle="modal"
                data-target="#local-mail-action-empty-trash-modal"
            >
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
                <button
                    type="button"
                    class="dropdown-item"
                    data-toggle="modal"
                    data-target="#local-mail-label-modal-{$store.params.labelid}"
                >
                    {$store.strings.editlabel}
                </button>
                <button
                    type="button"
                    class="dropdown-item"
                    data-toggle="modal"
                    data-target="#local-mail-action-delete-label-modal"
                >
                    {$store.strings.deletelabel}
                </button>
            {/if}
        {/if}
    </div>

    {#if $store.params.tray == 'trash'}
        <ConfirmationModal
            id="local-mail-action-empty-trash-modal"
            title={$store.strings.emptytrash}
            body={replaceStringParams($store.strings.messagesdeleteconfirm, $store.totalCount)}
            cancelText={$store.strings.cancel}
            confirmText={$store.strings.emptytrash}
            confirmCallback={() => store.emptyTrash()}
        />
    {:else if label}
        <LabelModal {store} {label} />
        <ConfirmationModal
            id="local-mail-action-delete-label-modal"
            title={$store.strings.deletelabel}
            body={replaceStringParams($store.strings.labeldeleteconfirm, label.name)}
            cancelText={$store.strings.cancel}
            confirmText={$store.strings.deletelabel}
            confirmCallback={() => store.deleteLabel($store.params.labelid || 0)}
        />
    {/if}
</div>

<style>
    .local-mail-action-more-button::after {
        display: none;
    }
</style>
