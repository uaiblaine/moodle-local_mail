<svelte:options immutable={true} />

<script lang="ts">
    import { truncate } from '../actions/truncate';
    import type { MessageSummary } from '../lib/services';
    import { ViewSize, type Store } from '../lib/store';

    export let store: Store;
    export let message: MessageSummary;

    $: users =
        $store.params.type == 'sent' || $store.params.type == 'drafts'
            ? message.recipients.length > 0
                ? message.recipients.map((user) => user.fullname)
                : [$store.strings.norecipient]
            : [message.sender.fullname];
</script>

<span
    use:truncate={users.join('\n')}
    class="w-100 flex-shrink-1 mr-2"
    class:local-mail-list-messaege-users-md={$store.viewSize >= ViewSize.MD}
>
    {users.join(', ')}
</span>

<style>
    .local-mail-list-messaege-users-md {
        min-width: 20%;
        max-width: 20%;
    }
</style>
