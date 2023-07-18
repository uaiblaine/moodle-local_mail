<svelte:options immutable={true} />

<script lang="ts">
    import { tick } from 'svelte';
    import { blur } from '../actions/blur';
    import { truncate } from '../actions/truncate';
    import type { Course } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;
    export let label: string;
    export let selected: number | undefined;
    export let required = false;
    export let readonly = false;
    export let primary = false;
    export let align: 'left' | 'right' = 'left';
    export let onChange: (id?: number) => void;

    let inputNode: HTMLInputElement;
    let inputText = '';
    let entering = false;
    let currentCourse: Course | undefined;
    let nameField: 'shortname' | 'fullname';

    $: nameField = $store.settings.filterbycourse == 'shortname' ? 'shortname' : 'fullname';
    $: currentCourse = $store.courses.find((course) => course.id == selected);
    $: inputPattern = new RegExp(escape(inputText.trim()).replaceAll(/\s+/gu, '\\s+'), 'giu');
    $: dropdownCourses = $store.courses.filter((course) => course[nameField].match(inputPattern));
    $: dropdownIconClass = !entering ? 'fa-caret-down' : inputText ? 'fa-times' : 'fa-caret-up';
    $: courseHtml = (course: Course): string =>
        course[nameField].replaceAll(inputPattern, (match) =>
            match.trim() ? '<mark>' + match + '</mark>' : match,
        );

    const escape = (text: string): string => text.replace(/[.*+?^${}()|[\]\\]/gu, '\\$&');

    const openDropdown = async () => {
        entering = true;
        inputText = '';
        await tick();
        inputNode.focus();
    };

    const closeDropdown = () => {
        entering = false;
        inputText = '';
    };

    const toggleDropdown = async () => {
        if (entering) {
            closeDropdown();
        } else {
            openDropdown();
        }
    };

    const selectAllCourses = async () => {
        await onChange();
        selected = undefined;
        entering = false;
        inputText = '';
    };

    const selectCourse = async (course: Course) => {
        await onChange(course.id);
        selected = course.id;
        entering = false;
        inputText = '';
    };

    const handleInputKey = (event: KeyboardEvent) => {
        if (event.key == 'Escape') {
            entering = false;
            inputText = '';
            inputNode.blur();
        }
    };
</script>

<div class="local-mail-course-select position-relative d-flex" use:blur={closeDropdown}>
    <div class="position-absolute h-100 d-flex align-items-center px-2" style="top: 0; left: 0">
        <i class="fa fa-fw fa-filter" aria-hidden="true" />
    </div>

    {#if readonly}
        <div
            class="alert-secondary form-control pl-5 pr-2 text-left"
            use:truncate={currentCourse?.[nameField] || ''}
        >
            {currentCourse?.[nameField]}
        </div>
    {:else if entering || !currentCourse}
        <input
            type="text"
            class="form-control px-5 text-truncate"
            placeholder={label}
            aria-label={label}
            bind:value={inputText}
            bind:this={inputNode}
            on:focus={openDropdown}
            on:keyup={handleInputKey}
        />
    {:else}
        <button
            type="button"
            class="form-control px-5 text-left"
            class:alert-primary={primary}
            use:truncate={currentCourse?.[nameField] || ''}
            on:click={toggleDropdown}
        >
            {currentCourse?.[nameField]}
        </button>
    {/if}
    {#if !readonly}
        <button
            type="button"
            aria-expanded={entering}
            class="btn position-absolute h-100 d-flex align-items-center px-2"
            style="top: 0; right: 0"
            on:click={toggleDropdown}
        >
            <i class="fa fa-fw {dropdownIconClass}" aria-hidden="true" />
            <span class="sr-only">{$store.strings.togglefilterresults}n</span>
        </button>
    {/if}

    {#if entering}
        <div class="dropdown-menu dropdown-menu-{align} show">
            {#if !required}
                <button
                    type="button"
                    class="dropdown-item text-truncate"
                    on:click={() => selectAllCourses()}
                >
                    {$store.strings.allcourses}
                </button>

                <div class="dropdown-divider" />
            {/if}
            {#each dropdownCourses as course (course.id)}
                <button
                    type="button"
                    class="dropdown-item text-truncate"
                    on:click={() => selectCourse(course)}
                >
                    <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                    {@html courseHtml(course)}
                </button>
            {:else}
                <div class="px-4 text-danger">
                    {$store.strings.emptycoursefilterresults}
                </div>
            {/each}
        </div>
    {/if}
</div>

<style>
    .local-mail-course-select {
        min-width: 0;
        flex-grow: 1;
        width: 100%;
    }

    .local-mail-course-select .dropdown-menu {
        max-width: 90vw;
    }

    .local-mail-course-select .dropdown-item :global(mark) {
        padding-left: 0;
        padding-right: 0;
        background-color: rgba(255, 255, 0, 0.2);
        color: inherit;
    }
</style>
