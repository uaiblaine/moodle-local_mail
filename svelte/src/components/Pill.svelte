<svelte:options immutable={true} />

<script lang="ts">
    export let text: string;
    export let color = 'default';
</script>

<span class="local-mail-pill local-mail-pill-{color} badge d-shrink-0 mr-2">
    {text}
</span>

<style>
    .local-mail-pill {
        font-size: 100%;
        font-weight: normal;
        color: black;
        background: var(--secondary);
    }
    .local-mail-pill-lightgray {
        background: #999;
        color: white;
    }
    .local-mail-pill-lightred {
        background: #e66550;
        color: white;
    }
    .local-mail-pill-lightorange {
        background: #ffbc6b;
    }
    .local-mail-pill-lighbrown {
        background: #e9b96e;
    }
    .local-mail-pill-lightyellow {
        background: #fdee7c;
    }
    .local-mail-pill-lightgreen {
        background: #b3e622;
    }
    .local-mail-pill-lightblue {
        background: #6d9eeb;
    }
    .local-mail-pill-lightpurple {
        background: #b694e8;
        color: white;
    }
    .local-mail-pill-gray {
        background: #555;
        color: white;
    }
    .local-mail-pill-red {
        background: #c00;
        color: white;
    }
    .local-mail-pill-orange {
        background: #f57900;
        color: white;
    }
    .local-mail-pill-brown {
        background: #c17d11;
        color: black;
    }
    .local-mail-pill-yellow {
        background: #edd400;
        color: black;
    }
    .local-mail-pill-green {
        background: #73d216;
        color: black;
    }
    .local-mail-pill-blue {
        background: #005dd6;
        color: white;
    }
    .local-mail-pill-purple {
        background: #b100cc;
        color: white;
    }
    .local-mail-pill-black {
        background: #05181e;
        color: white;
    }
    .local-mail-pill-darkred {
        background: #8b1818;
        color: white;
    }
    .local-mail-pill-darkorange {
        background: #ad5f1f;
        color: white;
    }
    .local-mail-pill-darkbrown {
        background: #8f5902;
        color: white;
    }
    .local-mail-pill-darkyellow {
        background: #a58c1d;
        color: white;
    }
    .local-mail-pill-darkgreen {
        background: #4d8618;
        color: white;
    }
    .local-mail-pill-darkblue {
        background: #19488f;
        color: white;
    }
    .local-mail-pill-darkpurple {
        background: #6d1782;
        color: white;
    }
</style>
