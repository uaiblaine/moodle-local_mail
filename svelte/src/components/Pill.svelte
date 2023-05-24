<svelte:options immutable={true} />

<script lang="ts">
    export let text: string;
    export let color = 'gray';
    export let dimmed = false;
</script>

<span
    class="local-mail-pill local-mail-color-{color} badge d-shrink-0 mr-2"
    class:local-mail-pill-dimmed={dimmed}
    style="background-color: var(--local-mail-color-{color}); color: var(--local-mail-color-{color}-fg)"
>
    {text}
</span>

<style>
    .local-mail-pill {
        font-size: 100%;
        font-weight: bold;
        color: black;
        background: var(--secondary);
    }

    .local-mail-pill-dimmed {
        opacity: 0.75;
    }
</style>
