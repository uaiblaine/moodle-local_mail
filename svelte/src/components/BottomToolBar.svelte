<svelte:options immutable={true} />

<script lang="ts">
    import { onMount } from 'svelte';
    import type { Store } from '../lib/store';
    import DeleteButton from './DeleteButton.svelte';
    import DeleteForeverButton from './DeleteForeverButton.svelte';
    import LabelsButton from './LabelsButton.svelte';
    import MoreActionsButton from './MoreActionsButton.svelte';
    import PagingButtons from './PagingButtons.svelte';
    import RestoreButton from './RestoreButton.svelte';

    export let store: Store;

    let height: number;
    let placeholderNode: HTMLElement;
    let fixed = false;

    onMount(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    fixed =
                        entry.intersectionRatio < 1 &&
                        entry.boundingClientRect.top >= entry.boundingClientRect.height;
                }
            },
            { threshold: 1 },
        );
        observer.observe(placeholderNode);

        return () => observer.disconnect();
    });
</script>

<div class="row position-relative" bind:this={placeholderNode} style="height: {height}px;">
    <div
        role="toolbar"
        class="local-mail-toolbar d-flex w-100 py-3 border-top"
        class:position-fixed={fixed}
        class:position-absolute={!fixed}
        class:fixed-bottom={fixed}
        class:border-bottom={!fixed}
        class:bg-white={!fixed}
        class:bg-light={fixed}
        bind:offsetHeight={height}
    >
        <div class=" w-100 d-flex justify-content-around" role="group">
            {#if $store.params.tray == 'trash'}
                <RestoreButton {store} transparent={true} />
                <DeleteForeverButton {store} transparent={true} />
            {:else}
                <LabelsButton {store} transparent={true} dropup={true} />
                <DeleteButton {store} transparent={true} />
            {/if}
            <MoreActionsButton {store} transparent={true} dropup={true} />
            <PagingButtons {store} transparent={true} compact={true} />
        </div>
    </div>
</div>
