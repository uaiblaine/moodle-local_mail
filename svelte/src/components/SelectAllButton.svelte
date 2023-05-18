<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;

    $: selectCount = Object.values($store.selected).filter((selected) => selected).length;
    $: allSelected = selectCount == $store.list.messages.length;
    $: handleCheckClick = () => store.selectAll(selectCount == 0 ? 'all' : 'none');
    $: iconClass = selectCount > 0 ? 'fa-check-square-o' : 'fa-square-o';
</script>

<div class="btn-group">
    <button
        class="btn btn-secondary"
        role="checkbox"
        aria-checked={allSelected ? true : selectCount > 0 ? 'mixed' : false}
        aria-label={$store.strings.selectall}
        on:keydown={handleCheckClick}
        on:click={handleCheckClick}
    >
        <i class="icon fa fa-fw {iconClass} mx-0" />
    </button>
    <button
        type="button"
        class="btn btn-secondary dropdown-toggle dropdown-toggle-split"
        data-toggle="dropdown"
        aria-expanded="false"
    />
    <div class="dropdown-menu">
        <button class="dropdown-item" on:click={() => store.selectAll('all')}
            >{$store.strings.all}</button
        >
        <button class="dropdown-item" on:click={() => store.selectAll('none')}
            >{$store.strings.none}</button
        >
        <button class="dropdown-item" on:click={() => store.selectAll('read')}
            >{$store.strings.read}</button
        >
        <button class="dropdown-item" on:click={() => store.selectAll('unread')}
            >{$store.strings.unread}</button
        >
        <button class="dropdown-item" on:click={() => store.selectAll('starred')}
            >{$store.strings.starred}</button
        >
        <button class="dropdown-item" on:click={() => store.selectAll('unstarred')}
            >{$store.strings.unstarred}</button
        >
    </div>
</div>
