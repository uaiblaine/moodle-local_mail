<svelte:options immutable={true} />

<script lang="ts">
    import { ViewportSize, type MessageSummary } from '../lib/state';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let message: MessageSummary;
</script>

{#if message.numattachments > 0 || $store.viewportSize >= ViewportSize.MD}
    <span
        class="-shrink-0 mr-2"
        title={message.numattachments > 0
            ? replaceStringParams($store.strings.attachnumber, message.numattachments)
            : ''}
        aria-hidden={message.numattachments == 0}
    >
        <i class="fa fa-fw {message.numattachments > 0 ? 'fa-paperclip' : ''}" aria-hidden={true} />
    </span>
{/if}
