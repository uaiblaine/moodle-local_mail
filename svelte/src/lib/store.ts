import { get, writable } from 'svelte/store';
import {
    callServices,
    DeletedStatus,
    type CountMessagesResponse,
    type Course,
    type CreateLabelRequest,
    type DeleteLabelRequest,
    type EmptyTrashRequest,
    type GetCoursesResponse,
    type GetLabelsResponse,
    type GetMessageResponse,
    type Label,
    type Message,
    type MessageQuery,
    type MessageSummary,
    type Preferences,
    type SearchMessagesResponse,
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
    type MessageForm,
    RecipientType,
    type MessageData,
    type ReplyMessageRequest,
    type ForwardMessageRequest,
} from './services';
import { getViewParamsFromUrl, setUrlFromViewParams } from './url';
import { replaceStringParams, sleep } from './utils';

export type ViewTray = 'inbox' | 'sent' | 'drafts' | 'starred' | 'course' | 'label' | 'trash';

export interface SearchParams {
    readonly content?: string;
    readonly sendername?: string;
    readonly recipientname?: string;
    readonly unread?: boolean;
    readonly withfilesonly?: boolean;
    readonly maxtime?: number;
    readonly startid?: number;
    readonly reverse?: boolean;
}

export interface ViewParams {
    readonly tray: ViewTray;
    readonly courseid?: number;
    readonly labelid?: number;
    readonly messageid?: number;
    readonly offset?: number;
    readonly search?: SearchParams;
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
    readonly incrementalSearchStopId?: number;

    /* Parameters of the current view. */
    readonly params: ViewParams;

    /* Data fetched using web services for the current view.  */
    readonly unread: number;
    readonly drafts: number;
    readonly courses: ReadonlyArray<Course>;
    readonly labels: ReadonlyArray<Label>;
    readonly totalCount: number;
    readonly listMessages: ReadonlyArray<MessageSummary>;
    readonly message?: Message;
    readonly messageOffset?: number;
    readonly nextMessageId?: number;
    readonly prevMessageId?: number;

    /* Data used for editing drafts. */
    readonly draftForm?: MessageForm;
    readonly draftData?: MessageData;
    readonly draftSaved?: boolean;

    /* Transient interface state. */
    readonly loading: boolean;
    readonly error?: ServiceError;
    readonly selectedMessages: ReadonlyMap<number, MessageSummary>;
    readonly toasts: ReadonlyArray<Toast>;
    readonly viewSize: number;
    readonly navigationId: number;
}

export type SelectAllType = 'all' | 'none' | 'read' | 'unread' | 'starred' | 'unstarred';

export interface InitialData {
    readonly userid: number;
    readonly settings: Settings;
    readonly preferences: Preferences;
    readonly strings: Strings;
}

export async function createStore(data: InitialData) {
    let currentActionId = 0;
    let draftTimeoutId = 0;

    const { subscribe, update } = writable<State>({
        /* Info */
        userid: data.userid,
        settings: data.settings,
        preferences: data.preferences,
        strings: data.strings,

        /* Params */
        params: { tray: 'inbox' },

        /* Data */
        unread: 0,
        drafts: 0,
        courses: [],
        labels: [],
        totalCount: 0,
        listMessages: [],

        /* Transient */
        loading: true,
        selectedMessages: new Map(),
        toasts: [],
        navigationId: 0,
        viewSize: 0,
    });

    const store = {
        subscribe,

        get(): State {
            return get(store);
        },

        async callServicesAndRefresh(
            requests: ServiceRequest[],
            newParams?: ViewParams,
            redirect = false,
        ): Promise<unknown[]> {
            const actionId = ++currentActionId;

            const messageid = store.get().message?.id;
            const draftData = store.get().draftData;
            const params = newParams || store.get().params;
            const perpage = store.get().preferences.perpage;

            update((state) => ({ ...state, loading: true }));

            // Save draft.
            if (messageid && draftData) {
                clearTimeout(draftTimeoutId);
                requests.unshift({
                    methodname: 'update_message',
                    messageid,
                    data: draftData,
                });
            }

            // Number of unread messages.
            requests.push({
                methodname: 'count_messages',
                query: { roles: Object.values(RecipientType), unread: true },
            });

            // Number of drafts.
            requests.push({
                methodname: 'count_messages',
                query: { draft: true },
            });

            // Courses.
            requests.push({
                methodname: 'get_courses',
            });

            // Labels.
            requests.push({
                methodname: 'get_labels',
            });

            const query: MessageQuery = {
                courseid: params.courseid,
                labelid: params.tray == 'label' ? params.labelid : undefined,
                draft: params.tray == 'drafts' ? true : params.tray == 'sent' ? false : undefined,
                roles:
                    params.tray == 'inbox'
                        ? ['to', 'cc', 'bcc']
                        : params.tray == 'sent'
                        ? ['from']
                        : undefined,
                starred: params.tray == 'starred' ? true : undefined,
                deleted: params.tray == 'trash',
            };

            // Total count of messages.
            requests.push({
                methodname: 'count_messages',
                query,
            });

            if (params.messageid) {
                // Full message.
                requests.push({
                    methodname: 'get_message',
                    messageid: params.messageid,
                });

                // Next message.
                requests.push({
                    methodname: 'search_messages',
                    query: {
                        ...query,
                        ...params.search,
                        startid: params.messageid,
                        reverse: false,
                    },
                    limit: 1,
                });

                // Previous message.
                requests.push({
                    methodname: 'search_messages',
                    query: {
                        ...query,
                        ...params.search,
                        startid: params.messageid,
                        reverse: true,
                    },
                    limit: 1,
                });

                if (!params.search) {
                    // Offset of the message.
                    requests.push({
                        methodname: 'count_messages',
                        query: {
                            ...query,
                            startid: params.messageid,
                            reverse: true,
                        },
                    });
                }
            } else {
                // List of messages.
                requests.push({
                    methodname: 'search_messages',
                    query: { ...query, ...params.search },
                    offset: params.search ? undefined : params.offset,
                    limit: params.search ? perpage + 1 : perpage,
                });
            }

            let responses: unknown[];
            try {
                responses = await callServices(requests);
            } catch (error) {
                store.setError(error as ServiceError);
                return [];
            }

            let message: Message | undefined;
            let messageOffset: number | undefined;
            let nextMessageId: number | undefined;
            let prevMessageId: number | undefined;
            let listMessages: ReadonlyArray<MessageSummary> = [];

            if (params.messageid) {
                if (!params.search) {
                    messageOffset = responses.pop() as CountMessagesResponse;
                }
                prevMessageId = (responses.pop() as SearchMessagesResponse)[0]?.id;
                nextMessageId = (responses.pop() as SearchMessagesResponse)[0]?.id;
                message = responses.pop() as GetMessageResponse;
            } else {
                listMessages = responses.pop() as SearchMessagesResponse;
                if (params.search) {
                    if (params.search.reverse) {
                        prevMessageId = listMessages[perpage]?.id;
                        nextMessageId = params.search.startid;
                        listMessages = listMessages.slice(0, perpage).reverse();
                    } else {
                        prevMessageId = params.search.startid;
                        nextMessageId = listMessages[perpage]?.id;
                        listMessages = listMessages.slice(0, perpage);
                    }
                }
            }

            const totalCount = responses.pop() as CountMessagesResponse;
            const labels = responses.pop() as GetLabelsResponse;
            const courses = responses.pop() as GetCoursesResponse;
            const drafts = responses.pop() as CountMessagesResponse;
            const unread = responses.pop() as CountMessagesResponse;
            if (messageid && draftData) {
                responses.shift();
            }

            // Check if the course or label exists.
            if (
                (params.tray == 'course' && !courses.find((c) => c.id == params.courseid)) ||
                (params.tray == 'label' && !labels.find((l) => l.id == params.labelid))
            ) {
                await store.navigate({ tray: 'inbox' }, true);
                return responses;
            }

            // Fetch form if message is a draft.
            let draftForm: MessageForm | undefined;
            if (message?.draft && message?.id != messageid) {
                const draftRequests: ServiceRequest[] = [
                    {
                        methodname: 'get_message_form',
                        messageid: message.id,
                    },
                ];
                let draftResponses: unknown[];
                try {
                    draftResponses = await callServices(draftRequests);
                } catch (error) {
                    store.setError(error as ServiceError);
                    return responses;
                }
                draftForm = draftResponses.pop() as MessageForm;
            }

            // Check if the user has done some other action during the web service calls.
            if (actionId != currentActionId) {
                return responses;
            }

            // Update state.
            update((state): State => {
                return {
                    ...state,
                    params,
                    unread,
                    drafts,
                    courses,
                    labels,
                    messageOffset,
                    totalCount,
                    listMessages,
                    message,
                    nextMessageId,
                    prevMessageId,
                    draftForm: message?.id != messageid ? draftForm : state.draftForm,
                    draftData: undefined,
                    draftSaved: message?.id == messageid ? draftData != null : false,
                    selectedMessages: new Map(
                        message
                            ? [[message.id, message]]
                            : state.message
                            ? []
                            : listMessages
                                  .filter((message) => state.selectedMessages.has(message.id))
                                  .map((message) => [message.id, message]),
                    ),
                    loading: false,
                    // Scroll to top and prevent animations if changing page.
                    navigationId:
                        redirect ||
                        params.tray != state.params.tray ||
                        params.courseid != state.params.courseid ||
                        params.labelid != state.params.labelid ||
                        params.messageid != state.params.messageid ||
                        params.offset != state.params.offset
                            ? state.navigationId + 1
                            : state.navigationId,
                };
            });

            // Display draft saved notification if navigated to another page.
            if (messageid && draftData && messageid != message?.id) {
                store.showToast({ text: store.get().strings.draftsaved });
            }

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

            return responses.pop() as number | undefined;
        },

        async deleteLabel(labelid: number) {
            const request: DeleteLabelRequest = {
                methodname: 'delete_label',
                labelid,
            };
            store.callServicesAndRefresh([request], { tray: 'inbox' });
        },

        async emptyTrash() {
            const request: EmptyTrashRequest = {
                methodname: 'empty_trash',
            };
            await store.callServicesAndRefresh([request]);
        },

        async forward(message: Message) {
            const params = store.get().params;

            const request: ForwardMessageRequest = {
                methodname: 'forward_message',
                messageid: message.id,
            };

            const responses = await store.callServicesAndRefresh([request]);

            await store.navigate({
                tray: 'drafts',
                messageid: responses.pop() as number,
                courseid: params.courseid ? message.course.id : undefined,
            });
        },

        hideToast(toast: Toast) {
            update((state) => ({
                ...state,
                toasts: state.toasts.filter((t) => t != toast),
            }));
        },

        async init() {
            const requests: ServiceRequest[] = [];
            if (store.get().settings.incrementalsearch) {
                requests.push({
                    methodname: 'search_messages',
                    query: { deleted: false },
                    offset: store.get().settings.incrementalsearchlimit,
                    limit: 1,
                });
            }

            const responses = await store.callServicesAndRefresh(requests, getViewParamsFromUrl());

            update((state) => ({
                ...state,
                incrementalSearchStopId: (responses.pop() as MessageSummary[] | undefined)?.[0]?.id,
            }));
        },

        async navigate(params?: ViewParams, redirect = false) {
            const requests: ServiceRequest[] = [];
            if (params?.messageid) {
                requests.push({
                    methodname: 'set_unread',
                    messageid: params.messageid,
                    unread: false,
                });
            }
            await store.callServicesAndRefresh(requests, params, redirect);
        },

        async navigateToList(redirect = false) {
            const state = store.get();
            const params: ViewParams = {
                ...state.params,
                messageid: undefined,
                offset: state.params.search
                    ? Math.floor((state.messageOffset || 0) / state.preferences.perpage) *
                      state.preferences.perpage
                    : undefined,
            };

            await store.callServicesAndRefresh([], params, redirect);
        },

        async reply(message: Message, all: boolean) {
            const params = store.get().params;

            const request: ReplyMessageRequest = {
                methodname: 'reply_message',
                messageid: message.id,
                all,
            };

            const responses = await store.callServicesAndRefresh([request]);

            await store.navigate({
                tray: 'drafts',
                messageid: responses.pop() as number,
                courseid: params.courseid ? message.course.id : undefined,
            });
        },

        selectAll(type: SelectAllType) {
            update((state) => {
                return {
                    ...state,
                    selectedMessages: new Map(
                        state.listMessages
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
                };
            });
        },

        async selectCourse(id?: number) {
            const params = store.get().params;
            await store.navigate({
                ...params,
                courseid: id,
                offset: 0,
                messageid: undefined,
                search: params.search
                    ? { ...params.search, startid: undefined, reverse: undefined }
                    : undefined,
            });
        },

        async sendMessage() {
            const params = store.get().params;
            const message = store.get().message;

            if (!message?.draft) {
                return;
            }

            const request: ServiceRequest = {
                methodname: 'send_message',
                messageid: message.id,
            };

            const newParams: ViewParams = {
                tray: 'inbox',
                courseid: params.courseid ? message.course.id : undefined,
            };

            await store.callServicesAndRefresh([request], newParams);

            store.showToast({ text: store.get().strings.messagesent });
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
            const params: ViewParams = { ...store.get().params, messageid: undefined };
            await store.callServicesAndRefresh(requests, params, true);

            if (deleted != DeletedStatus.DeletedForever) {
                const string = deleted
                    ? ids.length > 1
                        ? 'undodeletemany'
                        : 'undodeleteone'
                    : ids.length > 1
                    ? 'undorestoremany'
                    : 'undorestoreone';
                const text = replaceStringParams(store.get().strings[string], ids.length);
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

        async setLabels(added: number[], removed: number[]) {
            store.updateStateMessages((message, state) => {
                if (!state.selectedMessages.has(message.id)) {
                    return message;
                }
                return {
                    ...message,
                    labels: state.labels.filter((label) => {
                        if (added.includes(label.id)) {
                            return true;
                        } else if (removed.includes(label.id)) {
                            return false;
                        } else {
                            return message.labels.findIndex((l) => l.id == label.id) >= 0;
                        }
                    }),
                };
            });

            const requests: SetLabelsRequest[] = [];
            store.get().selectedMessages.forEach((message) => {
                requests.push({
                    methodname: 'set_labels',
                    messageid: message.id,
                    labelids: message.labels.map((label) => label.id),
                });
            });

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
            const params = store.get().params;
            const newParams: ViewParams = {
                ...params,
                offset: params.offset ? Math.trunc(params.offset / perpage) * perpage : undefined,
            };
            const request: SetPreferencesRequest = {
                methodname: 'set_preferences',
                preferences: { perpage },
            };
            await store.callServicesAndRefresh([request], newParams, true);
        },

        async setStarred(messageids: ReadonlyArray<number>, starred: boolean) {
            store.updateStateMessages((message) =>
                messageids.includes(message.id) ? { ...message, starred } : message,
            );

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
            store.updateStateMessages((message) =>
                messageids.includes(message.id) ? { ...message, unread } : message,
            );

            const requests = messageids.map(
                (messageid): SetUnreadRequest => ({
                    methodname: 'set_unread',
                    messageid,
                    unread,
                }),
            );

            const params: ViewParams = { ...store.get().params, messageid: undefined };

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
                return {
                    ...state,
                    selectedMessages: new Map(
                        state.listMessages
                            .filter((message) =>
                                message.id == id
                                    ? !state.selectedMessages.has(message.id)
                                    : state.selectedMessages.has(message.id),
                            )
                            .map((message) => [message.id, message]),
                    ),
                };
            });
        },

        async undo(toast: Toast) {
            if (toast.undo) {
                await toast.undo();
                store.hideToast(toast);
            }
        },

        updateDraft(data: MessageData, force: boolean) {
            const message = store.get().message;
            const prevData = store.get().draftData;
            if (!message) {
                return;
            }

            update((state) => ({
                ...state,
                draftData: data,
                draftSaved: false,
            }));

            const actionId = ++currentActionId;

            let delay = 3000;
            if (force) {
                delay = 0;
            } else if (prevData) {
                delay = Math.max(0, message.time * 1000 + delay - Date.now());
            }
            clearTimeout(draftTimeoutId);

            draftTimeoutId = setTimeout(async () => {
                const requests: ServiceRequest[] = [
                    {
                        methodname: 'update_message',
                        messageid: message.id,
                        data,
                    },
                    {
                        methodname: 'get_message',
                        messageid: message.id,
                    },
                ];

                let responses: unknown[];
                try {
                    responses = await callServices(requests);
                } catch (error) {
                    store.setError(error as ServiceError);
                    return;
                }
                const updatedMessage = responses.pop() as Message;
                if (actionId == currentActionId) {
                    update((state) => ({
                        ...state,
                        message: updatedMessage,
                        draftData: undefined,
                        draftSaved: true,
                    }));
                }
            }, delay);
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

        updateStateMessages(callback: <T extends MessageSummary>(message: T, state: State) => T) {
            update((state) => ({
                ...state,
                listMessages: state.listMessages.map((message) => callback(message, state)),
                message: state.message ? callback(state.message, state) : undefined,
                selectedMessages: new Map(
                    Array.from(state.selectedMessages.entries()).map(([id, message]) => [
                        id,
                        callback(message, state),
                    ]),
                ),
            }));
        },
    };

    await store.init();

    return store;
}

export type Store = Awaited<ReturnType<typeof createStore>>;

export type { Unsubscriber } from 'svelte/store';
