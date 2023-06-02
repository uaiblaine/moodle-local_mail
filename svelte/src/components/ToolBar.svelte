<svelte:options immutable={true} />

<script lang="ts">
    import { onMount } from 'svelte';
    import DeleteButton from './DeleteButton.svelte';
    import DeleteForeverButton from './DeleteForeverButton.svelte';
    import LabelsButton from './LabelsButton.svelte';
    import MoreActionsButton from './MoreActionsButton.svelte';
    import PagingButtons from './PagingButtons.svelte';
    import RestoreButton from './RestoreButton.svelte';
    import { type Store } from '../lib/store';

    export let store: Store;
    export let fixed = false;

    let height: number;
    let placeholderNode: HTMLElement;
    let placeholderVisible = false;

    onMount(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    placeholderVisible = entry.intersectionRatio >= 1;
                }
            },
            { threshold: 1 },
        );
        observer.observe(placeholderNode);

        return () => observer.disconnect();
    });
</script>

<div class="w-100 position-relative" bind:this={placeholderNode} style="height: {height}px;">
    <div
        role="toolbar"
        class="local-mail-toolbar d-flex w-100"
        class:position-fixed={fixed && !placeholderVisible}
        class:position-absolute={fixed && placeholderVisible}
        class:fixed-bottom={fixed && !placeholderVisible}
        class:bg-white={fixed}
        class:p-2={fixed}
        class:border-top={fixed}
        class:border-bottom={fixed && placeholderVisible}
        bind:offsetHeight={height}
    >
        {#if fixed}
            <div class="w-100 d-flex justify-content-around" role="group">
                {#if $store.params.type == 'trash'}
                    <RestoreButton {store} {fixed} />
                    <DeleteForeverButton {store} {fixed} />
                {:else}
                    <LabelsButton {store} {fixed} />
                    <DeleteButton {store} {fixed} />
                {/if}
                <MoreActionsButton {store} {fixed} />
                <PagingButtons {store} {fixed} />
            </div>
        {:else}
            <div class="btn-group mr-auto" role="group">
                {#if $store.params.type == 'trash'}
                    <RestoreButton {store} {fixed} />
                    <DeleteForeverButton {store} {fixed} />
                {:else}
                    <LabelsButton {store} {fixed} />
                    <DeleteButton {store} {fixed} />
                {/if}
                <MoreActionsButton {store} {fixed} />
            </div>
            <PagingButtons {store} {fixed} />
        {/if}
    </div>
</div>
