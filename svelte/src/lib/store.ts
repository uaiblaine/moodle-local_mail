import { get, writable } from 'svelte/store';
import {
    callServices,
    DeletedStatus,
    type CreateLabelRequest,
    type DeleteLabelRequest,
    type EmptyTrashRequest,
    type GetMenuRequest,
    type Menu,
    type MessageList,
    type Preferences,
    type SearchIndexRequest,
    type ServiceRequest,
    type SetDeletedRequest,
    type SetLabelsRequest,
    type SetPreferencesRequest,
    type SetStarredRequest,
    type SetUnreadRequest,
    type UpdateLabelRequest,
} from './services';
import { getViewParamsFromUrl, setUrlFromViewParams } from './url';
import { replaceStringParams, sleep } from './utils';

export type ViewType = 'inbox' | 'sent' | 'drafts' | 'starred' | 'course' | 'label' | 'trash';

export interface SearchParams {
    readonly content: string;
    readonly sender?: string;
    readonly recipients?: string;
    readonly unread?: boolean;
    readonly attachments?: boolean;
    readonly time?: number;
}

export interface ViewParams {
    readonly type: ViewType;
    readonly courseid?: number;
    readonly labelid?: number;
    readonly messageid?: number;
    readonly beforeid?: number;
    readonly afterid?: number;
    readonly search?: SearchParams;
}

export interface Toast {
    readonly text: string;
    readonly undo?: () => void;
}

export interface State {
    /* General information fetched only once. */
    readonly userid: number;
    readonly preferences: Preferences;
    readonly strings: Readonly<{ [id: string]: string }>;

    /* Parameters of the current view. */
    readonly params: ViewParams;

    /* Data fetched using web services for the current view.  */
    readonly menu: Menu;
    readonly list: MessageList;

    /* Transient interface state. */
    readonly nextParams?: ViewParams;
    readonly prevParams?: ViewParams;
    readonly selectedIds: ReadonlySet<number>;
    readonly toasts: ReadonlyArray<Toast>;
    readonly loading: boolean;
}

export type SelectAllType = 'all' | 'none' | 'read' | 'unread' | 'starred' | 'unstarred';

export async function createStore() {
    let currentActionId = 0;

    const { subscribe, update } = writable<State>({
        /* Info */
        userid: 0,
        preferences: { perpage: 10, markasread: false },
        strings: {},

        /* Params */
        params: { type: 'inbox' },

        /* Data */
        menu: {
            unread: 0,
            drafts: 0,
            labels: [],
            courses: [],
        },
        list: {
            totalcount: 0,
            messages: [],
            firstoffset: 0,
            lastoffset: 0,
            previousid: 0,
            nextid: 0,
        },

        /* Transient */
        loading: true,
        selectedIds: new Set(),
        toasts: [],
    });

    const store = {
        subscribe,

        get(): State {
            return get(this);
        },

        async callServicesAndRefresh(
            requests: ServiceRequest[],
            params?: ViewParams,
            redirect = false,
        ): Promise<any[]> {
            const actionId = ++currentActionId;

            params = params || store.get().params;
            const perpage = store.get().preferences.perpage;

            update((state) => ({ ...state, loading: true }));

            const menuRequest: GetMenuRequest = { methodname: 'get_menu' };

            const itemid =
                params.type == 'course'
                    ? params.courseid
                    : params.type == 'label'
                    ? params.labelid
                    : 0;
            const listRequest: SearchIndexRequest = {
                methodname: 'search_index',
                type: params.type,
                itemid,
                query: {
                    beforeid: params.beforeid,
                    afterid: params.afterid,
                    limit: perpage,
                    ...params.search
                },
            };

            const responses = await callServices([...requests, menuRequest, listRequest]);

            let list = responses.pop() as MessageList;
            const menu = responses.pop() as Menu;

            // In some corner cases, when navigating backwards, less messages than than perpage may be fetched.
            // Fetch additional messages to fill the page.
            if (list.messages.length < perpage && list.nextid) {
                const listRequest2: SearchIndexRequest = {
                    ...listRequest,
                    query: {
                        ...listRequest.query,
                        beforeid: list.messages[list.messages.length - 1].id,
                        afterid: undefined,
                        limit: perpage - list.messages.length,
                    },
                };
                const [list2] = await callServices([listRequest2]);
                list = {
                    totalcount: list2.totalcount,
                    messages: list.messages.concat(list2.messages),
                    firstoffset: list.firstoffset,
                    lastoffset: list2.lastoffset,
                    previousid: list.previousid,
                    nextid: list2.nextid,
                };
            }

            if (
                (params.type == 'course' && !menu.courses.find((c) => c.id == params.courseid)) ||
                (params.type == 'label' && !menu.labels.find((l) => l.id == params.labelid))
            ) {
                await store.navigate({ type: 'inbox' }, true);
            } else if (actionId === currentActionId) {
                update(
                    (state): State => ({
                        ...state,
                        params: {
                            ...params,
                            beforeid: list.previousid,
                            afterid: undefined,
                        },
                        menu,
                        list,
                        nextParams: list.nextid
                            ? {
                                  ...params,
                                  beforeid: list.messages[list.messages.length - 1].id,
                                  afterid: undefined,
                              }
                            : undefined,
                        prevParams: list.previousid
                            ? {
                                  ...params,
                                  beforeid: undefined,
                                  afterid: list.messages[0]?.id,
                              }
                            : undefined,
                        selectedIds: new Set(
                            list.messages
                                .filter((message) => state.selectedIds.has(message.id))
                                .map((message) => message.id),
                        ),
                        loading: false,
                    }),
                );
                setUrlFromViewParams(params, redirect);
            }

            return responses;
        },

        async createLabel(name: string, color: string): Promise<number> {
            const request: CreateLabelRequest = {
                methodname: 'create_label',
                name,
                color,
            };

            const responses = await store.callServicesAndRefresh([request]);

            return responses.pop();
        },

        async deleteLabel(labelid: number) {
            const request: DeleteLabelRequest = {
                methodname: 'delete_label',
                labelid,
            };
            store.callServicesAndRefresh([request], { type: 'inbox' });
        },

        async emptyTrash() {
            const request: EmptyTrashRequest = {
                methodname: 'empty_trash',
            };
            await store.callServicesAndRefresh([request]);
        },

        hideToast(toast: Toast) {
            update((state) => ({
                ...state,
                toasts: state.toasts.filter((t) => t != toast),
            }));
        },

        async init() {
            const [info] = await callServices([{ methodname: 'get_info' }]);

            update((state) => ({ ...state, ...info }));

            await store.callServicesAndRefresh([], getViewParamsFromUrl());
        },

        async navigate(params: ViewParams, redirect = false) {
            await store.callServicesAndRefresh([], params, redirect);
        },

        selectAll(type: SelectAllType) {
            update((state) => ({
                ...state,
                selectedIds: new Set(
                    state.list.messages
                        .filter(
                            (message) =>
                                type == 'all' ||
                                (type == 'read' && !message.unread) ||
                                (type == 'unread' && message.unread) ||
                                (type == 'starred' && message.starred) ||
                                (type == 'unstarred' && !message.starred),
                        )
                        .map((message) => message.id),
                ),
            }));
        },

        async setDeleted(ids: ReadonlyArray<number>, deleted: DeletedStatus, allowUndo: boolean) {
            const requests = ids.map(
                (id): SetDeletedRequest => ({
                    methodname: 'set_deleted',
                    messageid: id,
                    deleted,
                }),
            );

            await store.callServicesAndRefresh(requests);

            if (deleted != DeletedStatus.DeletedForever) {
                const text = replaceStringParams(
                    store.get().strings[deleted ? 'undodelete' : 'undorestore'],
                    ids.length,
                );
                const undo = () => {
                    store.setDeleted(
                        ids,
                        deleted ? DeletedStatus.NotDeleted : DeletedStatus.Deleted,
                        false,
                    );
                };
                store.showToast({ text, undo: allowUndo ? undo : undefined });
            }
        },

        async setLabels(messageids: number[], added: number[], removed: number[]) {
            const requests: SetLabelsRequest[] = [];

            update((state) => ({
                ...state,
                messages: state.list.messages.map((message) => {
                    if (messageids.includes(message.id)) {
                        const labels = state.menu.labels.filter((label) => {
                            if (added.includes(label.id)) {
                                return true;
                            } else if (removed.includes(label.id)) {
                                return false;
                            } else {
                                return message.labels.findIndex((l) => l.id == label.id) >= 0;
                            }
                        });
                        requests.push({
                            methodname: 'set_labels',
                            messageid: message.id,
                            labelids: labels.map((label) => label.id),
                        });
                        return { ...message, labels };
                    } else {
                        return message;
                    }
                }),
            }));

            await store.callServicesAndRefresh(requests);
        },

        async setPerPage(perpage: number) {
            update((state) => ({
                ...state,
                preferences: { ...state.preferences, perpage },
            }));
            const request: SetPreferencesRequest = {
                methodname: 'set_preferences',
                preferences: { perpage },
            };
            await store.callServicesAndRefresh([request]);
        },

        async setStarred(messageids: ReadonlyArray<number>, starred: boolean) {
            update((state) => ({
                ...state,
                messages: state.list.messages.map((message) => {
                    if (messageids.includes(message.id)) {
                        return { ...message, starred };
                    } else {
                        return message;
                    }
                }),
            }));
            const requests = messageids.map(
                (messageid): SetStarredRequest => ({
                    methodname: 'set_starred',
                    messageid,
                    starred,
                }),
            );

            await store.callServicesAndRefresh(requests);
        },

        async setUnread(messageids: ReadonlyArray<number>, unread: boolean) {
            update((state) => ({
                ...state,
                messages: state.list.messages.map((message) => {
                    if (messageids.includes(message.id)) {
                        return { ...message, unread };
                    } else {
                        return message;
                    }
                }),
            }));
            const requests = messageids.map(
                (messageid): SetUnreadRequest => ({
                    methodname: 'set_unread',
                    messageid,
                    unread,
                }),
            );
            await store.callServicesAndRefresh(requests);
        },

        async showToast(toast: Toast) {
            update((state) => ({ ...state, toasts: [toast] }));
            if (toast) {
                await sleep(10000);
                store.hideToast(toast);
            }
        },

        toggleSelected(id: number) {
            update((state) => ({
                ...state,
                selectedIds: new Set(
                    state.list.messages
                        .filter(
                            (message) =>
                                (message.id != id && state.selectedIds.has(message.id)) ||
                                (message.id == id && !state.selectedIds.has(message.id)),
                        )
                        .map((message) => message.id),
                ),
            }));
        },

        async undo(toast: Toast) {
            if (toast.undo) {
                await toast.undo();
                store.hideToast(toast);
            }
        },

        async updateLabel(labelid: number, name: string, color: string) {
            const request: UpdateLabelRequest = {
                methodname: 'update_label',
                labelid,
                name,
                color,
            };

            store.callServicesAndRefresh([request]);
        },
    };

    await store.init();

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
