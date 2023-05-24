<svelte:options immutable={true} />

<script lang="ts">
    import type { MenuLabel } from '../lib/services';
    import type { Store } from '../lib/store';
    import { normalizeLabelName } from '../lib/utils';

    const colors: ReadonlyArray<string> = [
        'blue',
        'indigo',
        'purple',
        'pink',
        'red',
        'orange',
        'yellow',
        'green',
        'teal',
        'cyan',
        'gray',
        'black',
    ];
    export let store: Store;
    export let label: MenuLabel | undefined = undefined;

    let name = label?.name || '';
    let selectedColor = label?.color || colors[0];

    $: emptyName = normalizeLabelName(name) == '';
    $: repeatedName = $store.menu.labels.some(
        (l) => l.id != label?.id && l.name == normalizeLabelName(name),
    );
    $: validName = !emptyName && !repeatedName;

    const reset = () => {
        // Wait until the close transition ends.
        setTimeout(() => {
            name = '';
            selectedColor = colors[0];
        }, 500);
    };

    const submit = async () => {
        if (label) {
            store.updateLabel(label.id, name, selectedColor);
        } else {
            const id = await store.createLabel(name, selectedColor);
            store.setLabels(Array.from($store.selectedIds.values()), [id], []);
        }
        reset();
    };
</script>

<div
    class="modal fade"
    id="local-mail-label-modal-{label?.id || 'new'}"
    tabindex="-1"
    aria-labelledby="local-mail-label-modal-title-{label?.id || 'new'}"
    aria-hidden="true"
>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="local-mail-label-modal-title-{label?.id || 'new'}">
                    {$store.strings[label ? 'editlabel' : 'newlabel']}
                </h5>
                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label={$store.strings.cancel}
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="local-mail-label-modal-name">{$store.strings.labelname}</label>
                    <input
                        type="text"
                        required
                        class="form-control is-invalid"
                        class:is-valid={validName}
                        class:is-invalid={!validName}
                        id="local-mail-label-modal-name-{label?.id || 'new'}"
                        bind:value={name}
                    />
                    <div class="invalid-feedback">
                        {$store.strings[
                            repeatedName ? 'errorrepeatedlabelname' : 'erroremptylabelname'
                        ]}
                    </div>
                    <div class="valid-feedback">&nbsp;</div>
                </div>
                <div class="form-group">
                    <label for="local-mail-label-modal-color-{label?.id || 'new'}">
                        {$store.strings.labelcolor}
                    </label>
                    <div
                        role="radiogroup"
                        class="local-mail-label-modal-color"
                        id="local-mail-label-modal-color-{label?.id || 'new'}"
                    >
                        {#each colors as color}
                            <button
                                role="radio"
                                aria-checked={color == selectedColor}
                                tabindex="0"
                                title={$store.strings[`color${color}`]}
                                class="local-mail-label-modal-color-option btn"
                                style="background-color: var(--local-mail-color-{color}); color: var(--local-mail-color-{color}-fg)"
                                on:click={() => {
                                    selectedColor = color;
                                }}
                            >
                                {#if color == selectedColor}
                                    <i
                                        class="fa fa-check local-mail-label-modal-color-option-check"
                                    />
                                {/if}
                            </button>
                        {/each}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-secondary"
                    data-dismiss="modal"
                    on:click={() => reset()}
                >
                    {$store.strings.cancel}
                </button>
                <button
                    type="button"
                    disabled={!validName}
                    class="btn btn-primary"
                    data-dismiss="modal"
                    on:click={() => submit()}
                >
                    {$store.strings[label ? 'save' : 'create']}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .local-mail-label-modal-color {
        display: flex;
        flex-wrap: wrap;
    }
    .local-mail-label-modal-color-option {
        width: 2rem;
        height: 2rem;
        margin-right: 0.4rem;
        margin-bottom: 0.4rem;
        display: flex;
        justify-content: center;
        align-items: center;
        cursor: pointer;
    }

    .local-mail-label-modal-color-option:last-child {
        margin-right: 0;
    }
</style>
