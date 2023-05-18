<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import ListItem from './ListeItem.svelte';

    export let store: Store;
</script>

<div class="list-group">
    {#each $store.list.messages as message}
        <ListItem {store} {message} />
    {/each}
</div>
