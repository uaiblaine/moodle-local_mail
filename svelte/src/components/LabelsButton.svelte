<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import LabelModal from './LabelModal.svelte';

    export let store: Store;
    export let transparent = false;
    export let dropup = false;

    let selectedLabels: ReadonlyMap<number, 'false' | 'mixed' | 'true'> = new Map();

    $: selectedLabels = new Map(
        $store.menu.labels.map((label) => {
            const messages = $store.list.messages.filter(
                (message) =>
                    $store.targetMessageIds.has(message.id) &&
                    message.labels.some((messageLabel) => messageLabel.id == label.id),
            );
            return [
                label.id,
                messages.length == 0
                    ? 'false'
                    : messages.length < $store.targetMessageIds.size
                    ? 'mixed'
                    : 'true',
            ];
        }),
    );

    $: applyEnabled = Array.from(selectedLabels.entries()).some(([labelid, selected]) =>
        $store.list.messages.some(
            (message) =>
                $store.targetMessageIds.has(message.id) &&
                ((selected == 'true' && message.labels.every((label) => label.id != labelid)) ||
                    (selected == 'false' && message.labels.some((label) => label.id == labelid))),
        ),
    );

    $: labelIconClass = (labelid: number) => {
        if (selectedLabels.get(labelid) == 'false') {
            return 'fa-square-o';
        } else if (selectedLabels.get(labelid) == 'mixed') {
            return 'fa-minus-square-o';
        } else {
            return 'fa-check-square-o';
        }
    };

    const toggleLabel = (labelid: number) => {
        selectedLabels = new Map(
            Array.from(selectedLabels.entries()).map(([id, selected]) => [
                id,
                id == labelid ? (selected == 'true' ? 'false' : 'true') : selected,
            ]),
        );
    };

    const applyLabels = () => {
        store.setLabels(
            Array.from($store.targetMessageIds.values()),
            Array.from(selectedLabels.keys()).filter((id) => selectedLabels.get(id) == 'true'),
            Array.from(selectedLabels.keys()).filter((id) => selectedLabels.get(id) == 'false'),
        );
    };
</script>

<div class="btn-group" class:dropup role="group">
    <button
        type="button"
        class="local-mail-action-label-button btn dropdown-toggle"
        class:btn-secondary={!transparent}
        class:disabled={!$store.targetMessageIds.size}
        disabled={!$store.targetMessageIds.size}
        data-toggle="dropdown"
        aria-expanded="false"
        title={$store.strings.labels}
    >
        <i class="fa fa-fw fa-tag" />
    </button>
    <div class="dropdown-menu">
        {#each $store.menu.labels as label (label.id)}
            <button
                type="button"
                class="dropdown-item local-mail-action-label-button-item"
                on:click|stopPropagation={() => toggleLabel(label.id)}
            >
                <i class="fa fa-fw {labelIconClass(label.id)}" />
                {label.name}
            </button>
        {/each}
        {#if $store.menu.labels.length > 0}
            <div class="dropdown-divider" />
        {/if}
        {#if applyEnabled}
            <button
                type="button"
                class="dropdown-item local-mail-action-label-button-item"
                on:click={() => applyLabels()}
            >
                {$store.strings.applychanges}
            </button>
        {:else}
            <button
                type="button"
                class="dropdown-item local-mail-action-label-button-item"
                data-toggle="modal"
                data-target="#local-mail-label-modal-new"
            >
                {$store.strings.newlabel}
            </button>
        {/if}
    </div>
    <LabelModal {store} />
</div>

<style>
    .local-mail-action-label-button::after {
        display: none;
    }
</style>
