import { require, type CoreAjax } from './amd';

export type ServiceRequest =
    | SetPreferencesRequest
    | GetCoursesRequest
    | GetLabelsRequest
    | CountMessagesRequest
    | SearchMessagesRequest
    | GetMessageRequest
    | SetUnreadRequest
    | SetStarredRequest
    | SetDeletedRequest
    | EmptyTrashRequest
    | CreateLabelRequest
    | UpdateLabelRequest
    | DeleteLabelRequest
    | SetLabelsRequest
    | GetRolesRequest
    | GetGroupsRequest
    | SearchUsersRequest
    | GetMessageFormRequest
    | CreateMessageRequest
    | ReplyMessageRequest
    | ForwardMessageRequest
    | UpdateMessageRequest
    | SendMessageRequest;

export interface SetPreferencesRequest {
    readonly methodname: 'set_preferences';
    readonly preferences: Partial<Preferences>;
}

export interface GetCoursesRequest {
    readonly methodname: 'get_courses';
}

export type GetCoursesResponse = ReadonlyArray<Course>;

export interface GetLabelsRequest {
    readonly methodname: 'get_labels';
}

export type GetLabelsResponse = ReadonlyArray<Label>;

export interface MessageQuery {
    readonly courseid?: number;
    readonly labelid?: number;
    readonly draft?: boolean;
    readonly roles?: ReadonlyArray<string>;
    readonly unread?: boolean;
    readonly starred?: boolean;
    readonly deleted?: boolean;
    readonly content?: string;
    readonly sendername?: string;
    readonly recipientname?: string;
    readonly withfilesonly?: boolean;
    readonly maxtime?: number;
    readonly startid?: number;
    readonly stopid?: number;
    readonly reverse?: boolean;
}

export interface CountMessagesRequest {
    readonly methodname: 'count_messages';
    readonly query: MessageQuery;
}

export type CountMessagesResponse = number;

export interface SearchMessagesRequest {
    readonly methodname: 'search_messages';
    readonly query: MessageQuery;
    readonly offset?: number;
    readonly limit?: number;
}

export type SearchMessagesResponse = ReadonlyArray<MessageSummary>;

export interface GetMessageRequest {
    readonly methodname: 'get_message';
    readonly messageid: number;
}

export type GetMessageResponse = Message;

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

export interface GetRolesRequest {
    readonly methodname: 'get_roles';
    readonly courseid: number;
}

export type GetRolesResponse = ReadonlyArray<Role>;

export interface GetGroupsRequest {
    readonly methodname: 'get_groups';
    readonly courseid: number;
}

export type GetGroupsResponse = ReadonlyArray<Group>;

export interface UserQuery {
    readonly courseid: number;
    readonly roleid?: number;
    readonly groupid?: number;
    readonly fullname?: string;
    readonly include?: number[];
}

export interface SearchUsersRequest {
    readonly methodname: 'search_users';
    readonly query: UserQuery;
    readonly offset?: number;
    readonly limit?: number;
}

export type SearchUsersResponse = ReadonlyArray<User>;

export interface GetMessageFormRequest {
    readonly methodname: 'get_message_form';
    readonly messageid: number;
}

export type GetMessageFormeResponse = MessageForm;

export interface CreateMessageRequest {
    readonly methodname: 'create_message';
    readonly courseid: number;
}

export type CreateMessageResponse = number;

export interface ReplyMessageRequest {
    readonly methodname: 'reply_message';
    readonly messageid: number;
    readonly all: boolean;
}

export type ReplyMessageResponse = number;

export interface ForwardMessageRequest {
    readonly methodname: 'forward_message';
    readonly messageid: number;
}

export type ForwardMessageResponse = number;

export interface MessageData {
    readonly courseid: number;
    readonly to: number[];
    readonly cc: number[];
    readonly bcc: number[];
    readonly subject: string;
    readonly content: string;
    readonly format: number;
    readonly draftitemid: number;
}

export interface UpdateMessageRequest {
    readonly methodname: 'update_message';
    readonly messageid: number;
    readonly data: MessageData;
}

export interface SendMessageRequest {
    readonly methodname: 'send_message';
    readonly messageid: number;
}

export interface Settings {
    maxrecipients: number;
    globaltrays: ReadonlyArray<string>;
    coursetrays: 'none' | 'unread' | 'all';
    coursetraysname: 'shortname' | 'fullname';
    coursebadges: 'hidden' | 'shortname' | 'fullname';
    coursebadgeslength: number;
    filterbycourse: 'hidden' | 'shortname' | 'fullname';
    incrementalsearch: boolean;
    incrementalsearchlimit: number;
}

export type Strings = Record<string, string>;

export interface Preferences {
    readonly perpage: number;
    readonly markasread: boolean;
}
export interface Course {
    readonly id: number;
    readonly shortname: string;
    readonly fullname: string;
    readonly visible: boolean;
    readonly unread: number;
}

export interface Label {
    readonly id: number;
    readonly name: string;
    readonly color: string;
    readonly unread: number;
}

export interface MessageSummary {
    readonly id: number;
    readonly subject: string;
    readonly numattachments: number;
    readonly draft: boolean;
    readonly time: number;
    readonly shorttime: string;
    readonly fulltime: string;
    readonly unread: boolean;
    readonly starred: boolean;
    readonly deleted: boolean;
    readonly course: MessageCourse;
    readonly sender: User;
    readonly recipients: ReadonlyArray<Recipient>;
    readonly labels: ReadonlyArray<MessageLabel>;
}

export interface MessageCourse {
    readonly id: number;
    readonly shortname: string;
    readonly fullname: string;
}

export interface User {
    readonly id: number;
    readonly fullname: string;
    readonly pictureurl: string;
    readonly profileurl: string;
}

export enum RecipientType {
    TO = 'to',
    CC = 'cc',
    BCC = 'bcc',
}

export interface Recipient extends User {
    readonly type: RecipientType;
    readonly isvalid?: boolean;
}

export interface MessageLabel {
    readonly id: number;
    readonly name: string;
    readonly color: string;
}

export interface Message extends MessageSummary {
    readonly content: string;
    readonly format: number;
    readonly attachments: ReadonlyArray<Attachment>;
    readonly references: ReadonlyArray<Reference>;
}

export interface Reference {
    readonly id: number;
    readonly subject: string;
    readonly content: string;
    readonly format: number;
    readonly time: number;
    readonly shorttime: string;
    readonly fulltime: string;
    readonly sender: User;
    readonly attachments: ReadonlyArray<Attachment>;
}

export interface Attachment {
    readonly filepath: string;
    readonly filename: string;
    readonly mimetype: string;
    readonly filesize: number;
    readonly fileurl: string;
    readonly iconurl: string;
}

export interface Role {
    readonly id: number;
    readonly name: string;
}

export interface Group {
    readonly id: number;
    readonly name: string;
}

export interface MessageForm {
    readonly editorhtml: string;
    readonly filemanagerhtml: string;
    readonly javascript: string;
}

export interface ServiceError {
    readonly message: string;
    readonly errorcode: string;
    readonly debuginfo?: string;
    readonly stacktrace?: string;
}

/**
 * Calls one or more web service methods in a single HTTP request.
 *
 * @param requests List of request with method name and arguments.
 * @returns A promise to the web service responses.
 */
export async function callServices(requests: ServiceRequest[]): Promise<unknown[]> {
    const ajax = (await require('core/ajax')) as CoreAjax;
    const responses = await Promise.all(
        ajax.call(
            Array.from(requests).map(({ methodname, ...args }) => ({
                methodname: `local_mail_${methodname}`,
                args,
            })),
        ),
    );
    return responses;
}
