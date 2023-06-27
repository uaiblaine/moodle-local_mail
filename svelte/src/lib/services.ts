import { require } from './amd';

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
    | SetLabelsRequest;

export interface SetPreferencesRequest {
    readonly methodname: 'set_preferences';
    readonly preferences: Partial<Preferences>;
}

export interface GetCoursesRequest {
    readonly methodname: 'get_courses';
}

export interface GetLabelsRequest {
    readonly methodname: 'get_labels';
}

export interface Query {
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
    readonly query: Query;
}

export interface SearchMessagesRequest {
    readonly methodname: 'search_messages';
    readonly query: Query;
    readonly offset?: number;
    readonly limit?: number;
}

export interface GetMessageRequest {
    readonly methodname: 'get_message';
    readonly messageid: number;
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

export type ServiceResponse<T> = T extends SetPreferencesRequest
    ? void
    : T extends GetCoursesRequest
    ? ReadonlyArray<Course>
    : T extends GetLabelsRequest
    ? ReadonlyArray<Label>
    : T extends CountMessagesRequest
    ? number
    : T extends SearchMessagesRequest
    ? ReadonlyArray<MessageSummary>
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

export interface Settings {
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
    readonly sender: Sender;
    readonly recipients: ReadonlyArray<Recipient>;
    readonly labels: ReadonlyArray<MessageLabel>;
}

export interface MessageCourse {
    readonly id: number;
    readonly shortname: string;
    readonly fullname: string;
}

export interface Sender {
    readonly id: number;
    readonly fullname: string;
    readonly pictureurl: string;
    readonly profileurl: string;
}

export interface Recipient {
    readonly type: 'to' | 'cc' | 'bcc';
    readonly id: number;
    readonly fullname: string;
    readonly pictureurl: string;
    readonly profileurl: string;
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
    readonly sender: Sender;
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
export async function callServices<T extends ServiceRequest[] | []>(
    requests: T,
): Promise<{ [P in keyof T]: ServiceResponse<T[P]> }> {
    let ajax = await require('core/ajax');
    const responses = await Promise.all(
        ajax.call(
            Array.from(requests).map(({ methodname, ...args }) => ({
                methodname: `local_mail_${methodname}`,
                args,
            })),
        ),
    );
    return responses as { [P in keyof T]: ServiceResponse<T[P]> };
}
