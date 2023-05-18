<svelte:options immutable={true} />

<script lang="ts">
    import Pill from './Pill.svelte';
    import { truncate } from '../actions/truncate';
    import type { MessageListItem } from '../lib/services';
    import type { Store } from '../lib/store';
    import { composeUrl, viewUrl } from '../lib/url';

    export let store: Store;
    export let message: MessageListItem;

    $: params = { ...$store.params, messageid: message.id };
    $: users =
        $store.params.type == 'sent' || $store.params.type == 'drafts'
            ? message.recipients.map((user) => user.fullname)
            : [message.sender.fullname];
    $: checkClass = $store.selected[message.id] ? 'fa-check-square-o' : 'fa-square-o';
    $: starClass = message.starred ? 'fa-star text-warning' : 'fa-star-o';
    $: handleItemClick = (event: Event) => {
        if (!message.draft) {
            event.preventDefault();
            store.navigate(params);
        }
    };
</script>

<a
    class="local-mail-list-item list-group-item list-group-item-action d-flex align-items-center px-0 py-0"
    href={message.draft ? composeUrl(message.id) : viewUrl(params)}
    class:list-group-item-primary={$store.selected[message.id]}
    class:list-group-item-secondary={!message.unread && !$store.selected[message.id]}
    class:font-weight-bold={message.unread}
    on:click={handleItemClick}
>
    <button
        class="btn px-2 ml-1"
        role="checkbox"
        aria-label={$store.strings.select}
        aria-checked={!!$store.selected[message.id]}
        on:click={() => store.toggleSelected(message.id)}
    >
        <i class="icon fa mx-0 align-middle {checkClass}" />
    </button>
    <button
        class="btn px-2 mr-2"
        role="checkbox"
        aria-label={$store.strings.starred}
        aria-checked={message.starred}
        on:click={() => store.toggleSelected(message.id)}
    >
        <i class="icon fa mx-0 {starClass}" />
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
        {message.subject}
    </span>
    {#each message.labels as label}
        {#if $store.params.type != 'label' || $store.params.labelid != label.id}
            <Pill text={label.name} color={label.color} />
        {/if}
    {/each}
    {#if $store.params.type != 'course' || $store.params.courseid != message.course.id}
        <Pill text={message.course.shortname} />
    {/if}
    <span
        class="local-mail-list-item-time text-truncate d-shrink-0 text-right my-2 mr-3"
        title={message.fulltime}
    >
        {message.shorttime}
    </span>
</a>

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
