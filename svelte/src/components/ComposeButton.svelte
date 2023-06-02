<svelte:options immutable={true} />

<script lang="ts">
    import type { Strings } from '../lib/services';
    import { createUrl } from '../lib/url';

    export let strings: Strings;
    export let courseid: number | undefined = undefined;
</script>

<a class="btn btn-primary text-truncate px-3" role="button" href={createUrl(courseid)}>
    <i class="icon fa fa-fw fa-edit mr-1" />
    {strings.compose}
</a>
