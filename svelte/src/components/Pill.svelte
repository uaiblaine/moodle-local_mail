<svelte:options immutable={true} />

<script lang="ts">
    import { colors } from '../lib/utils';

    export let text: string;
    export let color = 'gray';

    $: color = colors.includes(color) ? color : 'gray';
</script>

<span
    class="local-mail-pill badge d-shrink-0 mr-2"
    style={`color: var(--local-mail-color-${color}-fg, var(--local-mail-color-gray-fg));` +
        `background-color: var(--local-mail-color-${color}-bg, var(--local-mail-color-gray-bg))`}
>
    {text}
</span>

<style>
    .local-mail-pill {
        font-size: inherit;
        font-weight: inherit;
        color: var(--local-mail-color-gray-fg);
        background-color: var(--local-mail-color-gray-bg);
    }
</style>
