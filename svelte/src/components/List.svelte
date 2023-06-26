<svelte:options immutable={true} />

<script lang="ts">
    import { flip } from 'svelte/animate';
    import { fade } from 'svelte/transition';

    import { ViewSize, type Store } from '../lib/store';
    import type { MessageSummary } from '../lib/services';
    import { composeUrl, viewUrl } from '../lib/url';
    import ListMessageCheckbox from './ListMessageCheckbox.svelte';
    import ListMessageStar from './ListMessageStar.svelte';
    import ListMessageUsers from './ListMessageUsers.svelte';
    import ListMessageSubject from './ListMessageSubject.svelte';
    import ListMessageLabels from './ListMessageLabels.svelte';
    import ListMessageAttachments from './ListMessageAttachments.svelte';
    import ListMessageTime from './ListMessageTime.svelte';
    import ListEmptyAlert from './ListAlert.svelte';

    export let store: Store;

    const messageParams = (message: MessageSummary, i: number) => {
        return {
            ...$store.params,
            messageid: message.id,
            offset: ($store.params.offset || 0) + i,
        };
    };

    const clickHandler = (message: MessageSummary, i: number) => {
        return (event: MouseEvent) => {
            if (!message.draft) {
                event.preventDefault();
                store.navigate(messageParams(message, i));
            }
        };
    };
</script>

{#key $store.navigationId}
    <div class="list-group">
        {#each $store.listMessages as message, i (message.id)}
            <a
                animate:flip={{ delay: 400, duration: 400 }}
                in:fade|local={{ delay: 400 }}
                out:fade|local={{ duration: 400 }}
                class="local-mail-list-message list-group-item list-group-item-action p-0"
                href={message.draft ? composeUrl(message.id) : viewUrl(messageParams(message, i))}
                class:list-group-item-primary={$store.selectedMessages.has(message.id)}
                class:list-group-item-secondary={!message.unread &&
                    !$store.selectedMessages.has(message.id)}
                class:font-weight-bold={message.unread}
                on:click={clickHandler(message, i)}
            >
                {#if $store.viewSize >= ViewSize.MD}
                    <div class="d-flex align-items-center pl-1 pr-3">
                        <ListMessageCheckbox {store} {message} />
                        <ListMessageStar {store} {message} />
                        <div
                            class="d-flex w-100 flex-grow-1 align-items-center"
                            style="min-width: 0"
                        >
                            <div class="flex-grow-1 mx-2" style="width: 25%; min-width: 0">
                                <ListMessageUsers {store} {message} />
                            </div>
                            <div
                                class="flex-grow-1 d-flex my-2 mx-2"
                                style="width: 75%; min-width: 0"
                            >
                                <ListMessageSubject {store} {message} />
                                <div
                                    class="d-flex text-truncate flex-shrink-0 justify-content-end mb-n2 ml-auto"
                                    style="max-width: 80%"
                                >
                                    <ListMessageLabels {store} {message} />
                                </div>
                            </div>
                        </div>
                        <ListMessageAttachments {store} {message} />
                        <ListMessageTime {store} {message} />
                    </div>
                {:else}
                    <div class="d-flex align-items-start pt-1 pb-2 pl-1 pr-2">
                        <ListMessageCheckbox {store} {message} />
                        <div class="flex-shrink-1 w-100 ml-1" style="min-width: 0">
                            <div class="d-flex mt-2">
                                <ListMessageUsers {store} {message} />
                                <ListMessageAttachments {store} {message} />
                                <div class="ml-auto mr-2">
                                    <ListMessageTime {store} {message} />
                                </div>
                            </div>
                            <div class="d-flex">
                                <div class="d-flex w-100 d-shrink-1 my-2 mr-2" style="min-width: 0">
                                    <ListMessageSubject {store} {message} />
                                </div>
                                <ListMessageStar {store} {message} />
                            </div>
                            <div class="d-flex flex-wrap ml-n2 mr-2">
                                <ListMessageLabels {store} {message} />
                            </div>
                        </div>
                    </div>
                {/if}
            </a>
        {/each}
        <ListEmptyAlert {store} />
    </div>
{/key}

<style>
    .local-mail-list-message {
        color: var(--dark) !important;
    }
</style>
