<svelte:options immutable={true} />

<script lang="ts">
    import type { Reference } from '../lib/services';
    import MessageAttachments from './MessageAttachments.svelte';

    export let reference: Reference;
</script>

<div class="card mb-4">
    <div class="card-body p-3 px-xl-4">
        <h5 class="h5 card-title mb-3">
            {reference.subject}
        </h5>
        <div class="d-sm-flex mb-n1">
            <div class="d-flex mb-3 mb-sm-0">
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
                <div class="mt-1">
                    <a href={reference.sender.profileurl}>
                        {reference.sender.fullname}
                    </a>
                </div>
            </div>
            <div class="mt-1 ml-auto">
                {reference.fulltime}
            </div>
        </div>
        <hr />
        <div class="local-mail-message-reference-content">
            <!-- eslint-disable-next-line svelte/no-at-html-tags -->
            {@html reference.content}
        </div>
        {#if reference.attachments.length > 0}
            <hr />
            <MessageAttachments message={reference} />
        {/if}
    </div>
</div>

<style>
    .local-mail-message-reference-content {
        max-width: 60rem;
    }
</style>
