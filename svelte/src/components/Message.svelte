<svelte:options immutable={true} />

<script lang="ts">
    import Pill from './Pill.svelte';
    import { type Store } from '../lib/store';
    import type { Message } from '../lib/services';
    import MessageAttachments from './MessageAttachments.svelte';
    import MessageReference from './MessageReference.svelte';
    import { forwardeUrl, replyAllUrl, replyUrl, viewUrl } from '../lib/url';

    export let store: Store;
    export let message: Message;

    $: recipients = (type: string) => {
        return message.recipients.filter((user) => user.type == type);
    };

    $: starClass = message.starred ? 'fa-star text-warning' : 'fa-star-o';

    $: canReplyAll =
        [message.sender].concat(message.recipients).filter((u) => u.id != $store.userid).length > 1;
</script>

<div class="card">
    <div class="card-body">
        <h3 class="card-title mb-2 font-weight-normal">
            {message.subject}
        </h3>
        <div class="d-flex align-items-center mb-2">
            <div class="local-mail-message-labels">
                <Pill text={message.course.shortname} />
                {#each message.labels as label (label.id)}
                    <Pill text={label.name} color={label.color} />
                {/each}
            </div>
            <div class="ml-auto d-flex justify-content-end">
                <button
                    class="btn"
                    role="checkbox"
                    aria-checked={message.starred}
                    disabled={message.deleted}
                    title={message.starred
                        ? $store.strings.markasunstarred
                        : $store.strings.markasstarred}
                    on:click={() => store.setStarred([message.id], !message.starred)}
                >
                    <i class="fa {starClass}" />
                </button>
                <a href={replyUrl(message.id)} title={$store.strings.reply} class="btn">
                    <i class="fa fa-fw fa-reply" />
                </a>
                <a
                    href={replyAllUrl(message.id)}
                    class:disabled={!canReplyAll}
                    title={$store.strings.replyall}
                    class="btn"
                >
                    <i class="fa fa-fw fa-reply-all" />
                </a>
                <a href={forwardeUrl(message.id)} title={$store.strings.forward} class="btn">
                    <i class="fa fa-fw fa-share" />
                </a>
            </div>
        </div>
        <div class="local-mail-message-info d-flex">
            <div class="local-mail-message-sender-picture mr-3">
                <img
                    aria-hidden="true"
                    alt={message.sender.fullname}
                    src={message.sender.pictureurl}
                    width="35"
                    height="35"
                    class="rounded-circle"
                />
            </div>
            <div class="local-mail-message-users">
                <a href={message.sender.profileurl}>
                    {message.sender.fullname}
                </a>
                {#each ['to', 'cc', 'bcc'] as type}
                    {#if recipients(type).length}
                        <div class="local-mail-message-recipients mr-2">
                            <span class="local-mail-message-recipients-type">
                                {$store.strings[type]}:
                            </span>
                            {#each recipients(type) as user, i (user.id)}
                                {#if i > 0}, {/if}
                                <a href={user.profileurl}>{user.fullname}</a>
                            {/each}
                        </div>
                    {/if}
                {/each}
            </div>
            <div class="local-mail-message-time ml-auto">
                {message.fulltime}
            </div>
        </div>
        <hr />
        <div>
            {@html message.content}
        </div>
        {#if message.attachments.length > 0}
            <hr />
            <MessageAttachments {message} />
        {/if}
        <hr />
        <div class="mt-3 d-flex justify-content-end">
            <a href={replyUrl(message.id)} class="btn btn-primary mr-3">
                <i class="fa fa-fw fa-reply mr-2" />
                {$store.strings.reply}
            </a>
            <a
                href={replyAllUrl(message.id)}
                class:disabled={!canReplyAll}
                class="btn btn-primary mr-3"
            >
                <i class="fa fa-fw fa-reply-all mr-2" />
                {$store.strings.replyall}
            </a>
            <a href={forwardeUrl(message.id)} class="btn btn-primary">
                <i class="fa fa-fw fa-share mr-2" />
                {$store.strings.forward}
            </a>
        </div>
    </div>
</div>

{#if message.references.length > 0}
    <div class="alert alert-secondary mt-4 mb-4 text-center">
        {$store.strings.references}
    </div>
    {#each message.references as reference (reference.id)}
        <MessageReference {reference} />
    {/each}
{/if}

<style>
    .local-mail-message-users {
        line-height: 1.5;
    }
</style>
