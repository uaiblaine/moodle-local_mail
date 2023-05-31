<svelte:options immutable={true} />

<script lang="ts">
    import { truncate } from '../actions/truncate';
    import { type ViewParams } from '../lib/store';
    import { viewUrl } from '../lib/url';

    export let icon: string;
    export let text: string;
    export let params: ViewParams;
    export let count = 0;
    export let disabled = false;
    export let active = false;
    export let color: string | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;

    $: handleClick = (event: Event) => {
        if (onClick) {
            event.preventDefault();
            onClick(params);
        }
    };
</script>

<a
    class="local-mail-menu-item list-group-item list-group-item-action d-flex align-items-center px-3 py-2"
    class:list-group-item-primary={active}
    class:disabled
    aria-current={active}
    aria-disabled={disabled}
    role="tab"
    href={viewUrl(params)}
    on:click={handleClick}
    style={color != null && !active
        ? `color: var(--local-mail-color-${color}-fg, var(--local-mail-color-gray-fg));`
        : ''}
>
    <i
        class="fa {icon} fa-fw"
        aria-hidden="true"
        style={color != null && !active
            ? `color: var(--local-mail-color-${color}-bg, var(--local-mail-color-gray-bg));`
            : ''}
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
