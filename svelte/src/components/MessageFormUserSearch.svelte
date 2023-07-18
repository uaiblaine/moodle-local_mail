<svelte:options immutable={true} />

<script lang="ts">
    import { blur } from '../actions/blur';
    import {
        callServices,
        RecipientType,
        type Group,
        type Recipient,
        type Role,
        type ServiceError,
        type ServiceRequest,
        type User,
    } from '../lib/services';
    import type { Store } from '../lib/store';
    import { truncate } from '../actions/truncate';

    export let store: Store;
    export let courseid: number;
    export let recipients: ReadonlyMap<number, Recipient>;
    export let onChange: (users: ReadonlyArray<User>, type: RecipientType | null) => unknown;

    const LIMIT = 100;
    const DELAY = 500;

    let expanded = false;
    let text = '';
    let inputNode: HTMLElement;
    let loading = false;
    let timeoutId: number | undefined;
    let roles: ReadonlyArray<Role> = [];
    let groups: ReadonlyArray<Group> = [];
    let users: ReadonlyArray<User> = [];
    let moreUsers = false;
    let roleid = 0;
    let groupid = 0;

    const handleToggleClick = async () => {
        if (expanded) {
            text = '';
            roleid = 0;
            groupid = 0;
            expanded = false;
            window.clearTimeout(timeoutId);
        } else {
            search(false);
            inputNode.focus();
        }
    };

    const search = async (throttle: boolean) => {
        loading = true;
        window.clearTimeout(timeoutId);

        timeoutId = window.setTimeout(
            async () => {
                const requests: ServiceRequest[] = [
                    {
                        methodname: 'get_roles',
                        courseid,
                    },
                    {
                        methodname: 'get_groups',
                        courseid,
                    },
                    {
                        methodname: 'search_users',
                        query: {
                            courseid,
                            fullname: text,
                            roleid,
                            groupid,
                        },
                        limit: LIMIT + 1,
                    },
                ];
                let responses: unknown[];
                try {
                    responses = await callServices(requests);
                } catch (error) {
                    store.setError(error as ServiceError);
                    loading = false;
                    expanded = false;
                    moreUsers = false;
                    users = [];
                    return;
                }
                users = responses.pop() as ReadonlyArray<User>;
                moreUsers = users.length > LIMIT;
                users = users.slice(0, LIMIT);
                groups = responses.pop() as ReadonlyArray<Group>;
                groupid = groups.find((group) => group.id == groupid) ? groupid : 0;
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
    class="local-mail-message-form-search-users-input form-group position-relative"
    use:blur={handleBlur}
>
    <div
        class="position-absolute h-100 d-flex justify-content-center align-items-center px-0"
        style="top: 0; left: 0; width: 2.5rem"
    >
        <i class="fa fa-fw {loading ? 'fa-spinner fa-pulse' : 'fa-user'}" aria-hidden="true" />
    </div>

    <input
        type="text"
        class="form-control"
        class:is-invalid={!recipients.size}
        style="padding-left: 2.5rem; padding-right: 2.5"
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
            class="btn px-0"
            title={$store.strings.cancel}
            style="width: 2.5rem"
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
            class="local-mail-message-form-search-users-dropdown dropdown-menu dropdown-menu-left list-group show p-0 w-100"
            style="min-width: 18rem"
        >
            <div class="list-group-item d-sm-flex px-2 py-2">
                <div class="flex-grow-1 mx-2 mb-2 mb-sm-0">
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
                {#if groups.length}
                    <div class="flex-grow-1 mx-2">
                        <select
                            class="form-control text-truncate"
                            style="min-width: 0"
                            bind:value={groupid}
                            on:change={() => search(false)}
                        >
                            <option value={0}>{$store.strings.allgroups}</option>
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
            {:else if moreUsers}
                <div class="list-group-item text-danger">
                    {$store.strings.toomanyusersfound}
                </div>
            {:else}
                <div class="list-group-item d-flex align-items-center px-3 py-2">
                    <div use:truncate={$store.strings.allusers}>
                        {$store.strings.allusers}
                    </div>
                    <div class="d-flex ml-auto">
                        {#each Object.values(RecipientType) as type}
                            {@const all = users.every(
                                (user) => recipients.get(user.id)?.type == type,
                            )}
                            <button
                                type="button"
                                class="btn text-nowrap ml-2"
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
                {#each users as user (user.id)}
                    {@const recipientType = recipients.get(user.id)?.type}
                    <div class="list-group-item d-flex align-items-sm-center p-0">
                        <img
                            aria-hidden="true"
                            alt={user.fullname}
                            src={user.pictureurl}
                            width="35"
                            height="35"
                            class="rounded-circle mx-3 my-2"
                        />
                        <div class="d-sm-flex align-items-center flex-grow-1">
                            <div use:truncate={user.fullname} class="py-2 mr-3">
                                {user.fullname}
                            </div>
                            <div class="d-flex ml-auto mr-2">
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

<style>
    .local-mail-message-form-search-users-input {
        width: 100%;
        max-width: 100%;
    }

    .local-mail-message-form-search-users-input input.is-invalid {
        background-image: none;
    }

    .local-mail-message-form-search-users-dropdown {
        max-height: 50vh;
        max-width: 50rem;
        overflow-y: scroll;
    }
</style>
