<!--
SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-back-button btn btn-secondary"
    title={$store.strings.messagelist}
    on:click={() => store.navigateToList()}
>
    <i class="fa fa fa-fw fa-arrow-left" />
</button>

<style global>
    .local-mail-back-button {
        margin-right: 13px;
    }
</style>
