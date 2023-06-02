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

    const clickHandler = (message: MessageSummary) => {
        return (event: MouseEvent) => {
            // Check if the event target or its parent is a button,
            // which would mean that the select or star button is being clicked.
            if ((event.target as HTMLElement).matches('button, button > *')) {
                event.preventDefault();
            } else if (!message.draft) {
                event.preventDefault();
                store.navigate({ ...$store.params, messageid: message.id });
            }
        };
    };
</script>

{#key $store.listKey}
    <div class="list-group">
        {#each $store.list.messages as message (message.id)}
            <a
                animate:flip={{ delay: 400, duration: 400 }}
                in:fade|local={{ delay: 400 }}
                out:fade|local={{ duration: 400 }}
                class="local-mail-list-message list-group-item list-group-item-action p-0"
                href={message.draft
                    ? composeUrl(message.id)
                    : viewUrl({ ...$store.params, messageid: message.id })}
                class:list-group-item-primary={$store.selectedMessageIds.has(message.id)}
                class:list-group-item-secondary={!message.unread &&
                    !$store.selectedMessageIds.has(message.id)}
                class:font-weight-bold={message.unread}
                on:click={clickHandler(message)}
            >
                {#if $store.viewSize >= ViewSize.MD}
                    <div class="d-flex align-items-center pl-1">
                        <ListMessageCheckbox {store} {message} />
                        <ListMessageStar {store} {message} />
                        <ListMessageUsers {store} {message} />
                        <ListMessageSubject {store} {message} />
                        <div class="d-flex mt-2">
                            <ListMessageLabels {store} {message} />
                        </div>
                        <ListMessageAttachments {store} {message} />
                        <ListMessageTime {store} {message} />
                    </div>
                {:else}
                    <div class="d-flex align-items-start pt-1 pb-2 pl-1">
                        <ListMessageCheckbox {store} {message} />
                        <div class="flex-shrink-1 w-100 ml-1" style="min-width: 0">
                            <div class="d-flex mt-2">
                                <ListMessageUsers {store} {message} />
                                <ListMessageAttachments {store} {message} />
                                <ListMessageTime {store} {message} />
                            </div>
                            <div class="d-flex">
                                <div class="d-flex w-100 d-shrink-1 my-2" style="min-width: 0">
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
