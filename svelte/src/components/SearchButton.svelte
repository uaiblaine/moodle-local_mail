<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import SearchModal from './SearchModal.svelte';

    export let store: Store;

    $: enabled =
        !!$store.params.query?.content ||
        !!$store.params.query?.sender ||
        !!$store.params.query?.recipients ||
        !!$store.params.query?.unread ||
        !!$store.params.query?.attachments ||
        !!$store.params.query?.time;
</script>

<button
    type="button"
    class="local-mail-action-search btn"
    class:btn-info={enabled}
    class:btn-secondary={!enabled}
    class:disabled={!$store.list.totalcount}
    disabled={!$store.list.totalcount}
    title={$store.strings.search}
    aria-pressed={enabled}
    data-toggle="modal"
    data-target="#local-mail-search-modal"
>
    <i class="fa fa-fw fa-search" />
</button>

<SearchModal {store} />
