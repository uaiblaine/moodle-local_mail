<!--
SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    $: course = $store.courses.find((c) => c.id == $store.params.courseid);
</script>

{#if ['shortname', 'fullname'].includes($store.settings.courselink)}
    <div class="local-mail-course-link mt-n2 mb-3 mb-lg-4">
        {#if course}
            <nav class="d-flex align-items-center">
                <a
                    class="text-truncate"
                    href={window.M.cfg.wwwroot + '/course/view.php?id=' + $store.params.courseid}
                    title={replaceStringParams($store.strings.gotocourse, course.fullname)}
                >
                    {$store.settings.courselink == 'shortname' ? course.shortname : course.fullname}
                </a>
            </nav>
        {:else}
            &nbsp;
        {/if}
    </div>
{/if}

<style global>
</style>
