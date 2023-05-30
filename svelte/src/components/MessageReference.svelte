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
        <div class="d-flex align-items-center">
            <div class="mr-3">
                <img
                    aria-hidden="true"
                    alt={reference.sender.fullname}
                    src={reference.sender.pictureurl}
                    width="35"
                    height="35"
                    class="rounded-circle"
                />
            </div>
            <div class="local-mail-message-reference-users">
                <a href={reference.sender.profileurl}>
                    {reference.sender.fullname}
                </a>
            </div>
            <div class="ml-auto">
                {reference.fulltime}
            </div>
        </div>
        <hr />
        <div class="local-mail-message-reference-content">
            {@html reference.content}
        </div>
        {#if reference.attachments.length > 0}
            <hr />
            <MessageAttachments message={reference} />
        {/if}
    </div>
</div>

<style>
    .local-mail-message-reference-users {
        line-height: 1.5;
    }

    .local-mail-message-reference-content {
        max-width: 60rem;
    }
</style>
