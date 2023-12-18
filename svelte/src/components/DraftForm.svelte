<!--
SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { require, type CoreFragment, type EditorTinyLoader, type TinyMCE } from '../lib/amd';
    import {
        ViewportSize,
        type Message,
        type MessageData,
        type MessageForm,
        type RecipientType,
        type User,
    } from '../lib/state';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';
    import CourseSelect from './CourseSelect.svelte';
    import DraftFormRecipients from './DraftFormRecipients.svelte';
    import DraftFormTimeAndLabels from './DraftFormTimeAndLabels.svelte';
    import DraftFormUserSearch from './DraftFormUserSearch.svelte';
    import MessageReference from './MessageReference.svelte';
    import SendButton from './SendButton.svelte';

    export let store: Store;
    export let message: Message;
    export let form: MessageForm;

    let jsNode: Element | undefined;
    let formNode: HTMLFormElement | undefined;

    $: course = message.course;
    $: subject = message.subject;
    $: recipients = new Map(message.recipients.map((user) => [user.id, user]));

    $: updateJavascript(form.javascript);

    onMount(() => {
        formNode?.addEventListener('core_form/uploadChanged', () => save(false));
        const disableTinyEventHandlers = enableTinyEventHandlers();

        return () => {
            jsNode?.remove();
            disableTinyEventHandlers();
        };
    });

    const enableTinyEventHandlers = () => {
        let tiny: TinyMCE.TinyMCE | undefined;
        let tinyEditor: TinyMCE.Editor | undefined;

        const handleChange = () => {
            tinyEditor?.save();
            save(false);
        };

        const handleEditor = (event: { editor: TinyMCE.Editor }) => {
            if (event.editor.id == `local-mail-compose-editor-${message.id}`) {
                tinyEditor?.off('input', handleChange);
                tinyEditor?.off('ExecCommand', handleChange);
                tinyEditor = event.editor;
                event.editor.on('input', handleChange);
                event.editor.on('ExecCommand', handleChange);
            }
        };

        require('editor_tiny/loader').then(async (loader) => {
            tiny = await (loader as EditorTinyLoader).getTinyMCE();
            tiny.EditorManager.get().forEach((editor) => handleEditor({ editor }));
            tiny.EditorManager.on('SetupEditor', handleEditor);
        });

        return () => {
            tiny?.EditorManager.off('SetupEditor', handleEditor);
            tinyEditor?.off('input', handleChange);
            tinyEditor?.off('ExecCommand', handleChange);
        };
    };

    const updateJavascript = async (javascript: string) => {
        const fragment = (await require('core/fragment')) as CoreFragment;
        jsNode?.remove();
        await tick();
        jsNode = document.createElement('script');
        jsNode.setAttribute('type', 'text/javascript');
        jsNode.innerHTML = fragment.processCollectedJavascript(javascript);
        document.head.append(jsNode);
    };

    const handleCourseChange = (id?: number) => {
        course = $store.courses.find((course) => course.id == id) || $store.courses[0];
        save(true);
    };

    const handleRecipientChange = (users: ReadonlyArray<User>, type: RecipientType | null) => {
        const newRecipients = new Map(recipients);
        for (const user of users) {
            if (type) {
                newRecipients.set(user.id, { ...user, type, isvalid: true });
            } else {
                newRecipients.delete(user.id);
            }
        }
        const recipientsList = Array.from(newRecipients.values());
        recipientsList.sort((a, b) => a.sortorder.localeCompare(b.sortorder));
        recipients = new Map(recipientsList.map((recipient) => [recipient.id, recipient]));

        save(false);
    };

    const handleRecipientDelete = (user: User) => {
        handleRecipientChange([user], null);
    };

    const save = async (force: boolean) => {
        if (!formNode) {
            return;
        }

        const formData = new FormData(formNode);

        const data: MessageData = {
            courseid: course.id,
            to: Array.from(recipients.values())
                .filter((user) => user.type == 'to')
                .map((user) => user.id),
            cc: Array.from(recipients.values())
                .filter((user) => user.type == 'cc')
                .map((user) => user.id),
            bcc: Array.from(recipients.values())
                .filter((user) => user.type == 'bcc')
                .map((user) => user.id),
            subject,
            content: formData.get('content[text]')?.toString() || '',
            format: parseInt(formData.get('content[format]')?.toString() || '') || 1,
            draftitemid: parseInt(formData.get('content[itemid]')?.toString() || '') || 0,
        };

        await store.updateDraft(data, force);
    };
</script>

<hr class="d-lg-none mt-0 mb-3 mb-sm-2" />
<form
    bind:this={formNode}
    on:submit|preventDefault={() => save(true)}
    class="pt-lg-2 pb-3 px-lg-4"
    class:card={$store.viewportSize >= ViewportSize.LG}
>
    <DraftFormTimeAndLabels {store} {message} />
    <div class="row">
        <div class="form-group col-12 col-xl-5">
            <CourseSelect
                settings={$store.settings}
                strings={$store.strings}
                courses={$store.courses}
                label={$store.strings.course}
                selected={course.id}
                required={true}
                readonly={message.references.length > 0}
                style="filter-left"
                onChange={handleCourseChange}
            />
        </div>
        <div class="col-12 col-xl-7">
            <DraftFormUserSearch {store} {course} {recipients} onChange={handleRecipientChange} />
        </div>
    </div>
    <DraftFormRecipients {store} {recipients} onDelete={handleRecipientDelete} />

    <div class="form-group">
        <input
            type="text"
            id="local-mail-message-form-subject"
            class="form-control"
            class:is-invalid={!subject.trim()}
            placeholder={$store.strings.subject}
            aria-label={$store.strings.subject}
            autocomplete="off"
            maxlength="100"
            bind:value={subject}
            on:input={() => save(false)}
        />
    </div>

    <div class="form-group" on:change={() => save(false)}>
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html form.editorhtml}
    </div>
    <div class="form-group">
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html form.filemanagerhtml}
    </div>

    {#if Array.from(recipients.values()).some((user) => !user.isvalid)}
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle mr-2" />
            {$store.strings.errorinvalidrecipients}
        </div>
    {/if}
    {#if recipients.size == 0}
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle mr-2" />
            {$store.strings.erroremptyrecipients}
        </div>
    {:else if recipients.size > $store.settings.maxrecipients}
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle mr-2" />
            {replaceStringParams(
                $store.strings.errortoomanyrecipients,
                $store.settings.maxrecipients,
            )}
        </div>
    {/if}
    {#if !subject.trim()}
        <div class="alert alert-danger">
            <i class="fa fa-exclamation-circle mr-2" />
            {$store.strings.erroremptysubject}
        </div>
    {/if}
    <div class="d-flex justify-content-end align-items-center">
        <SendButton {store} />
    </div>
</form>

{#if message.references.length > 0}
    <div class="alert alert-secondary mt-4 mb-4 text-center">
        {$store.strings.references}
    </div>
    {#each message.references as reference (reference.id)}
        <MessageReference strings={$store.strings} {reference} />
    {/each}
{/if}
