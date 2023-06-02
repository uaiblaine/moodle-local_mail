<svelte:options immutable={true} />

<script lang="ts">
    import CourseBadge from './CourseBadge.svelte';
    import LabelBadge from './LabelBadge.svelte';
    import type { Message } from '../lib/services';
    import { ViewSize, type Store } from '../lib/store';
    import { forwardeUrl as forwardUrl, replyAllUrl, replyUrl } from '../lib/url';

    export let store: Store;
    export let message: Message;
    export let canReplyAll: boolean;

    $: starClass = message.starred ? 'fa-star text-warning' : 'fa-star-o';
</script>

<button
    class="btn py-2 border-0"
    role="checkbox"
    aria-checked={message.starred}
    disabled={message.deleted}
    title={message.starred ? $store.strings.markasunstarred : $store.strings.markasstarred}
    on:click={() => store.setStarred([message.id], !message.starred)}
>
    <i class="fa {starClass}" />
</button>

{#if $store.viewSize < ViewSize.SM}
    <div class="dropdown">
        <button
            type="button"
            class="btn"
            data-toggle="dropdown"
            aria-expanded="false"
            title={$store.strings.moreactions}
        >
            <i class="fa fa-fw fa-ellipsis-v align-middle" />
        </button>
        <div class="dropdown-menu dropdown-menu-right">
            <a type="button" class="dropdown-item" href="{replyUrl(message.id)}}">
                <i class="fa fa-fw fa-reply" aria-hidden="true" />
                {$store.strings.reply}
            </a>
            <a
                type="button"
                class="dropdown-item"
                href="{replyAllUrl(message.id)}}"
                class:disabled={!canReplyAll}
            >
                <i class="fa fa-fw fa-reply-all" aria-hidden="true" />
                {$store.strings.replyall}
            </a>
            <a type="button" class="dropdown-item" href="{forwardUrl(message.id)}}">
                <i class="fa fa-fw fa-share" aria-hidden="true" />
                {$store.strings.forward}
            </a>
        </div>
    </div>
{/if}

{#if $store.viewSize >= ViewSize.SM}
    <a href={replyUrl(message.id)} title={$store.strings.reply} class="btn py-2 border-0">
        <i class="fa fa-fw fa-reply" aria-hidden="true" />
    </a>
    <a
        href={replyAllUrl(message.id)}
        class:disabled={!canReplyAll}
        title={$store.strings.replyall}
        class="btn py-2 border-0"
    >
        <i class="fa fa-fw fa-reply-all" aria-hidden="true" />
    </a>
    <a href={forwardUrl(message.id)} title={$store.strings.forward} class="btn py-2 border-0">
        <i class="fa fa-fw fa-share" aria-hidden="true" />
    </a>
{/if}
