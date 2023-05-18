import View from './components/View.svelte';
import { createStore } from './lib/store';

async function init() {
    new View({
        target: document.getElementById('local_mail_view'),
        props: { store: await createStore() },
    });
}

init();

