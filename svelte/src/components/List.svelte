<svelte:options immutable={true} />

<script lang="ts">
    import { flip } from 'svelte/animate';
    import { fade } from 'svelte/transition';

    import ListItem from './ListeItem.svelte';
    import type { Store } from '../lib/store';
    import { composeUrl, viewUrl } from '../lib/url';

    export let store: Store;

    $: key = [
        $store.params.type,
        $store.params.courseid || 0,
        $store.params.labelid || 0,
        $store.params.beforeid || 0,
    ].join(':');

    $: recentParams = {
        type: $store.params.type,
        courseid: $store.params.courseid,
        labelid: $store.params.labelid,
    };
</script>

{#key key}
    <div class="list-group">
        {#each $store.list.messages as message (message.id)}
            <a
                animate:flip={{ delay: 200, duration: 400 }}
                in:fade|local={{ delay: 400 }}
                out:fade|local={{ duration: 400 }}
                class="local-mail-list-item list-group-item list-group-item-action d-flex align-items-center p-0"
                href={message.draft
                    ? composeUrl(message.id)
                    : viewUrl({ ...$store.params, messageid: message.id })}
                class:list-group-item-primary={$store.selectedIds.has(message.id)}
                class:list-group-item-secondary={!message.unread &&
                    !$store.selectedIds.has(message.id)}
                class:font-weight-bold={message.unread}
                on:click={(event) => {
                    if (!message.draft) {
                        event.preventDefault();
                        // TODO: View message
                    }
                }}
            >
                <ListItem {store} {message} />
            </a>
        {/each}
        {#if !$store.list.messages.length && !$store.list.nextid}
            <div in:fade|local={{ delay: 400 }} class="alert alert-info">
                <div>
                    {$store.strings.nomessagestoview}
                </div>
                {#if $store.list.totalcount > 0}
                    <a
                        class="btn btn-info text-white mt-3"
                        href={viewUrl(recentParams)}
                        on:click|preventDefault={() => store.navigate(recentParams)}
                    >
                        {$store.strings.showrecentmessages}
                    </a>
                {/if}
            </div>
        {/if}
    </div>
{/key}

<style>
    .local-mail-list-item {
        color: var(--dark) !important;
    }
</style>
