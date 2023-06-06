<svelte:options immutable={true} />

<script lang="ts">
    import ConfirmationModal from './ConfirmationModal.svelte';
    import { DeletedStatus } from '../lib/services';
    import type { Store } from '../lib/store';
    import { replaceStringParams } from '../lib/utils';

    export let store: Store;
    export let transparent = false;
</script>

<button
    type="button"
    class="local-mail-action-delete-forever btn flex-grow-0"
    class:btn-secondary={!transparent}
    class:disabled={!$store.targetMessageIds.size}
    disabled={!$store.targetMessageIds.size}
    title={$store.strings.deleteforever}
    data-toggle="modal"
    data-target="#local-mail-action-delete-forever-modal"
>
    <i class="fa fa-fw fa-trash" />
</button>

<ConfirmationModal
    id="local-mail-action-delete-forever-modal"
    title={$store.strings.deleteforever}
    body={replaceStringParams($store.strings.messagesdeleteconfirm, $store.targetMessageIds.size)}
    cancelText={$store.strings.cancel}
    confirmText={$store.strings.deleteforever}
    confirmCallback={() =>
        store.setDeleted(
            Array.from($store.targetMessageIds.values()),
            DeletedStatus.DeletedForever,
            true,
        )}
/>
