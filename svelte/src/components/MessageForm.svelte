<svelte:options immutable={true} />

<script lang="ts">
    import { onDestroy, onMount } from 'svelte';
    import { require, type CoreFragment } from '../lib/amd';
    import {
        type Message,
        type MessageData,
        type MessageForm,
        type Recipient,
        type RecipientType,
        type User,
    } from '../lib/services';
    import { ViewSize, type Store } from '../lib/store';
    import CourseSelect from './CourseSelect.svelte';
    import MessageFormRecipients from './MessageFormRecipients.svelte';
    import MessageFormUserSearch from './MessageFormUserSearch.svelte';

    export let store: Store;
    export let message: Message;
    export let form: MessageForm;

    let jsNode: Element | undefined;
    let formNode: HTMLFormElement | undefined;
    let recipients: ReadonlyMap<number, Recipient> = new Map();

    $: courseid = message.course.id;
    $: subject = message.subject;
    $: recipients = new Map(message.recipients.map((user) => [user.id, user]));

    onMount(async () => {
        const fragment = (await require('core/fragment')) as CoreFragment;
        jsNode = document.createElement('script');
        jsNode.setAttribute('type', 'text/javascript');
        jsNode.innerHTML = fragment.processCollectedJavascript(form?.javascript || '');
        document.head.append(jsNode);
    });

    onDestroy(() => {
        jsNode?.remove();
    });

    const handleCourseChange = (id?: number) => {
        courseid = id || $store.courses[0].id;
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
        recipients = newRecipients;
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
            courseid,
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

<hr class="d-lg-none my-2" />
<form
    bind:this={formNode}
    on:submit|preventDefault={() => save(true)}
    class="py-2 py-3 px-lg-4"
    class:card={$store.viewSize >= ViewSize.LG}
>
    <div class="row">
        <div class="form-group col-12 col-xl-5">
            <CourseSelect
                {store}
                label={$store.strings.course}
                selected={courseid}
                required={true}
                readonly={message.references.length > 0}
                onChange={handleCourseChange}
            />
        </div>
        <div class="col-12 col-xl-7">
            <MessageFormUserSearch
                {store}
                {courseid}
                {recipients}
                onChange={handleRecipientChange}
            />
        </div>
    </div>
    <MessageFormRecipients {store} {recipients} onDelete={handleRecipientDelete} />

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

    <div class="form-group" on:input={() => save(false)}>
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html form.editorhtml}
    </div>
    <div class="form-group">
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html form.filemanagerhtml}
    </div>
</form>
