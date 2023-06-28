<svelte:options immutable={true} />

<script lang="ts">
    import { tick } from 'svelte';
    import { blur } from '../actions/blur';
    import { truncate } from '../actions/truncate';
    import type { Course } from '../lib/services';
    import type { Store } from '../lib/store';

    export let store: Store;

    let inputNode: HTMLInputElement;
    let inputText = '';
    let entering = false;
    let currentCourse: Course | undefined;
    let nameField: 'shortname' | 'fullname';

    $: nameField = $store.settings.filterbycourse == 'shortname' ? 'shortname' : 'fullname';
    $: currentCourse = $store.courses.find((course) => course.id == $store.params.courseid);
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
        await store.selectCourse();
        entering = false;
        inputText = '';
    };

    const selectCourse = async (course: Course) => {
        await store.selectCourse(course.id);
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

<div
    class="local-mail-course-filter position-relative d-flex ml-auto mr-0 ml-md-0 mr-md-auto"
    use:blur={closeDropdown}
>
    <div
        class="position-absolute h-100 d-flex align-items-center px-2 flex-shrink-1"
        style="top: 0; left: 0"
    >
        <i class="fa fa-fw fa-filter" aria-hidden="true" />
    </div>

    {#if $store.params.tray == 'course'}
        <div
            class="local-mail-course-filter-input alert-secondary form-control pl-5 pr-2 text-left"
            use:truncate={currentCourse?.[nameField] || ''}
        >
            {currentCourse?.[nameField]}
        </div>
    {:else if entering || !currentCourse}
        <input
            type="text"
            class="local-mail-course-filter-input form-control px-5 text-truncate"
            placeholder={$store.strings.filterbycourse}
            aria-label={$store.strings.filterbycourse}
            bind:value={inputText}
            bind:this={inputNode}
            on:focus={openDropdown}
            on:keyup={handleInputKey}
        />
    {:else}
        <button
            class="local-mail-course-filter-input alert-primary form-control px-5 text-left"
            use:truncate={currentCourse?.[nameField] || ''}
            on:click={toggleDropdown}
        >
            {currentCourse?.[nameField]}
        </button>
    {/if}
    {#if $store.params.tray != 'course'}
        <button
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
        <div class="dropdown-menu dropdown-menu-right dropdown-menu-md-left show">
            <button
                type="button"
                class="dropdown-item text-truncate"
                on:click={() => selectAllCourses()}
            >
                {$store.strings.allcourses}
            </button>

            <div class="dropdown-divider" />
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
    .local-mail-course-filter {
        min-width: 0;
        flex-grow: 1;
        max-width: 20rem;
    }

    .local-mail-course-filter .dropdown-menu {
        max-width: 90vw;
    }

    .local-mail-course-filter .dropdown-item :global(mark) {
        padding-left: 0;
        padding-right: 0;
        background-color: rgba(255, 255, 0, 0.2);
        color: inherit;
    }
</style>
