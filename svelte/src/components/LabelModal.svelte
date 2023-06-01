<svelte:options immutable={true} />

<script lang="ts">
    import { jQueryEvents } from '../actions/jQueryEvents';
    import type { MenuLabel } from '../lib/services';
    import type { Store } from '../lib/store';
    import { colors, normalizeLabelName } from '../lib/utils';

    export let store: Store;
    export let label: MenuLabel | undefined = undefined;

    let nameEl: HTMLElement;

    $: id = `local-mail-label-modal-${label?.id || 'new'}`;
    $: name = label?.name || '';
    $: selectedColor = label?.color || colors[0];
    $: emptyName = normalizeLabelName(name) == '';
    $: repeatedName = $store.menu.labels.some(
        (l) => l.id != label?.id && l.name == normalizeLabelName(name),
    );
    $: validName = !emptyName && !repeatedName;

    const reset = () => {
        name = label?.name || '';
        selectedColor = label?.color || colors[0];
    };

    const submit = async () => {
        window.jQuery(`#${id}`).modal('hide');
        if (label) {
            store.updateLabel(label.id, name, selectedColor);
        } else {
            const id = await store.createLabel(name, selectedColor);
            if (id) {
                store.setLabels(Array.from($store.targetMessageIds.values()), [id], []);
            }
        }
    };
</script>

<div
    class="modal fade"
    {id}
    tabindex="-1"
    aria-labelledby="{id}-title"
    aria-hidden="true"
    use:jQueryEvents={{
        'shown.bs.modal': () => {
            nameEl.focus();
        },
        'hidden.bs.modal': () => {
            reset();
        },
    }}
>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{id}-title">
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
                <form on:submit|preventDefault={() => submit()}>
                    <div class="form-group">
                        <label for="local-mail-label-modal-name">{$store.strings.labelname}</label>
                        <input
                            type="text"
                            required
                            class="form-control is-invalid"
                            class:is-valid={validName}
                            class:is-invalid={!validName}
                            id="{id}-name"
                            bind:this={nameEl}
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
                        <label for="{id}-color">
                            {$store.strings.labelcolor}
                        </label>
                        <div role="radiogroup" class="local-mail-label-modal-color" id="{id}-color">
                            {#each colors as color (color)}
                                <button
                                    role="radio"
                                    aria-checked={color == selectedColor}
                                    tabindex="0"
                                    title={$store.strings[`color${color}`]}
                                    class="local-mail-label-modal-color-option btn"
                                    style={`color: var(--local-mail-color-${color}-fg, var(--local-mail-color-gray-fg));` +
                                        `background-color: var(--local-mail-color-${color}-bg, var(--local-mail-color-gray-bg))`}
                                    on:click|preventDefault={() => {
                                        selectedColor = color;
                                    }}
                                >
                                    {#if color == selectedColor}
                                        <i
                                            class="fa fa-check local-mail-label-modal-color-option-check"
                                        />
                                    {:else}
                                        <span aria-hidden="true">a</span>
                                    {/if}
                                </button>
                            {/each}
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
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
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: center;
        align-items: center;
        color: var(--local-mail-color-gray-fg);
        background-color: var(--local-mail-color-gray-bg);
    }

    .local-mail-label-modal-color-option:last-child {
        margin-right: 0;
    }
</style>
