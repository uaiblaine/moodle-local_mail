<svelte:options immutable={true} />

<script lang="ts">
    import { colors } from '../lib/utils';

    export let text: string;
    export let color = 'gray';

    $: color = colors.includes(color) ? color : 'gray';
</script>

<span
    class="local-mail-pill local-mail-color-{color} badge d-shrink-0 mr-2"
    style="background-color: var(--local-mail-color-{color}); color: var(--local-mail-color-{color}-fg)"
>
    {text}
</span>

<style>
    .local-mail-pill {
        font-size: inherit;
        font-weight: bold;
        color: var(--local-mail-color-gray-text);
        background: var(--local-mail-color-blue);
    }
</style>
