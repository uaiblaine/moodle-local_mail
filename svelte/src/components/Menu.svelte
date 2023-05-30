<svelte:options immutable={true} />

<script lang="ts">
    import type { Menu, Strings } from '../lib/services';
    import type { ViewParams } from '../lib/store';
    import MenuItem from './MenuItem.svelte';

    export let menu: Menu;
    export let strings: Strings;
    export let params: ViewParams | undefined = undefined;
    export let onClick: ((params: ViewParams) => void) | undefined = undefined;
    export let flush = false;
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
    <MenuItem
        icon="fa-star"
        text={strings.starredmail}
        params={{ type: 'starred' }}
        active={params?.type == 'starred'}
        {onClick}
    />
    <MenuItem
        icon="fa-paper-plane"
        text={strings.sentmail}
        params={{ type: 'sent' }}
        active={params?.type == 'sent'}
        {onClick}
    />
    <MenuItem
        icon="fa-file"
        text={strings.drafts}
        count={menu.drafts}
        params={{ type: 'drafts' }}
        active={params?.type == 'drafts'}
        {onClick}
    />
    <MenuItem
        icon="fa-trash"
        text={strings.trash}
        params={{ type: 'trash' }}
        active={params?.type == 'trash'}
        {onClick}
    />
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
        <MenuItem
            icon="fa-university"
            text={course.shortname}
            count={course.unread}
            params={{ type: 'course', courseid: course.id }}
            active={params?.type == 'course' && params?.courseid == course.id}
            {onClick}
        />
    {/each}
</div>
