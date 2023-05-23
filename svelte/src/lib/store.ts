import { get, writable } from 'svelte/store';
import {
    callServices,
    DeletedStatus,
    type EmptyTrashRequest,
    type Menu,
    type MessageList,
    type MessageListItem,
    type Preferences,
    type ServiceRequest,
    type SetDeletedRequest,
    type SetLabelsRequest,
    type SetStarredRequest,
    type SetUnreadRequest,
} from './services';
import { getViewParams, setViewParams } from './url';
import type { ViewParams } from './url';
import { replaceStringParams, sleep } from './utils';

export interface Toast {
    readonly text: string;
    readonly undo?: () => void;
}

export interface State {
    readonly userid: number;
    readonly preferences: Preferences;
    readonly strings: Readonly<{ [id: string]: string }>;
    readonly params: ViewParams;
    readonly menu: Menu;
    readonly messageList: MessageList;
    readonly selectedMessages: ReadonlyMap<number, MessageListItem>;
    readonly toasts: ReadonlyArray<Toast>;
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
        params: {} as ViewParams,
        menu: { unread: 0, drafts: 0, labels: [], courses: [] },
        messageList: { totalcount: 0, messages: [] },
        selectedMessages: new Map(),
        toasts: [],
        loading: true,
    });

    const callServicesAndRefresh = async (requests: ServiceRequest[], params: ViewParams) => {
        const actionId = ++currentActionId;

        update((state) => ({ ...state, loading: true }));

        const responses = await callServices([
            ...requests,
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

        const list = responses.pop() as MessageList;
        const menu = responses.pop() as Menu;

        if (list.messages.length == 0 && params.offset > 0) {
            const perPage = store.get().preferences.perpage;
            const lastPage = Math.max(0, Math.floor((list.totalcount - 1) / perPage));
            return await store.navigate({ ...params, offset: lastPage * perPage });
        }

        if (actionId === currentActionId) {
            update((state) => ({
                ...state,
                params,
                menu,
                messageList: list,
                selectedMessages: new Map(
                    list.messages
                        .filter((message) => state.selectedMessages.has(message.id))
                        .map((message) => [message.id, message]),
                ),
                loading: false,
            }));
            setViewParams(params);
        }
    };

    await callServicesAndRefresh([], getViewParams());

    const store = {
        subscribe,

        get(): State {
            return get(this);
        },

        async showToast(toast: Toast) {
            update((state) => ({ ...state, toasts: [toast] }));
            if (toast) {
                await sleep(10000);
                store.hideToast(toast);
            }
        },

        hideToast(toast: Toast) {
            update((state) => ({
                ...state,
                toasts: state.toasts.filter((t) => t != toast),
            }));
        },

        async undo(toast: Toast) {
            if (toast.undo) {
                await toast.undo();
                store.hideToast(toast);
            }
        },

        async emptyTrash() {
            update((state) => ({
                ...state,
                messageList: {
                    ...state.messageList,
                    messages: state.messageList.messages.filter((message) => !message.deleted),
                },
            }));
            const request: EmptyTrashRequest = {
                methodname: 'empty_trash',
            };
            await callServicesAndRefresh([request], getViewParams());
        },

        async navigate(params: ViewParams = getViewParams()) {
            await callServicesAndRefresh([], params);
        },

        selectAll(type: SelectAllType) {
            update((state) => ({
                ...state,
                selectedMessages: new Map(
                    state.messageList.messages
                        .filter(
                            (message) =>
                                type == 'all' ||
                                (type == 'read' && !message.unread) ||
                                (type == 'unread' && message.unread) ||
                                (type == 'starred' && message.starred) ||
                                (type == 'unstarred' && !message.starred),
                        )
                        .map((message) => [message.id, message]),
                ),
            }));
        },

        async setDeleted(ids: ReadonlyArray<number>, deleted: DeletedStatus, allowUndo: boolean) {
            update((state) => ({
                ...state,
                messageList: {
                    ...state.messageList,
                    messages: state.messageList.messages
                        .filter((message) => {
                            if (ids.includes(message.id)) {
                                return state.params.type == 'trash'
                                    ? deleted != DeletedStatus.DeletedForever
                                    : deleted == DeletedStatus.NotDeleted;
                            } else {
                                return true;
                            }
                        })
                        .map((message) => {
                            if (ids.includes(message.id)) {
                                return { ...message, deleted: Boolean(deleted) };
                            } else {
                                return message;
                            }
                        }),
                },
            }));

            const requests = ids.map(
                (id): SetDeletedRequest => ({
                    methodname: 'set_deleted',
                    messageid: id,
                    deleted,
                }),
            );

            await callServicesAndRefresh(requests, getViewParams());

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
            console.log({ messageids, added, removed });
            update((state) => ({
                ...state,
                messageList: {
                    ...state.messageList,
                    messages: state.messageList.messages.map((message) => {
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
                },
            }));

            await callServicesAndRefresh(requests, getViewParams());
        },

        async setStarred(messageids: ReadonlyArray<number>, starred: boolean) {
            update((state) => ({
                ...state,
                messageList: {
                    ...state.messageList,
                    messages: state.messageList.messages.map((message) => {
                        if (messageids.includes(message.id)) {
                            return { ...message, starred };
                        } else {
                            return message;
                        }
                    }),
                },
            }));
            const requests = messageids.map(
                (messageid): SetStarredRequest => ({
                    methodname: 'set_starred',
                    messageid,
                    starred,
                }),
            );

            await callServicesAndRefresh(requests, getViewParams());
        },

        async setUnread(messageids: ReadonlyArray<number>, unread: boolean) {
            update((state) => ({
                ...state,
                messageList: {
                    ...state.messageList,
                    messages: state.messageList.messages.map((message) => {
                        if (messageids.includes(message.id)) {
                            return { ...message, unread };
                        } else {
                            return message;
                        }
                    }),
                },
            }));
            const requests = messageids.map(
                (messageid): SetUnreadRequest => ({
                    methodname: 'set_unread',
                    messageid,
                    unread,
                }),
            );
            await callServicesAndRefresh(requests, getViewParams());
        },

        toggleSelected(id: number) {
            update((state) => ({
                ...state,
                selectedMessages: new Map(
                    state.messageList.messages
                        .filter(
                            (message) =>
                                (message.id != id && state.selectedMessages.has(message.id)) ||
                                (message.id == id && !state.selectedMessages.has(message.id)),
                        )
                        .map((message) => [message.id, message]),
                ),
            }));
        },
    };

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
