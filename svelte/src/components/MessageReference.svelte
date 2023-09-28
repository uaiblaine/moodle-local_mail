<!--
SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Reference, Strings } from '../lib/state';
    import MessageAttachments from './MessageAttachments.svelte';
    import UserPicture from './UserPicture.svelte';

    export let strings: Strings;
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
                    <UserPicture user={reference.sender} />
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
            <MessageAttachments {strings} message={reference} />
        {/if}
    </div>
</div>

<style global>
    .local-mail-message-reference-content {
        max-width: 60rem;
    }
</style>
