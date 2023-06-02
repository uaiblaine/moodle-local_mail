<svelte:options immutable={true} />

<script lang="ts">
    import type { Message } from '../lib/services';
    import { type Store } from '../lib/store';
    import { forwardeUrl, replyAllUrl, replyUrl } from '../lib/url';

    export let store: Store;
    export let message: Message;
    export let canReplyAll: boolean;
</script>

<a href={replyUrl(message.id)} class="btn btn-primary mr-3 mb-3">
    <i class="fa fa-fw fa-reply mr-2" />
    {$store.strings.reply}
</a>
<a
    href={replyAllUrl(message.id)}
    class:disabled={!canReplyAll}
    class="btn mr-3 mb-3"
    class:btn-primary={canReplyAll}
    class:btn-secondary={!canReplyAll}
>
    <i class="fa fa-fw fa-reply-all mr-2" />
    {$store.strings.replyall}
</a>
<a href={forwardeUrl(message.id)} class="btn btn-primary mr-3 mb-3">
    <i class="fa fa-fw fa-share mr-2" />
    {$store.strings.forward}
</a>
