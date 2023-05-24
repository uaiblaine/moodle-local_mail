import { get, writable } from 'svelte/store';
import {
    callServices,
    DeletedStatus,
    type CreateLabelRequest,
    type DeleteLabelRequest,
    type EmptyTrashRequest,
    type Menu,
    type MessageList,
    type Preferences,
    type ServiceRequest,
    type SetDeletedRequest,
    type SetLabelsRequest,
    type SetPreferencesRequest,
    type SetStarredRequest,
    type SetUnreadRequest,
    type UpdateLabelRequest,
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
    readonly selectedIds: ReadonlySet<number>;
    readonly toasts: ReadonlyArray<Toast>;
    readonly loading: boolean;
}

export type SelectAllType = 'all' | 'none' | 'read' | 'unread' | 'starred' | 'unstarred';

export async function createStore() {
    let currentActionId = 0;

    const { subscribe, update } = writable<State>({
        userid: 0,
        preferences: { perpage: 10, markasread: false },
        strings: {},
        params: {} as ViewParams,
        menu: { unread: 0, drafts: 0, labels: [], courses: [] },
        messageList: { totalcount: 0, messages: [] },
        selectedIds: new Set(),
        toasts: [],
        loading: true,
    });

    const store = {
        subscribe,

        get(): State {
            return get(this);
        },

        async init() {
            const [info] = await callServices([{ methodname: 'get_info' }]);

            update((state) => ({ ...state, ...info }));

            await store.callServicesAndRefresh([], getViewParams());
        },

        async callServicesAndRefresh(
            requests: ServiceRequest[],
            params: ViewParams,
        ): Promise<any[]> {
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
                    limit: store.get().preferences.perpage,
                },
            ]);

            const list = responses.pop() as MessageList;
            const menu = responses.pop() as Menu;

            if (
                (params.type == 'course' && !menu.courses.find((c) => c.id == params.courseid)) ||
                (params.type == 'label' && !menu.labels.find((l) => l.id == params.labelid))
            ) {
                await store.navigate({ type: 'inbox' });
            } else if (list.messages.length == 0 && params.offset > 0) {
                const perPage = store.get().preferences.perpage;
                const lastPage = Math.max(0, Math.floor((list.totalcount - 1) / perPage));
                await store.navigate({ ...params, offset: lastPage * perPage });
            } else if (actionId === currentActionId) {
                update((state) => ({
                    ...state,
                    params,
                    menu,
                    messageList: list,
                    selectedIds: new Set(
                        list.messages
                            .filter((message) => state.selectedIds.has(message.id))
                            .map((message) => message.id),
                    ),
                    loading: false,
                }));
                setViewParams(params);
            }

            return responses;
        },

        async createLabel(name: string, color: string): Promise<number> {
            const request: CreateLabelRequest = {
                methodname: 'create_label',
                name,
                color,
            };

            const responses = await store.callServicesAndRefresh([request], getViewParams());

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
            await store.callServicesAndRefresh([request], getViewParams());
        },

        hideToast(toast: Toast) {
            update((state) => ({
                ...state,
                toasts: state.toasts.filter((t) => t != toast),
            }));
        },

        async navigate(params: ViewParams = getViewParams()) {
            await store.callServicesAndRefresh([], params);
        },

        selectAll(type: SelectAllType) {
            update((state) => ({
                ...state,
                selectedIds: new Set(
                    state.messageList.messages
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

            await store.callServicesAndRefresh(requests, getViewParams());

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

            await store.callServicesAndRefresh(requests, getViewParams());
        },

        async setPreferences(preferences: Partial<Preferences>) {
            update((state) => ({
                ...state,
                preferences: { ...state.preferences, ...preferences },
            }));
            const request: SetPreferencesRequest = {
                methodname: 'set_preferences',
                preferences,
            };
            await store.callServicesAndRefresh([request], getViewParams());
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

            await store.callServicesAndRefresh(requests, getViewParams());
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
            await store.callServicesAndRefresh(requests, getViewParams());
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
                    state.messageList.messages
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

            store.callServicesAndRefresh([request], getViewParams());
        },
    };

    await store.init();

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
