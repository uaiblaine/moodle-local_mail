<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { createUrl } from '../lib/url';

    export let store: Store;
</script>

<a
    class="btn btn-primary px-3 text-truncate"
    role="button"
    href={createUrl($store.params.courseid)}
>
    <i class="icon fa fa-fw fa-edit mr-1" />
    {$store.strings.compose}
</a>
