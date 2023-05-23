<svelte:options immutable={true} />

<script lang="ts">
    import { flip } from 'svelte/animate';
    import { fade, fly } from 'svelte/transition';

    import ListItem from './ListeItem.svelte';
    import type { Store } from '../lib/store';
    import { composeUrl, viewUrl } from '../lib/url';

    export let store: Store;

    $: key =
        $store.params.type +
        '-' +
        ($store.params.courseid || 0) +
        '-' +
        ($store.params.labelid || 0) +
        '-' +
        ($store.params.offset || 0);
</script>

{#key key}
    <div class="list-group">
        {#each $store.messageList.messages as message (message.id)}
            <a
                animate:flip={{ delay: 200, duration: 400 }}
                in:fade|local={{ delay: 400 }}
                out:fly|local={{ duration: 400, x: 100 }}
                class="local-mail-list-item list-group-item list-group-item-action d-flex align-items-center p-0"
                href={message.draft
                    ? composeUrl(message.id)
                    : viewUrl({ ...$store.params, messageid: message.id })}
                class:list-group-item-primary={$store.selectedMessages[message.id]}
                class:list-group-item-secondary={!message.unread && !$store.selectedMessages[message.id]}
                class:font-weight-bold={message.unread}
                on:click={(event) => {
                    if (!message.draft) {
                        event.preventDefault();
                        store.navigate($store.params);
                    }
                }}
            >
                <ListItem {store} {message} />
            </a>
        {/each}
        {#if $store.messageList.totalcount == 0}
            <div transition:fade|local={{ delay: 400 }} class="list-group-item">
                {$store.strings.nomessages}
            </div>
        {/if}
    </div>
{/key}
