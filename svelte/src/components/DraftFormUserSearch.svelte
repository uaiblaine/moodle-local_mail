<!--
SPDX-FileCopyrightText: 2023 Proyecto UNIMOODLE <direccion.area.estrategia.digital@uva.es>

SPDX-License-Identifier: GPL-3.0-or-later
-->
<svelte:options immutable={true} />

<script lang="ts">
    import { blur } from '../actions/blur';
    import { truncate } from '../actions/truncate';
    import { callServices, type ServiceRequest } from '../lib/services';
    import {
        GroupMode,
        RecipientType,
        type Course,
        type Group,
        type Recipient,
        type Role,
        type ServiceError,
        type User,
    } from '../lib/state';
    import type { Store } from '../lib/store';
    import UserPicture from './UserPicture.svelte';

    export let store: Store;
    export let course: Course;
    export let recipients: ReadonlyMap<number, Recipient>;
    export let onChange: (users: ReadonlyArray<User>, type: RecipientType | null) => unknown;

    const DELAY = 500;

    let expanded = false;
    let text = '';
    let inputNode: HTMLElement;
    let loading = false;
    let timeoutId: number | undefined;
    let roles: ReadonlyArray<Role> = [];
    let groups: ReadonlyArray<Group> = [];
    let users: ReadonlyArray<User> = [];
    let tooManyUsers = false;
    let oldCourseId = course.id;
    let roleid = 0;
    let groupid = 0;

    // Reset role and group when course changes.
    $: if (oldCourseId != course.id) {
        oldCourseId = course.id;
        roleid = 0;
        groupid = 0;
    }

    const handleToggleClick = async () => {
        if (expanded) {
            text = '';
            expanded = false;
            window.clearTimeout(timeoutId);
        } else {
            search(false);
            inputNode.focus();
        }
    };

    const search = async (throttle: boolean) => {
        const limit = $store.settings.usersearchlimit;

        loading = true;
        window.clearTimeout(timeoutId);

        timeoutId = window.setTimeout(
            async () => {
                const requests: ServiceRequest[] = [
                    {
                        methodname: 'get_roles',
                        courseid: course.id,
                    },
                    {
                        methodname: 'get_groups',
                        courseid: course.id,
                    },
                    {
                        methodname: 'search_users',
                        query: {
                            courseid: course.id,
                            fullname: text,
                            roleid,
                            groupid,
                        },
                        limit: limit + 1,
                    },
                ];
                let responses: unknown[];
                try {
                    responses = await callServices(requests);
                } catch (error) {
                    store.setError(error as ServiceError);
                    loading = false;
                    expanded = false;
                    tooManyUsers = false;
                    users = [];
                    return;
                }
                users = responses.pop() as ReadonlyArray<User>;
                tooManyUsers = users.length > limit;
                users = users.slice(0, limit);
                groups = responses.pop() as ReadonlyArray<Group>;
                groupid = groups.find((group) => group.id == groupid)
                    ? groupid
                    : course.groupmode == GroupMode.Separate
                      ? groups[0]?.id || 0
                      : 0;
                roles = responses.pop() as ReadonlyArray<Role>;
                roleid = roles.find((role) => role.id == roleid) ? roleid : 0;
                loading = false;
                expanded = true;
            },
            throttle ? DELAY : 0,
        );
    };

    const handleBlur = () => {
        text = '';
        expanded = false;
    };

    const handleFocus = () => {
        if (!expanded) {
            search(false);
        }
    };
</script>

<div
    class="local-mail-draft-form-user-search-input form-group position-relative"
    use:blur={handleBlur}
>
    <div
        class="position-absolute h-100 d-flex justify-content-center align-items-center px-2"
        style="top: 0; left: 0;"
    >
        <i class="fa fa-fw {loading ? 'fa-spinner fa-pulse' : 'fa-user'}" aria-hidden="true" />
    </div>

    <input
        type="text"
        class="form-control px-5"
        class:is-invalid={!recipients.size}
        placeholder={$store.strings.addrecipients}
        aria-label={$store.strings.addrecipients}
        autocomplete="off"
        bind:value={text}
        bind:this={inputNode}
        on:input={() => search(true)}
        on:focus={handleFocus}
    />

    <div class="position-absolute h-100 d-flex align-items-center" style="top: 0; right: 0">
        <button
            type="button"
            aria-expanded={expanded}
            class="btn px-2"
            title={$store.strings.addrecipients}
            on:click|preventDefault={handleToggleClick}
        >
            <i
                class="fa fa-fw {text ? 'fa-times' : expanded ? 'fa-caret-up' : 'fa-caret-down'}"
                aria-hidden="true"
            />
        </button>
    </div>

    {#if expanded}
        <div
            class="local-mail-draft-form-user-search-dropdown dropdown-menu dropdown-menu-left list-group show p-0 w-100"
            style="min-width: 18rem"
        >
            <div class="list-group-item d-sm-flex px-2 py-2">
                <div class="flex-grow-1 mx-2">
                    <select
                        class="form-control text-truncate"
                        bind:value={roleid}
                        on:change={() => search(false)}
                    >
                        <option value={0}>{$store.strings.allroles}</option>
                        {#each roles as role (role.id)}
                            <option value={role.id}>
                                {role.name}
                            </option>
                        {/each}
                    </select>
                </div>
                {#if groups.length > 0}
                    <div class="flex-grow-1 mx-2 mt-2 mt-sm-0">
                        <select
                            class="form-control text-truncate"
                            style="min-width: 0"
                            bind:value={groupid}
                            on:change={() => search(false)}
                        >
                            {#if course.groupmode != GroupMode.Separate}
                                <option value={0}>{$store.strings.allgroups}</option>
                            {/if}
                            {#each groups as group (group.id)}
                                <option value={group.id} class="text-truncate">
                                    {group.name}
                                </option>
                            {/each}
                        </select>
                    </div>
                {/if}
            </div>
            {#if !users.length}
                <div class="list-group-item text-danger">
                    {$store.strings.nousersfound}
                </div>
            {:else if tooManyUsers}
                <div class="list-group-item text-danger">
                    {$store.strings.toomanyusersfound}
                </div>
            {:else}
                <div class="list-group-item d-flex align-items-sm-center p-0">
                    <div class="mx-3 my-2">
                        <UserPicture icon="fa-users" />
                    </div>
                    <div class="d-sm-flex align-items-center flex-grow-1">
                        <div class="py-2 mr-3" use:truncate={$store.strings.allusers}>
                            {$store.strings.allusers}
                        </div>
                        <div class="d-flex ml-auto mr-2">
                            {#each Object.values(RecipientType) as type}
                                {@const all = users.every(
                                    (user) => recipients.get(user.id)?.type == type,
                                )}
                                <button
                                    type="button"
                                    class="btn text-nowrap mr-2 mb-2 mt-sm-2"
                                    class:btn-primary={all}
                                    class:btn-secondary={!all}
                                    aria-pressed={all}
                                    on:click={() => onChange(users, all ? null : type)}
                                >
                                    {$store.strings[type]}
                                </button>
                            {/each}
                        </div>
                    </div>
                </div>
                {#each users as user (user.id)}
                    {@const recipientType = recipients.get(user.id)?.type}
                    <div class="list-group-item d-flex p-0">
                        <div class="mx-3 my-2">
                            <UserPicture {user} />
                        </div>
                        <div class="d-sm-flex flex-grow-1">
                            <div class="py-2 mr-3 align-self-center">
                                {user.fullname}
                            </div>
                            <div class="d-flex ml-auto mr-2 align-self-start">
                                {#each Object.values(RecipientType) as type}
                                    <button
                                        type="button"
                                        class="btn text-nowrap mr-2 mb-2 mt-sm-2"
                                        class:btn-primary={recipientType == type}
                                        class:btn-secondary={recipientType != type}
                                        aria-pressed={recipientType == type}
                                        on:click={() =>
                                            onChange([user], recipientType == type ? null : type)}
                                    >
                                        {$store.strings[type]}
                                    </button>
                                {/each}
                            </div>
                        </div>
                    </div>
                {/each}
            {/if}
        </div>
    {/if}
</div>

<style global>
    .local-mail-draft-form-user-search-input {
        width: 100%;
        max-width: 100%;
    }

    .local-mail-draft-form-user-search-input input.is-invalid {
        background-image: none;
    }

    .local-mail-draft-form-user-search-dropdown {
        max-height: 50vh;
        max-width: 50rem;
        overflow-y: scroll;
    }
</style>
