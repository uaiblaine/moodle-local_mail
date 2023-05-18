import { get, writable } from 'svelte/store';
import { callServices } from './services';
import type { Menu, MessageList, Preferences } from './services';
import { getViewParams, setViewParams } from './url';
import type { ViewParams } from './url';

export interface State {
    readonly userid: number;
    readonly preferences: Preferences;
    readonly strings: Readonly<{ [id: string]: string }>;
    readonly params: ViewParams;
    readonly menu: Menu;
    readonly list: MessageList;
    readonly selected: Readonly<{ [id: number]: boolean }>;
    readonly loading: boolean;
}

export type SelectAllType = 'all' | 'none' | 'read' | 'unread' | 'starred' | 'unstarred';

export async function createStore() {
    let currentActionId = 0;

    const [{ userid, preferences, strings }] = await callServices([{ methodname: 'get_info' }]);

    const { subscribe, update } = writable<State>({
        userid,
        preferences,
        strings,
        params: getViewParams(),
        menu: { unread: 0, drafts: 0, labels: [], courses: [] },
        list: { totalcount: 0, messages: [] },
        selected: {},
        loading: true,
    });

    const store = {
        subscribe,

        get(): State {
            return get(this);
        },

        async navigate(params: ViewParams) {
            const actionId = ++currentActionId;

            update((state) => ({ ...state, loading: true }));

            const [menu, list] = await callServices([
                {
                    methodname: 'get_menu',
                },
                {
                    methodname: 'get_index',
                    type: params.type,
                    itemid:
                        params.type == 'course'
                            ? params.courseid
                            : params.type == 'label'
                            ? params.labelid
                            : 0,
                    offset: params.offset || 0,
                    limit: preferences.perpage,
                },
            ]);

            if (list.messages.length == 0 && params.offset > 0) {
                const perPage = store.get().preferences.perpage;
                const lastPage = Math.max(0, Math.floor((list.totalcount - 1) / perPage));
                return await store.navigate({ ...params, offset: lastPage * perPage });
            }

            console.log(params);
            if (actionId === currentActionId) {
                update((state) => ({
                    ...state,
                    params,
                    menu,
                    list,
                    selected: Object.fromEntries(
                        list.messages.map((m) => [m.id, state.selected[m.id]]),
                    ),
                    loading: false,
                }));
                setViewParams(params);
            }
        },

        selectAll(type: SelectAllType) {
            update((state) => ({
                ...state,
                selected: Object.fromEntries(
                    state.list.messages.map((message) => [
                        message.id,
                        type == 'all' ||
                            (type == 'read' && !message.unread) ||
                            (type == 'unread' && message.unread) ||
                            (type == 'starred' && message.starred) ||
                            (type == 'unstarred' && !message.starred),
                    ]),
                ),
            }));
        },

        toggleSelected(id: number) {
            update((state) => ({
                ...state,
                selected: { ...state.selected, [id]: !state.selected[id] },
            }));
        },

        toggleStarred(id: number) {
            update((state) => {
                const messages = state.list.messages.map((message) => {
                    if (message.id == id) {
                        const starred = !message.starred;
                        callServices([{ methodname: 'set_starred', id, starred }]);
                        return { ...message, starred: !message.starred };
                    } else {
                        return message;
                    }
                });
                return { ...state, list: { ...state.list, messages } };
            });
        },
    };

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
