<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;

    $: iconClass =
        $store.selectedMessages.size == 0
            ? 'fa-square-o'
            : $store.selectedMessages.size < $store.listMessages.length
            ? 'fa-minus-square-o'
            : 'fa-check-square-o';
</script>

<div class="btn-group mr-3" role="group">
    <button
        class="btn btn-secondary dropdown-toggle"
        data-toggle="dropdown"
        aria-expanded="false"
        title={$store.strings.select}
    >
        <i class="fa fa-fw {iconClass}" />
    </button>
    <div class="dropdown-menu">
        <button class="dropdown-item" on:click={() => store.selectAll('all')}>
            {$store.strings.all}
        </button>
        <button class="dropdown-item" on:click={() => store.selectAll('none')}>
            {$store.strings.none}
        </button>
        <button class="dropdown-item" on:click={() => store.selectAll('read')}>
            {$store.strings.read}
        </button>
        <button class="dropdown-item" on:click={() => store.selectAll('unread')}>
            {$store.strings.unread}
        </button>
        <button class="dropdown-item" on:click={() => store.selectAll('starred')}>
            {$store.strings.starred}
        </button>
        <button class="dropdown-item" on:click={() => store.selectAll('unstarred')}>
            {$store.strings.unstarred}
        </button>
    </div>
</div>
