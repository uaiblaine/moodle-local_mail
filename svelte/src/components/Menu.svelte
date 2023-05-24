<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import MenuItem from './MenuItem.svelte';

    export let store: Store;
</script>

<div class="list-group">
    <MenuItem
        {store}
        icon="fa-inbox"
        text={$store.strings.inbox}
        count={$store.menu.unread}
        params={{ type: 'inbox' }}
    />
    <MenuItem
        {store}
        icon="fa-star"
        text={$store.strings.starredmail}
        params={{ type: 'starred' }}
    />
    <MenuItem
        {store}
        icon="fa-paper-plane"
        text={$store.strings.sentmail}
        params={{ type: 'sent' }}
    />
    <MenuItem
        {store}
        icon="fa-file"
        text={$store.strings.drafts}
        count={$store.menu.drafts}
        params={{ type: 'drafts' }}
    />
    <MenuItem {store} icon="fa-trash" text={$store.strings.trash} params={{ type: 'trash' }} />
    {#each $store.menu.labels as label}
        <MenuItem
            {store}
            icon="fa-tag"
            text={label.name}
            count={label.unread}
            color={label.color}
            params={{ type: 'label', labelid: label.id }}
        />
    {/each}
    {#each $store.menu.courses as course}
        <MenuItem
            {store}
            icon="fa-university"
            text={course.shortname}
            count={course.unread}
            params={{ type: 'course', courseid: course.id }}
        />
    {/each}
</div>
