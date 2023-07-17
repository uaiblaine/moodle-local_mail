<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-action-send btn btn-primary flex-grow-0"
    title={$store.strings.send}
    on:click={() => store.sendMessage()}
>
    <i class="fa fa-fw fa-send mr-2" />
    {$store.strings.send}
</button>
