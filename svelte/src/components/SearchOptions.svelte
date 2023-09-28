<!--
SPDX-FileCopyrightText: 2023 SEIDOR <https://www.seidor.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { dateFromTimestamp, timestampFromDate } from '../lib/utils';

    export let store: Store;
    export let sendername = '';
    export let recipientname = '';
    export let unread = false;
    export let withfilesonly = false;
    export let maxtime = 0;
    export let submit: () => void;
    export let submitEnabled: boolean;

    export function focus() {
        senderNode.focus();
    }

    let senderNode: HTMLElement;
    let today = dateFromTimestamp(Math.floor(new Date().getTime() / 1000));

    $: maxdate = dateFromTimestamp(maxtime);

    const updateMaxTime = (event: Event) => {
        maxtime = timestampFromDate((event.target as HTMLInputElement).value);
    };
</script>

<div class="dropdown-menu dropdown-menu-left show p-3 w-100" style="min-width: 18rem">
    <div class="form-group row">
        <label for="local-mail-search-input-sendername" class="col-3 col-form-label">
            {$store.strings.from}
        </label>
        <div class="col-9">
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-sendername"
                bind:value={sendername}
                bind:this={senderNode}
            />
        </div>
    </div>
    <div class="form-group row">
        <label for="local-mail-search-input-recipientname" class="col-3 col-form-label">
            {$store.strings.to}
        </label>
        <div class="col-9">
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-recipientname"
                bind:value={recipientname}
            />
        </div>
    </div>
    <div class="form-group row">
        <label for="local-mail-search-input-maxdate" class="col-3 col-form-label">
            {$store.strings.date}
        </label>
        <div class="col-9">
            <input
                type="date"
                class="form-control"
                id="local-mail-search-input-maxdate"
                max={today}
                value={maxdate}
                on:input={updateMaxTime}
            />
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center" style="column-gap: 2rem; row-gap: 1rem">
        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="local-mail-search-input-unread"
                bind:checked={unread}
            />
            <label class="form-check-label" for="local-mail-search-input-unread">
                {$store.strings.unreadonly}
            </label>
        </div>
        <div class="form-check">
            <input
                class="form-check-input"
                type="checkbox"
                id="local-mail-search-input-withfilesonly"
                bind:checked={withfilesonly}
            />
            <label class="form-check-label" for="local-mail-search-input-withfilesonly">
                {$store.strings.hasattachments}
            </label>
        </div>
        <input
            type="submit"
            disabled={!submitEnabled}
            class="btn btn-primary px-3 ml-auto"
            on:click|preventDefault={submit}
            value={$store.strings.search}
        />
    </div>
</div>
