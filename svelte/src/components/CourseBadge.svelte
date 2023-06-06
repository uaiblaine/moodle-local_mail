<svelte:options immutable={true} />

<script lang="ts">
    import { truncate } from '../actions/truncate';
    import type { Course, Settings } from '../lib/services';

    export let course: Course;
    export let settings: Settings;

    $: text = settings.coursebadges == 'shortname' ? course.shortname : course.fullname;
    $: length = settings.coursebadgeslength || 20 + 1;
</script>

{#if settings.coursebadges != 'none'}
    <span
        class="local-mail-course-badge badge px-2 mr-2 mb-2"
        use:truncate={text}
        style="min-width: 3rem; max-width: calc({length}ch + 1rem)"
    >
        {text}
    </span>
{/if}

<style>
    .local-mail-course-badge {
        font-size: inherit;
        font-weight: inherit;
        color: var(--local-mail-color-gray-fg);
        background-color: var(--local-mail-color-gray-bg);
    }
</style>
