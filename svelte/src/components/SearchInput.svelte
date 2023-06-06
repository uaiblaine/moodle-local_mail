<svelte:options immutable={true} />

<script lang="ts">
    import { jQueryEvents } from '../actions/jQueryEvents';
    import type { Store } from '../lib/store';

    export let store: Store;

    let dropdownNode: HTMLElement;
    let senderNode: HTMLElement;

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

    $: content = $store.params.query?.content || '';
    $: sender = $store.params.query?.sender || '';
    $: recipients = $store.params.query?.recipients || '';
    $: unread = $store.params.query?.unread || false;
    $: attachments = $store.params.query?.attachments || false;
    $: date = dateFromTimestamp($store.params.query?.time || 0);

    $: advancedEnabled = Boolean(
        $store.params.query?.sender ||
            $store.params.query?.recipients ||
            $store.params.query?.unread ||
            $store.params.query?.attachments ||
            $store.params.query?.time,
    );
    $: searchEnabled = Boolean($store.params.query?.content || advancedEnabled);
    $: submitEnabled = Boolean(content || sender || recipients || unread || attachments || date);

    const cancel = () => {
        store.navigate({
            ...$store.params,
            query: {
                ...$store.params.query,
                content: undefined,
                sender: undefined,
                recipients: undefined,
                unread: undefined,
                attachments: undefined,
                time: undefined,
            },
        });
        window.jQuery(dropdownNode).dropdown('hide');
    };

    const submit = () => {
        store.search({
            startid: undefined,
            content,
            sender,
            recipients,
            unread,
            attachments,
            time: timestampFromDate(date),
        });
        window.jQuery(dropdownNode).dropdown('hide');
    };

    const handleKeypress = (event: KeyboardEvent) => {
        if (event.key == 'Enter') {
            event.preventDefault();
            submit();
        }
    };
</script>

<form
    class="local-mail-search-input position-relative"
    on:submit|preventDefault={() => submit}
    use:jQueryEvents={{
        'shown.bs.dropdown': () => {
            senderNode.focus();
        },
    }}
>
    <div
        class="position-absolute h-100 d-flex align-items-center px-2"
        class:text-primary={searchEnabled}
        style="top: 0; left: 0"
    >
        <i class="fa fa-fw fa-search" aria-hidden="true" />
    </div>

    <input
        type="text"
        class="form-control px-5"
        placeholder={$store.strings.search}
        aria-label={$store.strings.search}
        bind:value={content}
        on:keypress={handleKeypress}
    />
    <button
        data-toggle="dropdown"
        data-reference="parent"
        aria-expanded="false"
        class="btn position-absolute h-100 d-flex align-items-center px-2"
        class:text-primary={advancedEnabled}
        style="top: 0; right: 0"
        title={$store.strings.advsearch}
        bind:this={dropdownNode}
    >
        <i class="fa fa-fw fa-sliders" aria-hidden="true" />
    </button>
    <div class="dropdown-menu dropdown-menu-right p-3">
        <div class="form-group">
            <label for="local-mail-search-input-semder">
                {$store.strings.from}
            </label>
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-sender"
                bind:value={sender}
                bind:this={senderNode}
            />
        </div>
        <div class="form-group">
            <label for="local-mail-search-input-recipients">
                {$store.strings.to}
            </label>
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-recipients"
                bind:value={recipients}
            />
        </div>
        <div class="form-group">
            <label for="local-mail-search-input-date">
                {$store.strings.filterbydate}
            </label>
            <input
                type="date"
                class="form-control"
                id="local-mail-search-input-date"
                bind:value={date}
            />
        </div>
        <div class="form-group">
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="local-mail-search-input-unread"
                    bind:checked={unread}
                />
                <label class="form-check-label" for="local-mail-search-input-unread">
                    {$store.strings.searchbyunread}
                </label>
            </div>
        </div>
        <div class="form-group">
            <div class="form-check">
                <input
                    class="form-check-input"
                    type="checkbox"
                    id="local-mail-search-input-attachments"
                    bind:checked={attachments}
                />
                <label class="form-check-label" for="local-mail-search-input-attachments">
                    {$store.strings.searchbyattach}
                </label>
            </div>
        </div>
        <div class="d-flex justify-content-between">
            <input
                type="button"
                class="btn btn-secondary"
                on:click={() => cancel()}
                value={$store.strings.cancel}
            />
            <input
                type="submit"
                disabled={!submitEnabled}
                class="btn btn-primary"
                on:click={() => submit()}
                value={$store.strings.search}
            />
        </div>
    </div>
</form>

<style>
    .local-mail-search-input {
        width: 100%;
        max-width: 100;
    }
</style>
