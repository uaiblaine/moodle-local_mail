<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-back-button btn btn-secondary"
    title={$store.strings.back}
    on:click={() => store.navigate({ ...$store.params, messageid: undefined })}
>
    <i class="fa fa fa-fw fa-arrow-left" />
</button>

<style>
    .local-mail-back-button {
        margin-right: calc(1rem + 13px);
    }
</style>
