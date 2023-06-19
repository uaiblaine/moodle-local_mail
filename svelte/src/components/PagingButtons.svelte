<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let transparent = false;
    export let compact = false;

    $: nextParams = $store.message
        ? $store.nextMessageId
            ? {
                  ...$store.params,
                  messageid: $store.nextMessageId,
              }
            : undefined
        : $store.params.search
        ? $store.nextMessageId
            ? {
                  ...$store.params,
                  search: {
                      ...$store.params.search,
                      startid: $store.listMessages[$store.listMessages.length - 1]?.id,
                      reverse: false,
                  },
              }
            : undefined
        : ($store.params.offset || 0) + $store.preferences.perpage < $store.totalCount
        ? {
              ...$store.params,
              messageid: undefined,

              offset: ($store.params.offset || 0) + $store.preferences.perpage,
          }
        : undefined;

    $: prevParams = $store.message
        ? $store.prevMessageId
            ? {
                  ...$store.params,
                  messageid: $store.prevMessageId,
                  offset: undefined,
              }
            : undefined
        : $store.params.search
        ? $store.prevMessageId
            ? {
                  ...$store.params,
                  search: {
                      ...$store.params.search,
                      startid: $store.listMessages[0].id,
                      reverse: true,
                  },
              }
            : undefined
        : ($store.params.offset || 0) > 0
        ? {
              ...$store.params,
              messageid: undefined,
              offset: Math.max(0, ($store.params.offset || 0) - $store.preferences.perpage),
          }
        : undefined;

    $: pagingText = $store.message
        ? replaceStringParams($store.strings.pagingsingle, {
              index: ($store.messageOffset || 0) + 1,
              total: $store.totalCount,
          })
        : $store.listMessages.length == 0
        ? ''
        : replaceStringParams($store.strings.pagingmultiple, {
              first: ($store.params.offset || 0) + 1,
              last: ($store.params.offset || 0) + $store.listMessages.length,
              total: $store.totalCount,
          });
</script>

{#if !compact && !$store.params.search}
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
        disabled={!prevParams}
        title={$store.strings.previouspage}
        on:click|preventDefault={() => store.navigate(prevParams)}
    >
        <i class="fa fa-w fa-chevron-left" aria-label={$store.strings.previouspage} />
    </button>
    {#if compact && !$store.params.search}
        <div class="text-truncate align-self-center mx-2">
            {pagingText}
        </div>
    {/if}
    <button
        class="btn"
        class:btn-secondary={!transparent}
        disabled={!nextParams}
        title={$store.strings.nextpage}
        on:click|preventDefault={() => store.navigate(nextParams)}
    >
        <i class="fa fa-w fa-chevron-right" aria-label={$store.strings.nextpage} />
    </button>
</div>

<style>
    .local-mail-paging-buttons {
        min-width: 0;
    }
</style>
