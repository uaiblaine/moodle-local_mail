<svelte:options immutable={true} />

<script lang="ts">
    import CourseBadge from './CourseBadge.svelte';
    import LabelBadge from './LabelBadge.svelte';
    import type { Message } from '../lib/services';
    import { type Store } from '../lib/store';

    export let store: Store;
    export let message: Message;
</script>

{#if message.course.id != $store.params.courseid}
    <CourseBadge course={message.course} settings={$store.settings} />
{/if}
{#each message.labels as label (label.id)}
    <LabelBadge {label} />
{/each}
