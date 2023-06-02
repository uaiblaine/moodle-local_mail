<svelte:options immutable={true} />

<script lang="ts">
    import CourseBadge from './CourseBadge.svelte';
    import LabelBadge from './LabelBadge.svelte';
    import type { MessageSummary } from '../lib/services';
    import { ViewSize, type Store } from '../lib/store';

    export let store: Store;
    export let message: MessageSummary;
</script>

{#if $store.viewSize < ViewSize.MD}
    {#if $store.params.type != 'course' || $store.params.courseid != message.course.id}
        <CourseBadge course={message.course} settings={$store.settings} />
    {/if}
{/if}
{#each message.labels as label (label.id)}
    {#if $store.params.type != 'label' || $store.params.labelid != label.id}
        <LabelBadge {label} />
    {/if}
{/each}
{#if $store.viewSize >= ViewSize.MD}
    {#if $store.params.type != 'course' || $store.params.courseid != message.course.id}
        <CourseBadge course={message.course} settings={$store.settings} />
    {/if}
{/if}
