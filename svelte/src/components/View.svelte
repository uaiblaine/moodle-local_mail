<script lang="ts">
    import { call } from '../lib/services';

    let inbox = call({
        methodname: 'get_index',
        type: 'inbox',
        itemid: 0,
        offset: 0,
        limit: 10,
    });
</script>

{#await inbox}
    <div class="card">Loading...</div>
{:then response}
    {#each response.messages as message}
        <p>
            {message.subject}
        </p>
    {/each}
{/await}
