<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: pagingText =
        $store.list.messages.length == 0
            ? ''
            : $store.list.messages.length == 1
            ? replaceStringParams($store.strings.pagingsingle, {
                  index: $store.list.firstoffset + 1,
                  total: $store.list.totalcount,
              })
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
        disabled={!$store.prevParams}
        title={$store.strings.previouspage}
        on:click|preventDefault={() => store.navigate($store.prevParams)}
    >
        <i class="icon fa fa-w fa-chevron-left mx-0" aria-label={$store.strings.previouspage} />
    </button>
    <button
        class="btn btn-secondary"
        disabled={!$store.nextParams}
        title={$store.strings.nextpage}
        on:click|preventDefault={() => store.navigate($store.nextParams)}
    >
        <i class="icon fa fa-w fa-chevron-right mx-0" aria-label={$store.strings.nextpage} />
    </button>
</div>
