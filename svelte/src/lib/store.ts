import { get, writable } from 'svelte/store';
import {
    callServices,
    DeletedStatus,
    type CreateLabelRequest,
    type DeleteLabelRequest,
    type EmptyTrashRequest,
    type Info,
    type Menu,
    type Message,
    type Preferences,
    type SearchIndexRequest,
    type SearchList,
    type SearchQuery,
    type ServiceError,
    type ServiceRequest,
    type SetDeletedRequest,
    type SetLabelsRequest,
    type SetPreferencesRequest,
    type SetStarredRequest,
    type Settings,
    type SetUnreadRequest,
    type Strings,
    type UpdateLabelRequest,
} from './services';
import { getViewParamsFromUrl, setUrlFromViewParams } from './url';
import { replaceStringParams, sleep } from './utils';

export type ViewType = 'inbox' | 'sent' | 'drafts' | 'starred' | 'course' | 'label' | 'trash';

export interface ViewParams {
    readonly type: ViewType;
    readonly courseid?: number;
    readonly labelid?: number;
    readonly messageid?: number;
    readonly query?: SearchQuery;
}

export enum ViewSize {
    SM = 576,
    MD = 768,
    LG = 992,
    XL = 1200,
}

export interface Toast {
    readonly text: string;
    readonly undo?: () => void;
}

export interface State {
    /* General information fetched only once. */
    readonly userid: number;
    readonly settings: Settings;
    readonly preferences: Preferences;
    readonly strings: Strings;

    /* Parameters of the current view. */
    readonly params: ViewParams;

    /* Data fetched using web services for the current view.  */
    readonly menu: Menu;
    readonly list: SearchList;
    readonly message?: Message;
    readonly messageOffset?: number;

    /* Transient interface state. */
    readonly loading: boolean;
    readonly error?: ServiceError;
    readonly nextPageParams?: ViewParams;
    readonly prevPageParams?: ViewParams;
    readonly selectedMessageIds: ReadonlySet<number>;
    readonly targetMessageIds: ReadonlySet<number>;
    readonly toasts: ReadonlyArray<Toast>;
    readonly viewSize: number;
    readonly navigationId: number;
}

export type SelectAllType = 'all' | 'none' | 'read' | 'unread' | 'starred' | 'unstarred';

export async function createStore(info: Info) {
    let currentActionId = 0;

    const { subscribe, update } = writable<State>({
        /* Info */
        userid: info.userid,
        settings: info.settings,
        preferences: info.preferences,
        strings: info.strings,

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
        selectedMessageIds: new Set(),
        targetMessageIds: new Set(),
        toasts: [],
        navigationId: 0,
        viewSize: 0,
    });

    const store = {
        subscribe,

        get(): State {
            return get(this);
        },

        async callServicesAndRefresh(
            requests: ServiceRequest[],
            newParams?: ViewParams,
            redirect = false,
        ): Promise<any[]> {
            const actionId = ++currentActionId;

            let params = newParams || store.get().params;
            const perpage = store.get().preferences.perpage;

            update((state) => ({ ...state, loading: true }));

            requests.push({
                methodname: 'get_menu',
            });

            const itemid: number =
                params.type == 'course'
                    ? params.courseid || 0
                    : params.type == 'label'
                    ? params.labelid || 0
                    : 0;

            requests.push({
                methodname: 'search_index',
                type: params.type,
                itemid,
                query: {
                    ...params.query,
                    startid: params.query?.startid || params.messageid,
                    limit: perpage,
                },
            });

            if (params.messageid) {
                requests.push({
                    methodname: 'set_unread',
                    messageid: params.messageid,
                    unread: false,
                });
                requests.push({
                    methodname: 'get_message',
                    messageid: params.messageid,
                });
                requests.push({
                    methodname: 'find_offset',
                    type: params.type,
                    itemid,
                    messageid: params.messageid,
                });
            }

            let responses: any[];
            try {
                responses = await callServices(requests);
            } catch (error) {
                store.setError(error as ServiceError);
                return [];
            }

            let messageOffset: number | undefined;
            let message: Message | undefined;
            if (params.messageid) {
                messageOffset = responses.pop() as number;
                message = responses.pop() as Message;
                responses.pop(); // set_unread response.
            }
            let list = responses.pop() as SearchList;
            const menu = responses.pop() as Menu;

            // Check if the course or label exists.
            if (
                (params.type == 'course' && !menu.courses.find((c) => c.id == params.courseid)) ||
                (params.type == 'label' && !menu.labels.find((l) => l.id == params.labelid))
            ) {
                await store.navigate({ type: 'inbox' }, true);
                return responses;
            }

            // In some corner cases, when navigating to the previous page, less messages than than perpage may be fetched.
            // Fetch additional messages to fill the page.
            if (list.messages.length < perpage && list.nextid) {
                const request: SearchIndexRequest = {
                    methodname: 'search_index',
                    type: params.type,
                    itemid,
                    query: {
                        ...params.query,
                        startid: list.nextid,
                        backwards: false,
                        limit: perpage - list.messages.length,
                    },
                };
                let list2: SearchList;
                try {
                    [list2] = await callServices([request]);
                } catch (error) {
                    store.setError(error as ServiceError);
                    return [];
                }
                list = {
                    totalcount: list2.totalcount,
                    messages: list.messages.concat(list2.messages),
                    firstoffset: list.firstoffset,
                    lastoffset: list2.lastoffset,
                    previousid: list.previousid,
                    nextid: list2.nextid,
                };
            }

            // Check if the user has done some other action during the web service calls.
            if (actionId != currentActionId) {
                return responses;
            }

            // Normalize current params.
            params = {
                ...params,
                query: {
                    ...params.query,
                    startid: list.messages.length > 0 ? list.messages[0].id : undefined,
                    backwards: false,
                },
            };

            // Calculate next/previous params.
            let nextPageParams: ViewParams | undefined;
            let prevPageParams: ViewParams | undefined;
            if (message) {
                // Displaying a single message.
                let index = list.messages.findIndex((m) => m.id == message?.id);
                let nextId: number;
                let prevId: number;
                if (index >= 0) {
                    // Message is on the list.
                    nextId =
                        index < list.messages.length - 1
                            ? list.messages[index + 1].id
                            : list.nextid;
                    prevId = index > 0 ? list.messages[index - 1].id : list.previousid;
                } else {
                    // Message not on the list, find closest message.
                    index = list.messages.findIndex(
                        (m) =>
                            m.time <= message!.time &&
                            (m.time < message!.time || m.id < message!.id),
                    );
                    nextId = index < list.messages.length ? list.messages[index].id : list.nextid;
                    prevId = index > 0 ? list.messages[index].id : list.previousid;
                }
                if (nextId) {
                    if (nextId == list.nextid) {
                        nextPageParams = {
                            ...params,
                            messageid: nextId,
                            query: { ...params.query, startid: nextId, backwards: false },
                        };
                    } else {
                        nextPageParams = {
                            ...params,
                            messageid: nextId,
                        };
                    }
                }
                if (prevId) {
                    if (prevId == list.previousid) {
                        prevPageParams = {
                            ...params,
                            messageid: prevId,
                            query: { ...params.query, startid: prevId, backwards: true },
                        };
                    } else {
                        prevPageParams = {
                            ...params,
                            messageid: prevId,
                        };
                    }
                }
            } else {
                // Displaying a list of messages.
                if (list.nextid) {
                    nextPageParams = {
                        ...params,
                        query: {
                            ...params.query,
                            startid: list.nextid,
                            backwards: false,
                        },
                    };
                }
                if (list.previousid) {
                    prevPageParams = {
                        ...params,
                        query: {
                            ...params.query,
                            startid: list.previousid,
                            backwards: true,
                        },
                    };
                }
            }

            // Update state.
            update((state): State => {
                const selectedMessageIds = new Set(
                    list.messages
                        .filter((m) => state.selectedMessageIds.has(m.id))
                        .map((m) => m.id),
                );
                return {
                    ...state,
                    params,
                    menu,
                    list,
                    message,
                    messageOffset,
                    nextPageParams,
                    prevPageParams,
                    selectedMessageIds,
                    targetMessageIds: params.messageid
                        ? new Set([params.messageid])
                        : selectedMessageIds,
                    loading: false,
                };
            });
            setUrlFromViewParams(params, redirect);

            return responses;
        },

        async createLabel(name: string, color: string): Promise<number | undefined> {
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
            await store.callServicesAndRefresh([], getViewParamsFromUrl());
        },

        async navigate(params?: ViewParams, redirect = false) {
            await store.callServicesAndRefresh([], params, redirect);

            // Scroll to top and prevent animations.
            update((state) => ({
                ...state,
                navigationId: state.navigationId + 1,
            }));
        },

        async search(query?: SearchQuery) {
            update((state) => ({
                ...state,
                params: { ...state.params, query },
            }));

            await store.navigate();
        },

        selectAll(type: SelectAllType) {
            update((state) => {
                const selectedMessageIds = new Set(
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
                );
                return {
                    ...state,
                    selectedMessageIds,
                    targetMessageIds: state.params.messageid
                        ? new Set([state.params.messageid])
                        : selectedMessageIds,
                };
            });
        },

        async setDeleted(ids: ReadonlyArray<number>, deleted: DeletedStatus, allowUndo: boolean) {
            const requests = ids.map(
                (id): SetDeletedRequest => ({
                    methodname: 'set_deleted',
                    messageid: id,
                    deleted,
                }),
            );

            // Redirect if deleting message in single view.
            let params: ViewParams = { ...store.get().params, messageid: undefined };
            await store.callServicesAndRefresh(requests, params, true);

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

        async setError(error?: ServiceError) {
            update((state) => ({
                ...state,
                loading: state.loading && !error,
                error,
            }));
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

        setViewportSize(width: number) {
            update((state) => ({
                ...state,
                viewSize: width,
                navigationId: state.navigationId + 1, // Prevent list animations.
            }));
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

            // Scroll to top and prevent animations.
            update((state) => ({
                ...state,
                navigationId: state.navigationId + 1,
            }));
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
            const params: ViewParams = { ...store.get().params, messageid: undefined };

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
            await store.callServicesAndRefresh(requests, params);
        },

        async showToast(toast: Toast) {
            update((state) => ({ ...state, toasts: [toast] }));
            if (toast) {
                await sleep(10000);
                store.hideToast(toast);
            }
        },

        toggleSelected(id: number) {
            update((state) => {
                const selectedMessageIds = new Set(
                    state.list.messages
                        .filter(
                            (message) =>
                                (message.id != id && state.selectedMessageIds.has(message.id)) ||
                                (message.id == id && !state.selectedMessageIds.has(message.id)),
                        )
                        .map((message) => message.id),
                );
                return {
                    ...state,
                    selectedMessageIds,
                    targetMessageIds: state.params.messageid
                        ? new Set([state.params.messageid])
                        : selectedMessageIds,
                };
            });
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

            await store.callServicesAndRefresh([request]);
        },
    };

    await store.init();

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
