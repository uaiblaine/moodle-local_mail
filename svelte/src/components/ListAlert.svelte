<svelte:options immutable={true} />

<script lang="ts">
    import { fade } from 'svelte/transition';

    import type { Store } from '../lib/store';
    import { viewUrl } from '../lib/url';

    export let store: Store;

    $: recentParams = {
        type: $store.params.type,
        courseid: $store.params.courseid,
        labelid: $store.params.labelid,
    };
</script>

{#if !$store.list.messages.length && !$store.list.nextid}
    <div in:fade|local={{ delay: 400 }} class="alert alert-info">
        <div>
            {$store.strings.nomessagestoview}
        </div>
        {#if $store.list.totalcount > 0}
            <a
                class="btn btn-info text-white mt-3"
                href={viewUrl(recentParams)}
                on:click|preventDefault={() => store.navigate(recentParams)}
            >
                {$store.strings.showrecentmessages}
            </a>
        {/if}
    </div>
{/if}
