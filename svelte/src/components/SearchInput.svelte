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

    $: content = $store.params.search?.content || '';
    $: sendername = $store.params.search?.sendername || '';
    $: recipientname = $store.params.search?.recipientname || '';
    $: unread = $store.params.search?.unread || false;
    $: withfilesonly = $store.params.search?.withfilesonly || false;
    $: maxdate = dateFromTimestamp($store.params.search?.maxtime || 0);

    $: advancedEnabled = Boolean(
        $store.params.search?.sendername ||
            $store.params.search?.recipientname ||
            $store.params.search?.unread ||
            $store.params.search?.withfilesonly ||
            $store.params.search?.maxtime,
    );
    $: searchEnabled = Boolean($store.params.search?.content || advancedEnabled);
    $: submitEnabled = Boolean(content || sendername || recipientname || unread || withfilesonly || maxdate);

    const cancel = () => {
        store.navigate({ ...$store.params, search: undefined });
        window.jQuery(dropdownNode).dropdown('hide');
    };

    const submit = () => {
        store.search({
            content,
            sendername,
            recipientname,
            unread,
            withfilesonly,
            maxtime: timestampFromDate(maxdate),
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
            <label for="local-mail-search-input-sendername">
                {$store.strings.from}
            </label>
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-sendername"
                bind:value={sendername}
                bind:this={senderNode}
            />
        </div>
        <div class="form-group">
            <label for="local-mail-search-input-recipientname">
                {$store.strings.to}
            </label>
            <input
                type="text"
                class="form-control"
                id="local-mail-search-input-recipientname"
                bind:value={recipientname}
            />
        </div>
        <div class="form-group">
            <label for="local-mail-search-input-maxdate">
                {$store.strings.filterbydate}
            </label>
            <input
                type="date"
                class="form-control"
                id="local-mail-search-input-maxdate"
                bind:value={maxdate}
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
                    id="local-mail-search-input-withfilesonly"
                    bind:checked={withfilesonly}
                />
                <label class="form-check-label" for="local-mail-search-input-withfilesonly">
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
