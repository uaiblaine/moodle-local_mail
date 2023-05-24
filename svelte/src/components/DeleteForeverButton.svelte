<svelte:options immutable={true} />

<script lang="ts">
    import ConfirmationModal from './ConfirmationModal.svelte';
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
</script>

<button
    type="button"
    class="local-mail-action-delete-forever btn btn-secondary"
    class:disabled={!$store.selectedIds.size}
    disabled={!$store.selectedIds.size}
    title={$store.strings.deleteforever}
    data-toggle="modal"
    data-target="#local-mail-action-delete-forever-modal"
>
    <i class="fa fa-fw fa-trash" />
</button>

<ConfirmationModal
    id="local-mail-action-delete-forever-modal"
    title={$store.strings.deleteforever}
    body={replaceStringParams($store.strings.messagesdeleteconfirm, $store.selectedIds.size)}
    cancelText={$store.strings.cancel}
    confirmText={$store.strings.deleteforever}
    confirmCallback={() =>
        store.setDeleted(
            Array.from($store.selectedIds.values()),
            DeletedStatus.DeletedForever,
            true,
        )}
/>
