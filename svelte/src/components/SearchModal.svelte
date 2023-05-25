<svelte:options immutable={true} />

<script lang="ts">
    import type { Store } from '../lib/store';
    import { jQueryEvents } from '../actions/jQueryEvents';

    export let store: Store;

    let contentNode: HTMLElement;

    const dateFromTimestamp = (time: number): string => {
        if (time == 0) {
            return '';
        }
        const date = new Date(time * 1000);
        return [
            String(date.getFullYear()),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0'),
        ].join('-');
    };

    const timestampFromDate = (date: string): number => {
        if (!date) {
            return 0;
        }
        return Math.floor(
            new Date(
                parseInt(date.slice(0, 4)),
                parseInt(date.slice(5, 7)) - 1,
                parseInt(date.slice(8, 10)),
            ).getTime() / 1000,
        );
    };

    $: content = $store.params.search?.content || '';
    $: sender = $store.params.search?.sender || '';
    $: recipients = $store.params.search?.recipients || '';
    $: unread = $store.params.search?.unread || false;
    $: attachments = $store.params.search?.attachments || false;
    $: date = dateFromTimestamp($store.params.search?.time || 0);

    $: advancedOpen = Boolean(
        $store.params.search?.sender ||
            $store.params.search?.recipients ||
            $store.params.search?.unread ||
            $store.params.search?.attachments ||
            $store.params.search?.time,
    );

    $: submitDisabled = !content && !sender && !recipients && !unread && !attachments && !date;

    const cancel = () => {
        window.jQuery('#local-mail-search-modal').modal('hide');
        store.navigate({ ...$store.params, search: undefined });
    };

    const submit = () => {
        if (submitDisabled) {
            return;
        }
        window.jQuery('#local-mail-search-modal').modal('hide');
        store.navigate({
            ...$store.params,
            beforeid: undefined,
            afterid: undefined,
            search: {
                content,
                sender,
                recipients,
                unread,
                attachments,
                time: timestampFromDate(date),
            },
        });
    };
</script>

<div
    class="modal fade"
    id="local-mail-search-modal"
    tabindex="-1"
    aria-labelledby="local-mail-search-modal-title"
    aria-hidden="true"
    use:jQueryEvents={{
        'shown.bs.modal': () => {
            contentNode.focus();
        },
    }}
>
    <div class="modal-dialog">
        <form class="modal-content" on:submit|preventDefault={() => submit()}>
            <div class="modal-header">
                <h5 class="modal-title" id="local-mail-search-modal-title">
                    {$store.strings.search}
                </h5>
                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label={$store.strings.cancel}
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <input
                        type="text"
                        class="form-control"
                        aria-labelledby="local-mail-search-modal-title"
                        bind:this={contentNode}
                        bind:value={content}
                    />
                </div>
                <details open={advancedOpen}>
                    <summary class="mb-2 h6">{$store.strings.advsearch}</summary>
                    <div class="form-group">
                        <label for="local-mail-search-modal-semder">
                            {$store.strings.from}
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="local-mail-search-modal-sender"
                            bind:value={sender}
                        />
                    </div>
                    <div class="form-group">
                        <label for="local-mail-search-modal-recipients">
                            {$store.strings.to}
                        </label>
                        <input
                            type="text"
                            class="form-control"
                            id="local-mail-search-modal-recipients"
                            bind:value={recipients}
                        />
                    </div>
                    <div class="form-group">
                        <label for="local-mail-search-modal-date">
                            {$store.strings.filterbydate}
                        </label>
                        <input
                            type="date"
                            class="form-control"
                            id="local-mail-search-modal-date"
                            bind:value={date}
                        />
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="local-mail-search-modal-unread"
                                bind:checked={unread}
                            />
                            <label class="form-check-label" for="local-mail-search-modal-unread">
                                {$store.strings.searchbyunread}
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="local-mail-search-modal-attachments"
                                bind:checked={attachments}
                            />
                            <label
                                class="form-check-label"
                                for="local-mail-search-modal-attachments"
                            >
                                {$store.strings.searchbyattach}
                            </label>
                        </div>
                    </div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" on:click={() => cancel()}>
                    {$store.strings.cancel}
                </button>
                <button type="submit" disabled={submitDisabled} class="btn btn-primary">
                    {$store.strings.search}
                </button>
            </div>
        </form>
    </div>
</div>

<style>
</style>
