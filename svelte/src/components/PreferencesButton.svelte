<svelte:options immutable={true} />

<script lang="ts">
    import type { Strings } from '../lib/services';
    import { preferencesUrl } from '../lib/url';

    export let strings: Strings;
</script>

<a
    class="btn btn-secondary text-truncate"
    role="button"
    href={preferencesUrl()}
    title={strings.preferences}
>
    <i class="fa fa-cog" aria-hidden={true} />
</a>
