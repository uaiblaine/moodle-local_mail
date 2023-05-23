<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { viewUrl } from '../lib/url';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: offset = $store.params.offset || 0;
    $: nextOffset = offset + $store.preferences.perpage;
    $: prevOffset = Math.max(0, offset - $store.preferences.perpage);
    $: nextParams = { ...$store.params, offset: nextOffset };
    $: prevParams = { ...$store.params, offset: prevOffset };
    $: nextDisabled = nextOffset >= $store.messageList.totalcount;
    $: prevDisabled = prevOffset == offset;
    $: pagingText =
        $store.messageList.messages.length == 0
            ? ''
            : $store.messageList.messages.length == 1
            ? replaceStringParams($store.strings.pagingsingle, {
                  index: offset + 1,
                  total: offset + $store.messageList.messages.length,
              })
            : replaceStringParams($store.strings.pagingmultiple, {
                  first: offset + 1,
                  last: offset + $store.messageList.messages.length,
                  total: $store.messageList.totalcount,
              });
</script>

<div class="text-truncate align-self-center ml-auto mr-3">
    {pagingText}
</div>

<div class="btn-group d-shrink-0" role="group">
    <a
        class="btn btn-secondary"
        class:disabled={prevDisabled}
        aria-disabled={prevDisabled}
        title={$store.strings.previouspage}
        href={viewUrl(prevParams)}
        on:click|preventDefault={() => store.navigate(prevParams)}
        ><i class="icon fa fa-w fa-chevron-left mx-0" aria-label={$store.strings.previouspage} /></a
    >
    <a
        class="btn btn-secondary"
        class:disabled={nextDisabled}
        aria-disabled={nextDisabled}
        title={$store.strings.nextpage}
        href={viewUrl(nextParams)}
        on:click|preventDefault={() => store.navigate(nextParams)}
    >
        <i class="icon fa fa-w fa-chevron-right mx-0" aria-label={$store.strings.nextpage} /></a
    >
</div>
