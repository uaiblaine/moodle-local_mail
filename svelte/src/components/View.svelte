<!--
SPDX-FileCopyrightText: 2023-2024 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>
SPDX-FileCopyrightText: 2024-2025 Albert Gasset <albertgasset@fsfe.org>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { onMount, afterUpdate } from 'svelte';
    import { busy } from '../actions/busy';
    import { ViewportSize } from '../lib/state';
    import type { Store } from '../lib/store';
    import { getViewParamsFromUrl } from '../lib/url';
    import { formatCourseName } from '../lib/utils';
    import ComposeButton from './ComposeButton.svelte';
    import CourseLink from './CourseLink.svelte';
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

    $: loading = $store.loading;
    $: tray = $store.params.tray;
    $: course = $store.courses.find((c) => c.id == $store.params.courseid);
    $: canCompose = $store.courses.some((c) => c.canwrite);
    $: label = $store.labels.find((l) => l.id == $store.params.labelid);

    $: heading =
        tray == 'inbox'
            ? $store.strings.inbox
            : tray == 'updates'
              ? $store.strings.updates
              : tray == 'starred'
                ? $store.strings.starredplural
                : tray == 'sent'
                  ? $store.strings.sentplural
                  : tray == 'drafts'
                    ? $store.strings.drafts
                    : tray == 'trash'
                      ? $store.strings.trash
                      : tray == 'label'
                        ? label?.name || ''
                        : tray == 'course'
                          ? formatCourseName(course, 'fullname')
                          : '';

    $: title = $store.message ? $store.message.subject.trim() || $store.strings.nosubject : heading;

    $: mobileTitle =
        $store.viewportSize < ViewportSize.LG ? heading || $store.strings.pluginname : '';

    $: captureBackButton = tray != null;

    /*
     * Origin of the Moodle App page that frames the mailbox, or null when it cannot be named.
     * The app serves its page from http(s)://localhost on Android and in a browser, but from a
     * custom scheme on iOS, whose origin no target origin can name; there the messages keep
     * '*', and they still reach window.parent only. Where the browser reports the framing
     * origin (location.ancestorOrigins) it decides; the referrer is the fallback elsewhere.
     */
    const appOrigin = ((): string | null => {
        const ancestors = window.location.ancestorOrigins;
        const candidate = ancestors ? ancestors[0] : document.referrer;
        try {
            const url = new URL(candidate || '');
            return url.protocol == 'https:' || url.protocol == 'http:' ? url.origin : null;
        } catch {
            return null;
        }
    })();

    /*
     * Messages for the app's page (classes/output/mobile-view.js), the only listener. Outside
     * the app nothing listens, so nothing is posted, and no framing page learns the title or
     * the links of the mailbox.
     */
    const postToApp = (data: Record<string, unknown>) => {
        if ($store.mobile && window.parent !== window) {
            window.parent.postMessage({ addon: 'local_mail', ...data }, appOrigin || '*');
        }
    };

    $: postToApp({ setLoading: loading });
    $: postToApp({ setTitle: mobileTitle });
    $: postToApp({ captureBack: captureBackButton });

    onMount(() => {
        store.setViewportSize(window.innerWidth);
    });

    afterUpdate(() => {
        if (prevNavigationId != $store.navigationId) {
            prevNavigationId = $store.navigationId;
            let parent = viewNode.parentElement;
            while (parent) {
                if (parent.scrollTop > 0) {
                    parent.scrollTo({ top: 0 });
                }
                parent = parent.parentElement;
            }
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
        // Only the app's page that frames the mailbox may drive it.
        if (!$store.mobile || window.parent === window || event.source !== window.parent) {
            return;
        }
        if (appOrigin && event.origin != appOrigin) {
            return;
        }
        if (event.data?.addon == 'local_mail' && event.data.backClicked) {
            store.navigateToMenu();
        }
    };

    const handleClick = (event: Event) => {
        if ($store.mobile && !event.defaultPrevented && event.target instanceof HTMLElement) {
            const link = event.target.closest('a');
            if (link) {
                postToApp({ openUrl: link.href });
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
    use:busy={$store.loading}
    class="local-mail local-mail-view container-fluid d-flex flex-column pt-2"
    class:pt-3={!$store.mobile}
    class:p-md-4={!$store.mobile && $store.viewportSize >= ViewportSize.MD}
    class:min-vh-100={$store.viewportSize < ViewportSize.MD}
    bind:this={viewNode}
>
    {#if !$store.mobile}
        <CourseLink {store} />
    {/if}

    <!-- Heading / search / compose button -->
    <div class="row align-items-center">
        {#if $store.mobile && $store.viewportSize < ViewportSize.LG}
            <div class="local-mail-view-side-column" />
        {:else}
            <h1 class="h2 local-mail-view-side-column text-truncate mb-3 mb-md-4">
                {$store.strings.pluginname}
                {#if heading && $store.viewportSize < ViewportSize.LG}
                    <i class="fa fa-angle-right mx-1" aria-hidden="true" />
                    {heading}
                {/if}
            </h1>
        {/if}

        <div
            class="local-mail-view-main-column d-flex mb-3"
            class:mb-4={$store.viewportSize >= ViewportSize.LG}
        >
            {#if tray}
                <div class="local-mail-view-search">
                    <SearchBox {store} />
                </div>
            {/if}
            {#if $store.viewportSize < ViewportSize.LG}
                <ComposeButton
                    strings={$store.strings}
                    iconOnly={tray && $store.viewportSize < ViewportSize.SM}
                    disabled={!canCompose}
                    onClick={() => store.createMessage($store.params.courseid)}
                />
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
                    <ComposeButton
                        strings={$store.strings}
                        disabled={!canCompose}
                        onClick={() => store.createMessage($store.params.courseid)}
                    />
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
                    courses={$store.courses}
                    labels={$store.labels}
                    params={$store.params}
                    onClick={store.navigate}
                    onCourseChange={store.selectCourse}
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
                    {#if course && !course.canwrite}
                        <div class="alert alert-info">
                            <i class="fa fa-lock mr-2" />
                            {$store.strings.coursereadonly}
                        </div>
                    {/if}
                    <List {store} />

                    <PerPageSelect {store} />
                {/if}
            </div>
        {/if}
    </div>
</div>

<div class="local-mail" style="display: contents">
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
    .local-mail-view {
        max-width: 100rem;
        padding-bottom: 0 !important;
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
