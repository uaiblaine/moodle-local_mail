<svelte:options immutable={true} />

<script lang="ts">
    import type { Menu, MenuCourse, Settings, Strings } from '../lib/services';
    import type { ViewParams, ViewType } from '../lib/store';
    import MenuItem from './MenuItem.svelte';

    export let settings: Settings;
    export let strings: Strings;
    export let menu: Menu;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;
    export let flush = false;

    $: trayVisible = (type: ViewType): boolean => {
        return settings.globaltrays.includes(type) || params?.type == type;
    };

    $: courseVisible = (course: MenuCourse): boolean => {
        return (
            settings.coursetrays == 'all' ||
            (settings.coursetrays == 'unread' && course.unread > 0) ||
            (params?.type == 'course' && params?.courseid == course.id)
        );
    };
</script>

<div class="list-group" class:list-group-flush={flush}>
    <MenuItem
        icon="fa-inbox"
        text={strings.inbox}
        count={menu.unread}
        params={{ type: 'inbox' }}
        active={params?.type == 'inbox'}
        {onClick}
    />
    {#if trayVisible('starred')}
        <MenuItem
            icon="fa-star"
            text={strings.starredmail}
            params={{ type: 'starred' }}
            active={params?.type == 'starred'}
            {onClick}
        />
    {/if}
    {#if trayVisible('sent')}
        <MenuItem
            icon="fa-paper-plane"
            text={strings.sentmail}
            params={{ type: 'sent' }}
            active={params?.type == 'sent'}
            {onClick}
        />
    {/if}
    {#if trayVisible('drafts')}
        <MenuItem
            icon="fa-file"
            text={strings.drafts}
            count={menu.drafts}
            params={{ type: 'drafts' }}
            active={params?.type == 'drafts'}
            {onClick}
        />
    {/if}
    {#if trayVisible('trash')}
        <MenuItem
            icon="fa-trash"
            text={strings.trash}
            params={{ type: 'trash' }}
            active={params?.type == 'trash'}
            {onClick}
        />
    {/if}
    {#each menu.labels as label (label.id)}
        <MenuItem
            icon="fa-tag"
            text={label.name}
            count={label.unread}
            color={label.color}
            params={{ type: 'label', labelid: label.id }}
            active={params?.type == 'label' && params?.labelid == label.id}
            {onClick}
        />
    {/each}
    {#each menu.courses as course (course.id)}
        {#if courseVisible(course)}
            <MenuItem
                icon="fa-university"
                text={settings.coursetraysname == 'fullname' ? course.fullname : course.shortname}
                count={course.unread}
                params={{ type: 'course', courseid: course.id }}
                active={params?.type == 'course' && params?.courseid == course.id}
                {onClick}
            />
        {/if}
    {/each}
</div>
