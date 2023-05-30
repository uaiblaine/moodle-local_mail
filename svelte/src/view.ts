import View from './components/View.svelte';
import { createStore } from './lib/store';

import './global.css';

async function init() {
    const target = document.getElementById('local_mail_view');
    if (target) {
        new View({ target, props: { store: await createStore() } });
    }
}

init();
