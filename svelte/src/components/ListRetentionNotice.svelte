<!--
SPDX-FileCopyrightText: 2026 Anderson Blaine

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;

    /*
     * Says out loud that mail in this tray is removed on a schedule, the way a spam
     * folder does. Nothing else in the interface would tell anyone: messages simply
     * stop being there one day.
     *
     * The trash needs room for two sentences rather than one. It holds mail of both
     * kinds, each on its own clock, and the usual configuration sets only the one for
     * updates -- so a single line would either state a rule that is not running or stay
     * silent while mail was being removed.
     *
     * A threshold of zero is how "keep indefinitely" is stored, so it prints nothing,
     * and so does the whole thing while the policy is switched off.
     */
    $: settings = $store.settings;
    $: tray = $store.params.tray;

    $: notices = !settings.retentionenabled
        ? []
        : tray == 'updates'
          ? [notice($store.strings.retentionnoticeupdates, settings.retentionupdatesdays)]
          : tray == 'trash'
            ? [
                  notice(
                      $store.strings.retentionnoticetrashupdates,
                      settings.retentionupdatestrashdays,
                  ),
                  notice($store.strings.retentionnoticetrash, settings.retentiontrashdays),
              ]
            : [];

    const notice = (text: string, days: number): string =>
        days > 0 ? replaceStringParams(text, days) : '';
</script>

{#if notices.some(Boolean)}
    <div class="alert alert-secondary text-dark mb-2 py-2 px-3">
        {#each notices.filter(Boolean) as text}
            <div><small>{text}</small></div>
        {/each}
    </div>
{/if}
