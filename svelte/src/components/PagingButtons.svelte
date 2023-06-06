<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let transparent = false;
    export let compact = false;

    $: pagingText = $store.message
        ? replaceStringParams($store.strings.pagingsingle, {
              index: ($store.messageOffset || 0) + 1,
              total: $store.list.totalcount,
          })
        : $store.list.messages.length == 0
        ? ''
        : replaceStringParams($store.strings.pagingmultiple, {
              first: $store.list.firstoffset + 1,
              last: $store.list.lastoffset + 1,
              total: $store.list.totalcount,
          });
</script>

{#if !compact}
    <div class="text-truncate align-self-center mx-3">
        {pagingText}
    </div>
{/if}

<div
    class="local-mail-paging-buttons btn-group d-flex flex-shrink-1"
    class:btn-group={!compact}
    role="group"
>
    <button
        class="btn btn-secondary"
        class:btn-secondary={!transparent}
        disabled={!$store.prevPageParams}
        title={$store.strings.previouspage}
        on:click|preventDefault={() => store.navigate($store.prevPageParams)}
    >
        <i class="fa fa-w fa-chevron-left" aria-label={$store.strings.previouspage} />
    </button>
    {#if compact}
        <div class="text-truncate align-self-center mx-2">
            {pagingText}
        </div>
    {/if}
    <button
        class="btn"
        class:btn-secondary={!transparent}
        disabled={!$store.nextPageParams}
        title={$store.strings.nextpage}
        on:click|preventDefault={() => store.navigate($store.nextPageParams)}
    >
        <i class="fa fa-w fa-chevron-right" aria-label={$store.strings.nextpage} />
    </button>
</div>

<style>
    .local-mail-paging-buttons {
        min-width: 0;
    }
</style>
