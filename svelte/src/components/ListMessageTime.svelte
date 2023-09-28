<!--
SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { ViewportSize, type MessageSummary } from '../lib/state';
    import type { Store } from '../lib/store';

    export let message: MessageSummary;
    export let store: Store;
</script>

<span
    class="local-mail-list-message-time d-shrink-0 text-nowrap text-right"
    class:local-mail-list-message-time-md={$store.viewportSize >= ViewportSize.MD}
    title={message.fulltime}
>
    {message.shorttime}
</span>

<style global>
    .local-mail-list-message-time-md {
        min-width: 5rem;
    }
</style>
