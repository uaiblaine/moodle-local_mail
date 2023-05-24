<svelte:options immutable={true} />

<script lang="ts">
    import { truncate } from '../actions/truncate';
    import type { Store } from '../lib/store';
    import { viewUrl, type ViewType, type ViewParams } from '../lib/url';

    export let store: Store;
    export let icon: string;
    export let text: string;
    export let params: ViewParams;
    export let count = 0;
    export let disabled = false;
    export let color: string | undefined = undefined;

    $: active =
        params.type == $store.params.type &&
        (params.type != 'label' || params.labelid == $store.params.labelid) &&
        (params.type != 'course' || params.courseid == $store.params.courseid);
    $: paramsWithOffset = active ? { ...params, offset: $store.params.offset } : params;
</script>

<a
    class="local-mail-menu-item list-group-item list-group-item-action d-flex align-items-center px-3 py-2"
    class:list-group-item-primary={active}
    class:disabled
    aria-current={active}
    aria-disabled={disabled}
    role="tab"
    href={viewUrl(paramsWithOffset)}
    on:click|preventDefault={() => store.navigate(paramsWithOffset)}
>
    <i
        class="fa {icon} fa-fw"
        aria-hidden="true"
        style={color ? `color: var(--local-mail-color-${color}` : ''}
    />
    <span class="flex-fill px-2" use:truncate={text}>
        {text}
    </span>
    {#if count > 0}
        <span class="local-mail-menu-item-count badge">
            {count}
        </span>
    {/if}
</a>
