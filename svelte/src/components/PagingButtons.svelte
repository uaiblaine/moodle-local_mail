<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

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

<div class="text-truncate align-self-center ml-auto mr-3">
    {pagingText}
</div>

<div class="btn-group d-shrink-0" role="group">
    <button
        class="btn btn-secondary"
        disabled={!$store.prevPageParams}
        title={$store.strings.previouspage}
        on:click|preventDefault={() => store.navigate($store.prevPageParams)}
    >
        <i class="icon fa fa-w fa-chevron-left mx-0" aria-label={$store.strings.previouspage} />
    </button>
    <button
        class="btn btn-secondary"
        disabled={!$store.nextPageParams}
        title={$store.strings.nextpage}
        on:click|preventDefault={() => store.navigate($store.nextPageParams)}
    >
        <i class="icon fa fa-w fa-chevron-right mx-0" aria-label={$store.strings.nextpage} />
    </button>
</div>
