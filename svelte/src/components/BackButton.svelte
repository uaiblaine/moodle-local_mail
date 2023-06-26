<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-back-button btn btn-secondary"
    title={$store.strings.messagelist}
    on:click={() =>
        store.navigate({
            ...$store.params,
            messageid: undefined,
            offset:
                Math.floor(($store.messageOffset || 0) / $store.preferences.perpage) *
                $store.preferences.perpage,
        })}
>
    <i class="fa fa fa-fw fa-arrow-left" />
</button>

<style>
    .local-mail-back-button {
        margin-right: 13px;
    }
</style>
