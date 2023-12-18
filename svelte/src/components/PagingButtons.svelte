<!--
SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let transparent = false;
    export let compact = false;

    $: hasNext =
        $store.nextMessageId ||
        (!$store.params.search &&
            ($store.params.offset || 0) + $store.preferences.perpage < $store.totalCount);

    $: hasPrev = $store.prevMessageId || (!$store.params.search && ($store.params.offset || 0));

    $: nextParams = hasNext
        ? $store.message
            ? {
                  ...$store.params,
                  messageid: $store.nextMessageId,
                  offset: ($store.params.offset || 0) + 1,
              }
            : {
                  ...$store.params,
                  messageid: undefined,
                  offset: ($store.params.offset || 0) + $store.preferences.perpage,
                  search: $store.params.search
                      ? {
                            ...$store.params.search,
                            startid: $store.listMessages[$store.listMessages.length - 1]?.id,
                            reverse: false,
                        }
                      : undefined,
              }
        : undefined;

    $: prevParams = hasPrev
        ? $store.message
            ? {
                  ...$store.params,
                  messageid: $store.prevMessageId,
                  offset: Math.max(0, ($store.params.offset || 0) - 1),
              }
            : {
                  ...$store.params,
                  messageid: undefined,
                  offset: Math.max(0, ($store.params.offset || 0) - $store.preferences.perpage),
                  search: $store.params.search
                      ? {
                            ...$store.params.search,
                            startid: $store.listMessages[0].id,
                            reverse: true,
                        }
                      : undefined,
              }
        : undefined;

    $: pagingText = $store.message
        ? $store.params.search
            ? ($store.params.offset || 0) + 1
            : replaceStringParams($store.strings.pagingsingle, {
                  index: ($store.messageOffset || 0) + 1,
                  total: $store.totalCount,
              })
        : $store.listMessages.length == 0
          ? ''
          : $store.params.search
            ? replaceStringParams($store.strings.pagingrange, {
                  first: ($store.params.offset || 0) + 1,
                  last: ($store.params.offset || 0) + $store.listMessages.length,
              })
            : replaceStringParams($store.strings.pagingrangetotal, {
                  first: ($store.params.offset || 0) + 1,
                  last: ($store.params.offset || 0) + $store.listMessages.length,
                  total: $store.totalCount,
              });
</script>

<div class="local-mail-paging-buttons d-flex" class:ml-auto={!compact}>
    {#if !compact}
        <div class="align-self-center text-nowrap">
            {pagingText}
        </div>
    {/if}

    <div class="btn-group d-flex" class:btn-group={!compact} role="group">
        <button
            type="button"
            class="btn btn-secondary"
            class:btn-secondary={!transparent}
            disabled={!prevParams}
            title={$store.strings[$store.message ? 'previousmessage' : 'previouspage']}
            on:click|preventDefault={() => store.navigate(prevParams)}
        >
            <i class="fa fa-w fa-chevron-left" aria-label={$store.strings.previouspage} />
        </button>
        {#if compact}
            <div class="text-truncate align-self-center mx-2">
                {pagingText}
            </div>
        {/if}
        <button
            type="button"
            class="btn"
            class:btn-secondary={!transparent}
            disabled={!nextParams}
            title={$store.strings[$store.message ? 'nextmessage' : 'nextpage']}
            on:click|preventDefault={() => store.navigate(nextParams)}
        >
            <i class="fa fa-w fa-chevron-right" aria-label={$store.strings.nextpage} />
        </button>
    </div>
</div>

<style global>
    .local-mail-paging-buttons {
        min-width: 0;
        column-gap: 1rem;
    }
</style>
