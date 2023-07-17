<svelte:options immutable={true} />

<script lang="ts">
    import { RecipientType, type Message } from '../lib/services';
    import { type Store } from '../lib/store';

    export let store: Store;
    export let message: Message;

    $: recipients = (type: string) => {
        return message.recipients.filter((user) => user.type == type);
    };
</script>

<div class="local-mail-message-users d-flex mb-n2">
    <div class="mr-3">
        <img
            aria-hidden="true"
            alt={message.sender.fullname}
            src={message.sender.pictureurl}
            width="35"
            height="35"
            class="rounded-circle"
        />
    </div>
    <div class="d-flex flex-column">
        <div class="mt-1 mb-2">
            <a href={message.sender.profileurl}>
                {message.sender.fullname}
            </a>
        </div>
        {#each Object.values(RecipientType) as type}
            {#if recipients(type).length > 0}
                <div class="mb-2">
                    <span> {$store.strings[type]}: </span>
                    {#each recipients(type) as user, i (user.id)}
                        {#if i > 0}, {/if}
                        <a href={user.profileurl}>{user.fullname}</a>
                    {/each}
                </div>
            {/if}
        {/each}
    </div>
</div>
