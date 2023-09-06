<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import ModalDialog from './ModalDialog.svelte';

    export let store: Store;
</script>

{#if $store.error}
    <ModalDialog title={$store.strings.error} onCancel={() => store.setError()}>
        <p>{$store.error?.message}</p>
        {#if $store.error?.debuginfo}
            <p>{$store.error?.debuginfo}</p>
        {/if}
        {#if $store.error?.stacktrace}
            <pre>{$store.error?.stacktrace}</pre>
        {/if}
    </ModalDialog>
{/if}
