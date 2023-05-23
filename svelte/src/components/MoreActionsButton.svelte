<svelte:options immutable={true} />

<script lang="ts">
    import ConfirmationModal from './ConfirmationModal.svelte';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: disabled =
        $store.params.type == 'trash'
            ? !$store.messageList.totalcount
            : !$store.selectedMessages.size;

    $: setUnread = (unread: boolean) => {
        store.setUnread(Array.from($store.selectedMessages.keys()), unread);
    };

    $: setStarred = (starred: boolean) => {
        store.setStarred(Array.from($store.selectedMessages.keys()), starred);
    };
</script>

<div class="btn-group" role="group">
    <button
        type="button"
        class="local-mail-action-more-button btn btn-secondary dropdown-toggle"
        class:disabled
        {disabled}
        data-toggle="dropdown"
        aria-expanded="false"
        title={$store.strings.moreactions}
    >
        <i class="fa fa-fw fa-ellipsis-v" />
    </button>
    <div class="dropdown-menu">
        {#if $store.params.type == 'trash'}
            <button
                type="button"
                class="dropdown-item"
                data-toggle="modal"
                data-target="#local-mail-action-empty-trash-modal"
            >
                {$store.strings.emptytrash}
            </button>
        {:else}
            {#if Array.from($store.selectedMessages.values()).some((message) => message.unread)}
                <button type="button" class="dropdown-item" on:click={() => setUnread(false)}>
                    {$store.strings.markasread}
                </button>
            {/if}
            {#if Array.from($store.selectedMessages.values()).some((message) => !message.unread)}
                <button type="button" class="dropdown-item" on:click={() => setUnread(true)}>
                    {$store.strings.markasunread}
                </button>
            {/if}
            {#if Array.from($store.selectedMessages.values()).some((message) => !message.starred)}
                <button type="button" class="dropdown-item" on:click={() => setStarred(true)}>
                    {$store.strings.markasstarred}
                </button>
            {/if}
            {#if Array.from($store.selectedMessages.values()).some((message) => message.starred)}
                <button type="button" class="dropdown-item" on:click={() => setStarred(false)}>
                    {$store.strings.markasunstarred}
                </button>
            {/if}
        {/if}
    </div>

    <ConfirmationModal
        id="local-mail-action-empty-trash-modal"
        title={$store.strings.emptytrash}
        body={replaceStringParams(
            $store.strings.messagesdeleteconfirm,
            $store.messageList.totalcount,
        )}
        cancelText={$store.strings.cancel}
        confirmText={$store.strings.emptytrash}
        confirmCallback={() => store.emptyTrash()}
    />
</div>

<style>
    .local-mail-action-more-button::after {
        display: none;
    }
</style>
