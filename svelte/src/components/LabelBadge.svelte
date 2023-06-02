<svelte:options immutable={true} />

<script lang="ts">
    import type { MessageLabel } from '../lib/services';
    import { colors } from '../lib/utils';

    export let label: MessageLabel;

    $: color = colors.includes(label.color) ? label.color : 'gray';
</script>

<span
    class="local-mail-label-badge badge flex-shrink-0 px-2 mr-2 mb-2"
    style={`color: var(--local-mail-color-${color}-fg, var(--local-mail-color-gray-fg));` +
        `background-color: var(--local-mail-color-${color}-bg, var(--local-mail-color-gray-bg))`}
>
    {label.name}
</span>

<style>
    .local-mail-label-badge {
        font-size: inherit;
        font-weight: inherit;
        color: var(--local-mail-color-gray-fg);
        background-color: var(--local-mail-color-gray-bg);
    }
</style>
