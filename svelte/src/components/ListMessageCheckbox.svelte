<svelte:options immutable={true} />

<script lang="ts">
    import type { MessageSummary } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let message: MessageSummary;

    $: checkClass = $store.selectedMessageIds.has(message.id) ? 'fa-check-square-o' : 'fa-square-o';
</script>

<button
    class="btn px-2"
    role="checkbox"
    aria-checked={Boolean($store.selectedMessageIds.has(message.id))}
    title={$store.strings.select}
    on:click={() => store.toggleSelected(message.id)}
>
    <i class="fa align-middle {checkClass}" />
</button>
