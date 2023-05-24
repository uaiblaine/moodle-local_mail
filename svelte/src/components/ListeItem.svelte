<svelte:options immutable={true} />

<script lang="ts">
    import Pill from './Pill.svelte';
    import { truncate } from '../actions/truncate';
    import type { MessageListItem } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let message: MessageListItem;

    $: users =
        $store.params.type == 'sent' || $store.params.type == 'drafts'
            ? message.recipients.length > 0
                ? message.recipients.map((user) => user.fullname)
                : [$store.strings.norecipient]
            : [message.sender.fullname];
    $: checkClass = $store.selectedIds.has(message.id) ? 'fa-check-square-o' : 'fa-square-o';
    $: starClass = message.starred ? 'fa-star text-warning' : 'fa-star-o';
</script>

<button
    class="btn px-2 ml-1"
    role="checkbox"
    aria-checked={Boolean($store.selectedIds.has(message.id))}
    title={$store.strings.select}
    on:click|preventDefault|stopPropagation={() => store.toggleSelected(message.id)}
>
    <i class="fa align-middle {checkClass}" />
</button>
<button
    class="btn px-2 mr-2"
    role="checkbox"
    aria-checked={message.starred}
    disabled={message.deleted}
    title={message.starred ? $store.strings.markasunstarred : $store.strings.markasstarred}
    on:click|preventDefault|stopPropagation={() => store.setStarred([message.id], !message.starred)}
>
    <i class="fa {starClass}" />
</button>
<span use:truncate={users.join('\n')} class="local-mail-list-item-users my-2 mr-2">
    {users.join(', ')}
</span>
{#if message.draft}
    <span class="local-mail-list-item-draft my-2 mr-2 text-danger">
        {$store.strings.draft}
    </span>
{/if}
<span use:truncate={message.subject} class="local-mail-list-item-subject d-grow-1 my-2 mr-2">
    {message.subject || $store.strings.nosubject}
</span>
{#each message.labels as label}
    {#if $store.params.type != 'label' || $store.params.labelid != label.id}
        <Pill text={label.name} color={label.color} dimmed={!message.unread} />
    {/if}
{/each}
{#if $store.params.type != 'course' || $store.params.courseid != message.course.id}
    <Pill text={message.course.shortname} dimmed={!message.unread} />
{/if}
<span
    class="local-mail-list-item-time text-truncate d-shrink-0 text-right my-2 mr-3"
    title={message.fulltime}
>
    {message.shorttime}
</span>

<style>
    .local-mail-list-item-users {
        min-width: 20%;
    }
    .local-mail-list-item-subject {
        width: 100%;
    }
    .local-mail-list-item-time {
        min-width: 7rem;
    }
</style>
