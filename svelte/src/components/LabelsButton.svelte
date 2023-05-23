<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';

    export let store: Store;

    let selectedLabels: ReadonlyMap<number, 'false' | 'mixed' | 'true'> = new Map();

    $: selectedLabels = new Map(
        $store.menu.labels.map((label) => {
            const messages = Array.from($store.selectedMessages.values()).filter((message) =>
                message.labels.some((messageLabel) => messageLabel.id == label.id),
            );
            return [
                label.id,
                messages.length == 0
                    ? 'false'
                    : messages.length < $store.selectedMessages.size
                    ? 'mixed'
                    : 'true',
            ];
        }),
    );

    $: applyEnabled = Array.from(selectedLabels.entries()).some(([labelid, selected]) =>
        Array.from($store.selectedMessages.values()).some(
            (message) =>
                (selected == 'true' && message.labels.every((label) => label.id != labelid)) ||
                (selected == 'false' && message.labels.some((label) => label.id == labelid)),
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

    $: toggleLabel = (labelid: number) => {
        selectedLabels = new Map(
            Array.from(selectedLabels.entries()).map(([id, selected]) => [
                id,
                id == labelid ? (selected == 'true' ? 'false' : 'true') : selected,
            ]),
        );
    };

    $: applyLabels = () => {
        store.setLabels(
            Array.from($store.selectedMessages.keys()),
            Array.from(selectedLabels.keys()).filter((id) => selectedLabels.get(id) == 'true'),
            Array.from(selectedLabels.keys()).filter((id) => selectedLabels.get(id) == 'false'),
        );
    };
</script>

<div class="btn-group" role="group">
    <button
        type="button"
        class="local-mail-action-label-button btn btn-secondary dropdown-toggle"
        class:disabled={!$store.selectedMessages.size}
        disabled={!$store.selectedMessages.size}
        data-toggle="dropdown"
        aria-expanded="false"
        title={$store.strings.labels}
    >
        <i class="fa fa-fw fa-tag" />
    </button>
    <div class="dropdown-menu">
        {#each $store.menu.labels as label}
            <button
                type="button"
                class="dropdown-item local-mail-action-label-button-item"
                on:click|stopPropagation={() => toggleLabel(label.id)}
            >
                <i class="fa fa-fw {labelIconClass(label.id)}" />
                {label.name}
            </button>
        {/each}
        <div class="dropdown-divider" />
        <button
            type="button"
            class="dropdown-item local-mail-action-label-button-item"
            disabled={!applyEnabled}
            on:click={() => applyLabels()}
        >
            {$store.strings.applychanges}
        </button>
    </div>
</div>

<style>
    .local-mail-action-label-button::after {
        display: none;
    }
</style>
