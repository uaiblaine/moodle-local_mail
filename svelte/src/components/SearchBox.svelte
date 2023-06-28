<svelte:options immutable={true} />

<script lang="ts">
    import { tick } from 'svelte';
    import { blur } from '../actions/blur';
    import type { SearchParams, Store, ViewParams } from '../lib/store';
    import AdvancedSearch from './AdvancedSearch.svelte';
    import IncrementalSearch from './IncrementalSearch.svelte';

    export let store: Store;

    let entering = !$store.params.search;
    let advancedExpanded = false;
    let inputNode: HTMLElement;
    let advancedNode: AdvancedSearch;
    let content = '';
    let sendername = '';
    let recipientname = '';
    let unread = false;
    let withfilesonly = false;
    let maxtime = 0;
    let loading = false;

    const updateFields = (search?: SearchParams) => {
        content = search?.content || '';
        sendername = search?.sendername || '';
        recipientname = search?.recipientname || '';
        unread = search?.unread || false;
        withfilesonly = search?.withfilesonly || false;
        maxtime = search?.maxtime || 0;
    };

    $: search = $store.params.search;

    $: updateFields(search);

    $: advancedEnabled = Boolean(
        search?.sendername ||
            search?.recipientname ||
            search?.unread ||
            search?.withfilesonly ||
            search?.maxtime,
    );

    $: searchEnabled = search?.content || advancedEnabled;

    $: submitEnabled = Boolean(
        content.trim() ||
            sendername.trim() ||
            recipientname.trim() ||
            unread ||
            withfilesonly ||
            maxtime,
    );

    $: searchFields = [
        { label: '', value: content },
        { label: $store.strings.from, value: sendername },
        { label: $store.strings.to, value: recipientname },
        {
            label: $store.strings.filterbydate,
            value: maxtime > 0 ? new Date(maxtime * 1000).toLocaleDateString() : '',
        },
        { label: $store.strings.searchbyunread, value: unread },
        { label: $store.strings.searchbyattach, value: withfilesonly },
    ].filter(({ value }) => Boolean(value));

    const startEntering = async () => {
        entering = true;
        if (advancedEnabled) {
            advancedExpanded = true;
        }
        await tick();
        inputNode.focus();
    };

    const stopEntering = async () => {
        entering = !searchEnabled;
        advancedExpanded = false;
        updateFields(search);
    };

    const toggleDropdown = async () => {
        if (advancedExpanded) {
            advancedExpanded = false;
            await tick();
            inputNode.focus();
        } else {
            advancedExpanded = true;
            startEntering();
            await tick();
            advancedNode.focus();
        }
    };

    const cancel = async () => {
        entering = true;
        advancedExpanded = false;
        updateFields();
        await store.navigate({ ...$store.params, offset: 0, search: undefined });
        await tick();
        inputNode.focus();
    };

    const submit = async () => {
        await store.navigate({
            ...$store.params,
            messageid: undefined,
            offset: undefined,
            search: advancedExpanded
                ? {
                      content: content.trim(),
                      sendername: sendername.trim(),
                      recipientname: recipientname.trim(),
                      unread,
                      withfilesonly,
                      maxtime,
                  }
                : {
                      content: content.trim(),
                  },
        });
        advancedExpanded = false;
        entering = false;
    };

    const handleKeypress = (event: KeyboardEvent) => {
        if (event.key == 'Enter') {
            event.preventDefault();
            if (submitEnabled) {
                submit();
            } else {
                cancel();
            }
        }
    };

    const handleIncrementalSearchClick = async (params: ViewParams) => {
        await store.navigate(params);
        advancedExpanded = false;
        entering = false;
    };
</script>

<form class="local-mail-search-input position-relative" use:blur={stopEntering}>
    <div
        class="position-absolute h-100 d-flex justify-content-center align-items-center px-0"
        style="top: 0; left: 0; width: 2.5rem"
    >
        <i class="fa fa-fw {loading ? 'fa-spinner fa-pulse' : 'fa-search'}" aria-hidden="true" />
    </div>

    {#if entering}
        <input
            type="text"
            class="form-control"
            style="padding-left: 2.5rem; padding-right: 5rem"
            placeholder={$store.strings.search}
            aria-label={$store.strings.search}
            autocomplete="off"
            bind:value={content}
            bind:this={inputNode}
            on:keypress={handleKeypress}
        />
    {:else}
        <button
            type="button"
            class="alert-primary form-control text-left text-truncate"
            style="padding-left: 2.5rem; padding-right: 5rem"
            on:click={startEntering}
        >
            {#each searchFields as { label, value }, i}
                {#if i > 0}<span class="dimmed_text">,&ensp;</span>{/if}
                {#if value === true}
                    <span class="dimmed_text">{label}</span>
                {:else}
                    {#if label}<span class="dimmed_text">{label}: </span>{/if}
                    {value}
                {/if}
            {/each}
        </button>
    {/if}
    <div class="position-absolute h-100 d-flex align-items-center" style="top: 0; right: 0">
        {#if searchEnabled || submitEnabled}
            <button
                type="button"
                class="btn px-0"
                title={$store.strings.cancel}
                style="width: 2.5rem"
                on:click|preventDefault={cancel}
            >
                <i class="fa fa-fw fa-times" aria-hidden="true" />
            </button>
        {/if}
        <button
            type="button"
            aria-expanded={advancedExpanded}
            class="btn px-0"
            style="width: 2.5rem"
            title={$store.strings.advsearch}
            on:click|preventDefault={toggleDropdown}
        >
            <i
                class="fa fa-fw {advancedExpanded ? 'fa-caret-up' : 'fa-sliders'}"
                aria-hidden="true"
            />
        </button>
    </div>
    {#if advancedExpanded}
        <AdvancedSearch
            bind:this={advancedNode}
            {store}
            bind:sendername
            bind:recipientname
            bind:unread
            bind:withfilesonly
            bind:maxtime
            {submit}
            {submitEnabled}
        />
    {/if}
    {#if $store.settings.incrementalsearch}
        <IncrementalSearch
            {store}
            enabled={entering && !advancedExpanded && !!content.trim()}
            {content}
            bind:loading
            handleClick={handleIncrementalSearchClick}
        />
    {/if}
</form>

<style>
    .local-mail-search-input {
        width: 100%;
        max-width: 100%;
    }
</style>
