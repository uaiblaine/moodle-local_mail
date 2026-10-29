<!--
SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
SPDX-FileCopyrightText: 2025 Albert Gasset <albertgasset@fsfe.org>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { convertHtmlToText, formatCourseName, replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: course = $store.courses.find((c) => c.id == $store.params.courseid);
    $: category = course ? convertHtmlToText(course.categoryname) : '';
</script>

{#if ['shortname', 'fullname'].includes($store.settings.courselink)}
    <div class="local-mail-course-link mb-3 mb-lg-4">
        {#if course}
            <!--
                Breadcrumb look on one plain link: the current page is the mailbox, so a
                breadcrumb's semantics would be wrong. The accessible name is the visible text.
            -->
            <a
                class="local-mail-course-link-chip"
                href={window.M.cfg.wwwroot + '/course/view.php?id=' + course.id}
                title={replaceStringParams(
                    $store.strings.gotocourse,
                    formatCourseName(course, 'fullname'),
                )}
            >
                {#if category}
                    <span class="local-mail-course-link-category">{category}</span>
                    <i
                        class="local-mail-course-link-separator fa fa-angle-right"
                        aria-hidden="true"
                    />
                {/if}
                <span class="local-mail-course-link-name">
                    {formatCourseName(course, $store.settings.courselink)}
                </span>
                <i class="local-mail-course-link-arrow fa fa-arrow-right" aria-hidden="true" />
            </a>
        {:else}
            &nbsp;
        {/if}
    </div>
{/if}

<style>
    .local-mail-course-link {
        margin-top: -0.5rem;
    }

    .local-mail-course-link-chip {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        min-width: 0;
        padding: 0.0625rem 0.625rem;
        border: 1px solid var(--bs-border-color, var(--gray-300, #dee2e6));
        border-radius: 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
        text-decoration: none;
        vertical-align: middle;
    }

    .local-mail-course-link-chip:hover,
    .local-mail-course-link-chip:focus {
        border-color: var(--bs-link-color, var(--primary, #0f6cbf));
        text-decoration: none;
    }

    .local-mail-course-link-chip:hover .local-mail-course-link-name,
    .local-mail-course-link-chip:focus .local-mail-course-link-name {
        text-decoration: underline;
    }

    .local-mail-course-link-chip:focus-visible {
        outline: 2px solid var(--bs-link-color, var(--primary, #0f6cbf));
        outline-offset: 2px;
    }

    .local-mail-course-link-category {
        flex: 0 1 auto;
        max-width: 40%;
        overflow: hidden;
        color: var(--bs-secondary-color, var(--secondary, #6c757d));
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .local-mail-course-link-separator {
        flex: none;
        margin: 0 0.375rem;
        color: var(--bs-secondary-color, var(--secondary, #6c757d));
    }

    .local-mail-course-link-name {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        color: var(--bs-link-color, var(--primary, #0f6cbf));
        font-weight: 500;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .local-mail-course-link-arrow {
        flex: none;
        margin-left: 0.375rem;
        color: var(--bs-link-color, var(--primary, #0f6cbf));
        font-size: 0.75rem;
    }

    @media (max-width: 575.98px) {
        .local-mail-course-link-category,
        .local-mail-course-link-separator {
            display: none;
        }
    }
</style>
