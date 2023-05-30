<svelte:options immutable={true} />

<script lang="ts">
    import { flip } from 'svelte/animate';
    import { fade } from 'svelte/transition';

    import Pill from './Pill.svelte';
    import { truncate } from '../actions/truncate';
    import type { Store } from '../lib/store';
    import type { MessageSummary } from '../lib/services';
    import { composeUrl, viewUrl } from '../lib/url';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: recentParams = {
        type: $store.params.type,
        courseid: $store.params.courseid,
        labelid: $store.params.labelid,
    };

    const users = (message: MessageSummary): string[] => {
        return $store.params.type == 'sent' || $store.params.type == 'drafts'
            ? message.recipients.length > 0
                ? message.recipients.map((user) => user.fullname)
                : [$store.strings.norecipient]
            : [message.sender.fullname];
    };

    const checkClass = (message: MessageSummary): string => {
        return $store.selectedIds.has(message.id) ? 'fa-check-square-o' : 'fa-square-o';
    };

    const starClass = (message: MessageSummary): string => {
        return message.starred ? 'fa-star text-warning' : 'fa-star-o';
    };

    const clickHander = (message: MessageSummary) => {
        return (event: MouseEvent) => {
            // Check if the event target or its parent is a button,
            // which would mean that the select or star button is being clicked.
            if ((event.target as HTMLElement).matches('button, button > *')) {
                event.preventDefault();
            } else if (!message.draft) {
                event.preventDefault();
                store.navigate({ ...$store.params, messageid: message.id });
            }
        };
    };
</script>

{#key $store.listKey}
    <div class="list-group">
        {#each $store.list.messages as message (message.id)}
            <a
                animate:flip={{ delay: 400, duration: 400 }}
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
                on:click={clickHander(message)}
            >
                <button
                    class="btn px-2 ml-1"
                    role="checkbox"
                    aria-checked={Boolean($store.selectedIds.has(message.id))}
                    title={$store.strings.select}
                    on:click={() => store.toggleSelected(message.id)}
                >
                    <i class="fa align-middle {checkClass(message)}" />
                </button>
                <button
                    class="btn px-2 mr-2"
                    role="checkbox"
                    aria-checked={message.starred}
                    disabled={message.deleted}
                    title={message.deleted
                        ? $store.strings[message.starred ? 'starred' : 'unstarred']
                        : $store.strings[message.starred ? 'markasunstarred' : 'markasstarred']}
                    on:click={() => store.setStarred([message.id], !message.starred)}
                >
                    <i class="fa {starClass(message)}" />
                </button>
                <span
                    use:truncate={users(message).join('\n')}
                    class="local-mail-list-item-users my-2 mr-2"
                >
                    {users(message).join(', ')}
                </span>
                {#if message.draft}
                    <span class="local-mail-list-item-draft my-2 mr-2 text-danger">
                        {$store.strings.draft}
                    </span>
                {/if}
                <span
                    use:truncate={message.subject}
                    class="local-mail-list-item-subject d-grow-1 my-2 mr-2"
                >
                    {message.subject || $store.strings.nosubject}
                </span>
                {#each message.labels as label (label.id)}
                    {#if $store.params.type != 'label' || $store.params.labelid != label.id}
                        <Pill text={label.name} color={label.color} />
                    {/if}
                {/each}
                {#if $store.params.type != 'course' || $store.params.courseid != message.course.id}
                    <Pill text={message.course.shortname} />
                {/if}
                <span
                    class="local-mail-list-item-attachments d-shrink-0 my-2 mr-2"
                    title={message.numattachments
                        ? replaceStringParams($store.strings.attachnumber, message.numattachments)
                        : ''}
                    aria-hidden={message.numattachments == 0}
                >
                    <i class="fa fa-fw {message.numattachments ? 'fa-paperclip' : ''}" />
                </span>
                <span
                    class="local-mail-list-item-time text-truncate d-shrink-1 text-right my-2 mr-3"
                    title={message.fulltime}
                >
                    {message.shorttime}
                </span>
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
    .local-mail-list-item-users {
        min-width: 20%;
    }
    .local-mail-list-item-subject {
        width: 100%;
    }
    .local-mail-list-item-time {
        min-width: 5rem;
    }
</style>
