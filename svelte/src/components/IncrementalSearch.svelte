<svelte:options immutable={true} />

<script lang="ts">
    import { onDestroy } from 'svelte';
    import {
        callServices,
        type Query,
        type MessageSummary,
        type ServiceError,
        type SearchMessagesRequest,
    } from '../lib/services';
    import type { Store, ViewParams } from '../lib/store';
    import ListMessageSubject from './ListMessageSubject.svelte';
    import ListMessageUsers from './ListMessageUsers.svelte';
    import { composeUrl, viewUrl } from '../lib/url';

    export let store: Store;
    export let enabled: boolean;
    export let content: string;
    export let loading = false;
    export let handleClick: (params: ViewParams) => void;

    $: if (enabled) {
        search(content);
    } else {
        messages = undefined;
        loading = false;
        window.clearTimeout(timeoutId);
    }

    const LIMIT = 10;
    const DELAY = 500;

    let timeoutId: number | undefined;
    let messages: ReadonlyArray<MessageSummary> | undefined;
    let moreResults = false;

    const search = async (content: string) => {
        loading = true;
        window.clearTimeout(timeoutId);

        timeoutId = window.setTimeout(async () => {
            const params = $store.params;
            const query: Query = {
                courseid: params.courseid,
                labelid: params.tray == 'label' ? params.labelid : undefined,
                draft: params.tray == 'drafts' ? true : params.tray == 'sent' ? false : undefined,
                roles:
                    params.tray == 'inbox'
                        ? ['to', 'cc', 'bcc']
                        : params.tray == 'sent'
                        ? ['from']
                        : undefined,
                starred: params.tray == 'starred' ? true : undefined,
                deleted: params.tray == 'trash',
                content: content.trim(),
                stopid: $store.incrementalSearchStopId,
            };
            const request: SearchMessagesRequest = {
                methodname: 'search_messages',
                query,
                limit: LIMIT + 1,
            };
            let responses: any[];
            try {
                responses = await callServices([request]);
            } catch (error) {
                store.setError(error as ServiceError);
                loading = false;
                messages = undefined;
                return;
            }
            loading = false;
            messages = responses[0] as ReadonlyArray<MessageSummary>;
            moreResults = messages.length > LIMIT || Boolean($store.incrementalSearchStopId);
            messages = messages.slice(0, LIMIT);
        }, DELAY);
    };

    const clickHandler = (message: MessageSummary, i: number) => {
        return (event: MouseEvent) => {
            if (!message.draft) {
                event.preventDefault();
                handleClick(messageParams(message, i));
            }
        };
    };

    onDestroy(() => {
        window.clearTimeout(timeoutId);
    });

    $: messageParams = (message: MessageSummary, i: number) => {
        return {
            ...$store.params,
            messageid: message.id,
            offset: i,
            search: { content: content.trim() },
        };
    };

    $: allParams = {
        ...$store.params,
        messageid: undefined,
        offset: 0,
        search: { content: content.trim() },
    };
</script>

{#if enabled && messages != null}
    <div
        class="dropdown-menu dropdown-menu-left overflow-hidden show p-0 w-100"
        style="min-width: 18rem"
    >
        {#each messages as message, i}
            {#if i > 0}
                <div class="dropdown-divider my-0" />
            {/if}
            <a
                class="dropdown-item local-mail-incremental-search-item"
                class:font-weight-bold={message.unread}
                href={message.draft ? composeUrl(message.id) : viewUrl(messageParams(message, i))}
                on:click={clickHandler(message, i)}
            >
                <ListMessageSubject {store} {message} />
                <div class="local-mail-incremental-search-muted d-flex">
                    <ListMessageUsers {store} {message} />
                    <div title={message.fulltime} class="ml-auto">
                        {message.shorttime}
                    </div>
                </div>
            </a>
        {/each}
        {#if moreResults}
            <div class="dropdown-divider my-0" />
            <a
                class="dropdown-item py-2"
                href={viewUrl(allParams)}
                on:click|preventDefault={() => handleClick(allParams)}
            >
                {$store.strings.searchallmessages}
            </a>
        {:else if !messages.length}
            <div class="px-4 py-2 text-danger">
                {$store.strings.nomessagesfound}
            </div>
        {/if}
    </div>
{/if}

<style>
    .local-mail-incremental-search-muted {
        opacity: 0.6;
    }
</style>
