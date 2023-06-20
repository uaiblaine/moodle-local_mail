<svelte:options immutable={true} />

<script lang="ts">
    import type { Label, Course, Settings, Strings } from '../lib/services';
    import type { ViewParams, ViewTray } from '../lib/store';
    import MenuItem from './MenuItem.svelte';

    export let settings: Settings;
    export let strings: Strings;
    export let unread: number;
    export let drafts: number;
    export let courses: ReadonlyArray<Course>;
    export let labels: ReadonlyArray<Label>;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;
    export let flush = false;

    $: trayVisible = (type: ViewTray): boolean => {
        return settings.globaltrays.includes(type) || params?.tray == type;
    };

    $: courseVisible = (course: Course): boolean => {
        return (
            settings.coursetrays == 'all' ||
            (settings.coursetrays == 'unread' && course.unread > 0) ||
            (params?.tray == 'course' && params?.courseid == course.id)
        );
    };

    $: courseid = params?.tray != 'course' ? params?.courseid : undefined;
</script>

<div class="list-group" class:list-group-flush={flush}>
    <MenuItem
        icon="fa-inbox"
        text={strings.inbox}
        count={unread}
        params={{ tray: 'inbox', courseid }}
        active={params?.tray == 'inbox'}
        {onClick}
    />
    {#if trayVisible('starred')}
        <MenuItem
            icon="fa-star"
            text={strings.starredmail}
            params={{ tray: 'starred', courseid }}
            active={params?.tray == 'starred'}
            {onClick}
        />
    {/if}
    {#if trayVisible('sent')}
        <MenuItem
            icon="fa-paper-plane"
            text={strings.sentmail}
            params={{ tray: 'sent', courseid }}
            active={params?.tray == 'sent'}
            {onClick}
        />
    {/if}
    {#if trayVisible('drafts')}
        <MenuItem
            icon="fa-file"
            text={strings.drafts}
            count={drafts}
            params={{ tray: 'drafts', courseid }}
            active={params?.tray == 'drafts'}
            {onClick}
        />
    {/if}
    {#if trayVisible('trash')}
        <MenuItem
            icon="fa-trash"
            text={strings.trash}
            params={{ tray: 'trash', courseid }}
            active={params?.tray == 'trash'}
            {onClick}
        />
    {/if}
    {#each labels as label (label.id)}
        <MenuItem
            icon="fa-tag"
            text={label.name}
            count={label.unread}
            color={label.color}
            params={{ tray: 'label', labelid: label.id, courseid }}
            active={params?.tray == 'label' && params?.labelid == label.id}
            {onClick}
        />
    {/each}
    {#each courses as course (course.id)}
        {#if courseVisible(course)}
            <MenuItem
                icon="fa-university"
                text={settings.coursetraysname == 'fullname' ? course.fullname : course.shortname}
                count={course.unread}
                params={{ tray: 'course', courseid: course.id }}
                active={params?.tray == 'course' && params?.courseid == course.id}
                {onClick}
            />
        {/if}
    {/each}
</div>
