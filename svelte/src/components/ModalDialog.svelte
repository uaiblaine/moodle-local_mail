<svelte:options immutable={true} />

<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { fade, fly } from 'svelte/transition';
    import { blur } from '../actions/blur';

    export let title: string;
    export let cancelText = '';
    export let confirmText = '';
    export let handleCancel: () => void;
    export let handleConfirm: (() => void) | undefined = undefined;
    export let confirmClass = 'btn-primary';
    export let confirmDisabled = false;

    let node: HTMLElement;

    onMount(() => {
        document.body.classList.add('modal-open');
        node.focus();
    });

    onDestroy(() => {
        document.body.classList.remove('modal-open');
    });

    const handleKey = (event: KeyboardEvent) => {
        if (event.key == 'Escape') {
            handleCancel();
        }
    };
</script>

<svelte:body on:keyup={handleKey} />

<div
    class="modal show"
    tabindex="-1"
    role="dialog"
    aria-label={title}
    aria-modal="true"
    bind:this={node}
    transition:fly|global={{ y: -100 }}
>
    <div class="modal-dialog">
        <div class="modal-content" use:blur={handleCancel}>
            <div class="modal-header">
                <h5 class="modal-title">
                    {title}
                </h5>
                <button type="button" class="close" aria-label={cancelText} on:click={handleCancel}>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <slot />
            </div>
            {#if cancelText || (confirmText && handleConfirm)}
                <div class="modal-footer">
                    {#if cancelText}
                        <button type="button" class="btn btn-secondary" on:click={handleCancel}>
                            {cancelText}
                        </button>
                    {/if}
                    {#if confirmText && handleConfirm}
                        <button
                            type="button"
                            class="btn {confirmClass}"
                            disabled={confirmDisabled}
                            on:click={handleConfirm}
                        >
                            {confirmText}
                        </button>
                    {/if}
                </div>
            {/if}
        </div>
    </div>
</div>

<div class="modal-backdrop show" transition:fade|global />
