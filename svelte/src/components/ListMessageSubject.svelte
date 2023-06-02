<svelte:options immutable={true} />

<script lang="ts">
    import { truncate } from '../actions/truncate';
    import type { MessageSummary } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let message: MessageSummary;
</script>

<span use:truncate={message.subject || $store.strings.nosubject} class="w-100 d-grow-1 mr-2">
    {#if message.draft}
        <span class="mr-2 text-danger">
            {$store.strings.draft}
        </span>
    {/if}
    {message.subject || $store.strings.nosubject}
</span>
