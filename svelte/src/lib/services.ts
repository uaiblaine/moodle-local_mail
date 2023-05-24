import { require } from './amd';

export type ServiceRequest =
    | GetInfoRequest
    | SetPreferencesRequest
    | GetMenuRequest
    | GetIndexRequest
    | SearchIndexRequest
    | GetMessageRequest
    | SetUnreadRequest
    | SetStarredRequest
    | SetDeletedRequest
    | EmptyTrashRequest
    | CreateLabelRequest
    | UpdateLabelRequest
    | DeleteLabelRequest
    | SetLabelsRequest;

export interface GetInfoRequest {
    readonly methodname: 'get_info';
}

export interface SetPreferencesRequest {
    readonly methodname: 'set_preferences';
    readonly preferences: Partial<Preferences>;
}

export interface GetMenuRequest {
    readonly methodname: 'get_menu';
}

export interface GetIndexRequest {
    readonly methodname: 'get_index';
    readonly type: 'inbox' | 'drafts' | 'sent' | 'starred' | 'course' | 'label' | 'trash';
    readonly itemid: number;
    readonly offset: number;
    readonly limit: number;
}

export interface SearchIndexRequest {
    readonly methodname: 'search_index';
    readonly type: 'inbox' | 'drafts' | 'sent' | 'starred' | 'course' | 'label' | 'trash';
    readonly query: SearchQuery;
}

export interface SearchQuery {
    readonly beforeid: number;
    readonly afterid: number;
    readonly content: string;
    readonly sender: string;
    readonly recipients: string;
    readonly unread: boolean;
    readonly attachments: boolean;
    readonly time: number;
    readonly limit: number;
}

export interface GetMessageRequest {
    readonly methodname: 'get_message';
    readonly id: number;
}

export interface SetUnreadRequest {
    readonly methodname: 'set_unread';
    readonly messageid: number;
    readonly unread: boolean;
}

export interface SetStarredRequest {
    readonly methodname: 'set_starred';
    readonly messageid: number;
    readonly starred: boolean;
}

export enum DeletedStatus {
    NotDeleted = 0,
    Deleted = 1,
    DeletedForever = 2,
}

export interface SetDeletedRequest {
    readonly methodname: 'set_deleted';
    readonly messageid: number;
    readonly deleted: DeletedStatus;
}

export interface EmptyTrashRequest {
    readonly methodname: 'empty_trash';
}

export interface CreateLabelRequest {
    readonly methodname: 'create_label';
    readonly name: string;
    readonly color: string;
}

export interface UpdateLabelRequest {
    readonly methodname: 'update_label';
    readonly labelid: number;
    readonly name: string;
    readonly color: string;
}

export interface DeleteLabelRequest {
    readonly methodname: 'delete_label';
    readonly labelid: number;
}

export interface SetLabelsRequest {
    readonly methodname: 'set_labels';
    readonly messageid: number;
    readonly labelids: ReadonlyArray<number>;
}

export type ServiceResponse<T> = T extends GetInfoRequest
    ? Info
    : T extends SetPreferencesRequest
    ? void
    : T extends GetMenuRequest
    ? Menu
    : T extends GetIndexRequest
    ? MessageList
    : T extends SearchIndexRequest
    ? MessageList
    : T extends GetMessageRequest
    ? Message
    : T extends SetUnreadRequest
    ? void
    : T extends SetStarredRequest
    ? void
    : T extends SetDeletedRequest
    ? void
    : T extends EmptyTrashRequest
    ? void
    : T extends CreateLabelRequest
    ? number
    : T extends UpdateLabelRequest
    ? void
    : T extends DeleteLabelRequest
    ? void
    : T extends SetLabelsRequest
    ? void
    : unknown;

export interface Info {
    readonly userid: number;
    readonly preferences: Preferences;
    readonly strings: Readonly<{ [id: string]: string }>;
}

export interface Menu {
    readonly unread: number;
    readonly drafts: number;
    readonly courses: ReadonlyArray<MenuCourse>;
    readonly labels: ReadonlyArray<MenuLabel>;
}

export interface Preferences {
    readonly perpage: number;
    readonly markasread: boolean;
}

export interface MenuCourse extends Course {
    readonly id: number;
    readonly shortname: string;
    readonly fullname: string;
    readonly unread: number;
    readonly visible: boolean;
}

export interface MenuLabel {
    readonly id: number;
    readonly name: string;
    readonly color: string;
    readonly unread: number;
}

export interface MessageList {
    readonly totalcount: number;
    readonly messages: ReadonlyArray<MessageListItem>;
}

export interface MessageListItem {
    readonly id: number;
    readonly subject: string;
    readonly attachments: number;
    readonly draft: boolean;
    readonly time: number;
    readonly shorttime: string;
    readonly fulltime: string;
    readonly unread: boolean;
    readonly starred: boolean;
    readonly deleted: boolean;
    readonly course: Course;
    readonly sender: Sender;
    readonly recipients: ReadonlyArray<Recipient>;
    readonly labels: ReadonlyArray<MessageLabel>;
}

export interface Course {
    readonly id: number;
    readonly shortname: string;
    readonly fullname: string;
}

export interface Sender {
    readonly id: number;
    readonly fullname: string;
    readonly pictureurl: string;
}

export interface Recipient {
    readonly id: number;
    readonly fullname: string;
    readonly pictureurl: string;
    readonly type: 'to' | 'cc' | 'bcc';
}

export interface MessageLabel {
    readonly id: number;
    readonly name: string;
    readonly color: string;
}

export interface Message {
    readonly id: number;
    readonly subject: string;
    readonly content: string;
    readonly format: number;
    readonly draft: boolean;
    readonly time: number;
    readonly shorttime: string;
    readonly fulltime: string;
    readonly unread: boolean;
    readonly starred: boolean;
    readonly deleted: boolean;
    readonly course: Course;
    readonly sender: Sender;
    readonly recipients: ReadonlyArray<Recipient>;
    readonly attachments: ReadonlyArray<Attachment>;
    readonly references: ReadonlyArray<Reference>;
    readonly labels: ReadonlyArray<MessageLabel>;
}

export interface Reference {
    readonly id: number;
    readonly subject: string;
    readonly content: string;
    readonly format: number;
    readonly time: number;
    readonly shorttime: string;
    readonly fulltime: string;
    readonly sender: Sender;
    readonly attachments: ReadonlyArray<Attachment>;
}

export interface Attachment {
    readonly filename: string;
    readonly mimetype: string;
    readonly filesize: number;
    readonly fileurl: string;
}

/**
 * Calls one or more web service methods in a single HTTP request.
 *
 * @param requests List of request with method name and arguments.
 * @returns A promise to the web service responses.
 */
export async function callServices<T extends ServiceRequest[] | []>(
    requests: T,
): Promise<{ [P in keyof T]: ServiceResponse<T[P]> }> {
    let ajax = await require('core/ajax');
    try {
        const responses = await Promise.all(
            ajax.call(
                Array.from(requests).map(({ methodname, ...args }) => ({
                    methodname: `local_mail_${methodname}`,
                    args,
                })),
            ),
        );
        return responses as { [P in keyof T]: ServiceResponse<T[P]> };
    } catch (error) {
        let notification = await require('core/notification');
        notification.exception(error);
        throw error;
    }
}
