<svelte:options immutable={true} />

<script lang="ts">
    import type { Strings } from '../lib/state';

    export let strings: Strings;
    export let onClick: () => void;
</script>

<button
    type="button"
    class="btn btn-secondary text-truncate"
    title={strings.preferences}
    on:click={onClick}
>
    <i class="fa fa-cog" aria-hidden={true} />
</button>
