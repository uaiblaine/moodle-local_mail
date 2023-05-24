<svelte:options immutable={true} />

<script lang="ts">
    export let id: string;
    export let title: string;
    export let body: string;
    export let confirmText: string;
    export let cancelText: string;
    export let confirmCallback: () => any;
</script>

<div class="modal fade" {id} tabindex="-1" aria-labelledby="{id}-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{id}-title">
                    {title}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label={cancelText}>
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                {body}
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    {cancelText}
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    data-dismiss="modal"
                    on:click={() => confirmCallback()}
                >
                    {confirmText}
                </button>
            </div>
        </div>
    </div>
</div>
