<svelte:options immutable={true} />

<script lang="ts">
    import type { Course, Settings } from '../lib/services';

    export let course: Course;
    export let settings: Settings;

    $: text = settings.coursebadges == 'shortname' ? course.shortname : course.fullname;
    $: truncatedText =
        settings.coursebadgeslength > 0 && text.length > settings.coursebadgeslength
            ? text.slice(0, settings.coursebadgeslength) + '…'
            : text;
</script>

{#if settings.coursebadges != 'none'}
    <span
        class="local-mail-course-badge badge d-shrink-0 mr-2"
        title={text != truncatedText ? text : undefined}
    >
        {truncatedText}
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
