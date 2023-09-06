<svelte:options immutable={true} />

<script lang="ts">
    import { onMount, afterUpdate } from 'svelte';
    import { ViewportSize } from '../lib/state';
    import type { Store } from '../lib/store';
    import { getViewParamsFromUrl } from '../lib/url';
    import ComposeButton from './ComposeButton.svelte';
    import ErrorModal from './ErrorModal.svelte';
    import BottomToolBar from './BottomToolBar.svelte';
    import List from './List.svelte';
    import Menu from './Menu.svelte';
    import Message from './Message.svelte';
    import DraftForm from './DraftForm.svelte';
    import PerPageSelect from './PerPageSelect.svelte';
    import PreferencesButton from './PreferencesButton.svelte';
    import PreferencesDialog from './PreferencesDialog.svelte';
    import SearchBox from './SearchBox.svelte';
    import Toasts from './Toasts.svelte';
    import TopToolBar from './TopToolBar.svelte';

    export let store: Store;

    let viewNode: HTMLElement;
    let prevNavigationId = 0;

    $: tray = $store.params.tray;

    $: heading =
        tray == 'inbox'
            ? $store.strings.inbox
            : tray == 'starred'
            ? $store.strings.starredmail
            : tray == 'sent'
            ? $store.strings.sentmail
            : tray == 'drafts'
            ? $store.strings.drafts
            : tray == 'trash'
            ? $store.strings.trash
            : tray == 'label'
            ? $store.labels.find((l) => l.id == $store.params.labelid)?.name || ''
            : tray == 'course'
            ? $store.courses.find((c) => c.id == $store.params.courseid)?.fullname || ''
            : '';

    $: title = $store.message ? $store.message.subject.trim() || $store.strings.nosubject : heading;

    $: mobileTitle =
        $store.viewportSize < ViewportSize.LG ? heading || $store.strings.pluginname : '';

    $: window.parent?.postMessage(
        {
            addon: 'local_mail',
            setTitle: mobileTitle,
            captureBack: tray != null,
        },
        '*',
    );

    onMount(() => {
        store.setViewportSize(window.innerWidth);
    });

    afterUpdate(() => {
        if (prevNavigationId != $store.navigationId) {
            prevNavigationId = $store.navigationId;
            viewNode.scrollIntoView();
        }
    });

    const handleBeforeUnload = (event: Event) => {
        if ($store.draftData) {
            event.preventDefault();
            store.updateDraft($store.draftData, true);
            return '';
        }
    };

    const handleMessage = (event: MessageEvent) => {
        if ($store.mobile && event.data.addon == 'local_mail' && event.data.backClicked) {
            store.navigateToMenu();
        }
    };

    const handleClick = (event: Event) => {
        if ($store.mobile && !event.defaultPrevented && event.target instanceof HTMLElement) {
            const link = event.target.closest('a');
            if (link) {
                window.parent?.postMessage({ addon: 'local_mail', openUrl: link.href }, '*');
            }
            if (!event.defaultPrevented) {
                event.preventDefault();
            }
        }
    };
</script>

<svelte:window
    on:resize={() => store.setViewportSize(window.innerWidth)}
    on:popstate={() => store.navigate(getViewParamsFromUrl())}
    on:beforeunload={handleBeforeUnload}
    on:message={handleMessage}
/>

<svelte:document on:click={$store.mobile ? handleClick : undefined} />

<svelte:head>
    <title>{title} - {$store.strings.pluginname}</title>
</svelte:head>

<div
    class="local-mail-view container-fluid pt-2"
    class:p-4={!$store.mobile}
    class:local-mail-loading={$store.loading}
    bind:this={viewNode}
>
    <!-- Heading / search / compose button -->
    <div class="row align-items-center">
        {#if $store.mobile && $store.viewportSize < ViewportSize.LG}
            <div class="local-mail-view-side-column" />
        {:else}
            <h1 class="h2 local-mail-view-side-column text-truncate mb-4">
                {$store.strings.pluginname}
                {#if $store.viewportSize < ViewportSize.LG}
                    <i class="fa fa-angle-right mx-1" aria-hidden="true" />
                    {heading}
                {/if}
            </h1>
        {/if}

        <div class="local-mail-view-main-column d-flex mb-4">
            {#if tray}
                <div class="local-mail-view-search">
                    <SearchBox {store} />
                </div>
            {/if}
            {#if $store.viewportSize < ViewportSize.LG}
                <div class="text-truncate d-flex">
                    <ComposeButton
                        strings={$store.strings}
                        iconOnly={tray && $store.viewportSize < ViewportSize.SM}
                        onClick={() => store.createMessage()}
                    />
                </div>
                {#if !tray}
                    <div class="ml-auto">
                        <PreferencesButton
                            strings={$store.strings}
                            onClick={() => store.showDialog('preferences')}
                        />
                    </div>
                {/if}
            {/if}
        </div>
    </div>

    <!-- Toolbar -->
    {#if tray || $store.viewportSize >= ViewportSize.LG}
        <div class="row mb-3">
            {#if $store.viewportSize >= ViewportSize.LG}
                <div class="local-mail-view-side-column">
                    <ComposeButton strings={$store.strings} onClick={() => store.createMessage()} />
                </div>
            {/if}
            {#if tray}
                <div class="local-mail-view-main-column d-flex">
                    <TopToolBar {store} />
                </div>
            {/if}
        </div>
    {/if}

    <!-- List / Messaege -->
    <div class="row mb-3">
        {#if !tray || $store.viewportSize >= ViewportSize.LG}
            <div class="local-mail-view-side-column">
                <Menu
                    settings={$store.settings}
                    strings={$store.strings}
                    unread={$store.unread}
                    drafts={$store.drafts}
                    courses={$store.courses}
                    labels={$store.labels}
                    params={$store.params}
                    onClick={(params) => store.navigate(params)}
                />
            </div>
        {/if}
        {#if tray}
            <div class="local-mail-view-main-column">
                {#if $store.message?.draft && $store.draftForm}
                    <DraftForm {store} message={$store.message} form={$store.draftForm} />
                {:else if $store.message}
                    <Message {store} message={$store.message} />
                {:else}
                    <List {store} />

                    <PerPageSelect {store} />
                {/if}
            </div>
        {/if}
    </div>

    {#if tray && $store.viewportSize < ViewportSize.MD}
        <BottomToolBar {store} />
    {/if}

    {#if $store.params.dialog == 'preferences'}
        <PreferencesDialog {store} onCancel={() => store.hideDialog()} />
    {/if}

    <Toasts {store} />
    <ErrorModal {store} />
</div>

<style>
    :global(#page-local-mail-view #topofscroll) {
        padding: 0;
        margin-bottom: 0;
    }
    :global(#page-local-mail-view #region-main-box) {
        padding-left: 0;
        padding-right: 0;
    }

    :global(#page-local-mail-view #page-header) {
        display: none;
    }

    :global(#page-local-mail-view #page.drawers) {
        padding-left: 0;
        padding-right: 0;
    }

    :global(#page-local-mail-view #page.drawers .main-inner) {
        margin-top: 0;
    }

    :global(#page-local-mail-view .btn-footer-popover) {
        position: static;
        margin: 0 2rem 2rem auto;
    }

    .local-mail-view :global(.dropdown-menu) {
        z-index: 1040;
        box-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
    }

    .local-mail-view :global(.dropdown-menu),
    .local-mail-view :global(.dropdown-menu .list-group-item),
    .local-mail-view :global(.dropdown-menu .form-control) {
        background-color: var(--light);
    }

    .local-mail-view :global(.dropdown-item:not(:focus):hover) {
        color: inherit;
        background-color: #eee;
    }

    .local-mail-view {
        max-width: 100rem;
    }

    .local-mail-view :global(.fa) {
        font-size: 16px;
    }

    .local-mail-view :global(.form-control) {
        font-size: 1rem !important;
    }

    .local-mail-view-main-column {
        padding-right: 15px;
        padding-left: 15px;
        flex-basis: 100%;
        flex-grow: 1;
        min-width: 0;
        column-gap: 1rem;
    }

    .local-mail-view-side-column {
        padding-right: 15px;
        padding-left: 15px;
        flex-basis: 100%;
        flex-grow: 1;
        min-width: 0;
    }

    .local-mail-view-search {
        flex-grow: 1;
        margin-right: auto;
    }

    .local-mail-loading :global(*) {
        cursor: wait;
    }

    @media (min-width: 992px) {
        .local-mail-view-main-column {
            flex-basis: 75%;
            flex-shrink: 1;
        }
        .local-mail-view-side-column {
            flex-basis: 25%;
            flex-shrink: 1;
            max-width: 18rem;
        }
        .local-mail-view-search {
            max-width: 30rem;
        }
    }
</style>
