<svelte:options immutable={true} />

<script lang="ts">
    import { jQueryEvents } from '../actions/jQueryEvents';
    import { type Store } from '../lib/store';

    export let store: Store;

    $: if ($store.error) {
        window.jQuery('#local-mail-error-modal').modal();
    }
</script>

<div
    class="modal fade"
    id="local-mail-error-modal"
    tabindex="-1"
    aria-labelledby="local-mail-error-modal-title"
    aria-hidden="true"
    use:jQueryEvents={{
        'hidden.bs.modal': () => {
            store.setError();
        },
    }}
>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="local-mail-error-modal-title">
                    {$store.error?.message}
                </h5>
                <button
                    type="button"
                    class="close"
                    data-dismiss="modal"
                    aria-label={$store.strings.close}
                >
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            {#if $store.error?.debuginfo || $store.error?.backtrace}
                <div class="modal-body">
                    {#if $store.error?.debuginfo}
                        <p>{$store.error?.debuginfo}</p>
                    {/if}
                    {#if $store.error?.backtrace}
                        <pre>{$store.error?.backtrace}</pre>
                    {/if}
                </div>
            {/if}
        </div>
    </div>
</div>
