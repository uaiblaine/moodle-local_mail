<svelte:options immutable={true} />

<script lang="ts">
    import type { MessageSummary } from '../lib/services';
    import { ViewSize, type Store } from '../lib/store';

    export let message: MessageSummary;
    export let store: Store;
</script>

<span
    class="local-mail-list-message-time d-shrink-0 text-nowrap text-right"
    class:local-mail-list-message-time-md={$store.viewSize >= ViewSize.MD}
    title={message.fulltime}
>
    {message.shorttime}
</span>

<style>
    .local-mail-list-message-time-md {
        min-width: 5rem;
    }
</style>
