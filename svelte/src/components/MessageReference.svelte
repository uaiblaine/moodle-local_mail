<svelte:options immutable={true} />

<script lang="ts">
    import type { Reference } from '../lib/services';
    import MessageAttachments from './MessageAttachments.svelte';

    export let reference: Reference;
</script>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title font-weight-normal">
            {reference.subject}
        </h5>
        <div class="local-mail-message-info d-flex align-items-center">
            <div class="local-mail-message-sender-picture mr-3">
                <img
                    aria-hidden="true"
                    alt={reference.sender.fullname}
                    src={reference.sender.pictureurl}
                    width="35"
                    height="35"
                    class="rounded-circle"
                />
            </div>
            <div class="local-mail-message-users">
                <a href={reference.sender.profileurl}>
                    {reference.sender.fullname}
                </a>
            </div>
            <div class="local-mail-message-time ml-auto">
                {reference.fulltime}
            </div>
        </div>
        <hr />
        <div>
            {@html reference.content}
        </div>
        {#if reference.attachments.length > 0}
            <hr />
            <MessageAttachments message={reference} />
        {/if}
    </div>
</div>

<style>
    .local-mail-message-users {
        line-height: 1.5;
    }
</style>
