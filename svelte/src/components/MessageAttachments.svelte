<svelte:options immutable={true} />

<script lang="ts">
    import type { Reference } from '../lib/services';
    import { formatSize } from '../lib/utils';

    export let message: Reference;
</script>

<div class="d-sm-flex flex-sm-wrap mr-n5 mt-3 mb-n3">
    {#each message.attachments as file}
        <div class="mr-5 mb-3">
            <img
                aria-hidden="true"
                alt={file.filename}
                src={file.iconurl}
                width="24"
                height="24"
                class="mr-2"
            />
            <a href={file.fileurl} class="mr-2">
                {(file.filepath + file.filename).replace(/^\//, '')}
            </a>
            <span class="text-muted">
                {formatSize(file.filesize)}
            </span>
        </div>
    {/each}
</div>
