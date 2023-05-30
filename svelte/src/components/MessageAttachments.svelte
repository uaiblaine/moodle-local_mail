<svelte:options immutable={true} />

<script lang="ts">
    import type { Reference } from '../lib/services';
    import { formatSize } from '../lib/utils';

    export let message: Reference;
</script>

<div class="local-mail-message-attachments d-flex flex-wrap mt-3">
    {#each message.attachments as file}
        <div>
            <img
                aria-hidden="true"
                alt={file.filename}
                src={file.iconurl}
                width="24"
                height="24"
                class="mr-2"
            />
            <a href={file.fileurl} class="mr-3">
                {(file.filepath + file.filename).replace(/^\//, '')}
            </a>
            <span class="text-muted">
                {formatSize(file.filesize)}
            </span>
        </div>
    {/each}
</div>
