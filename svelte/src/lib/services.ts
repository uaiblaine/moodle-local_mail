import { require } from './amd';

export type ServiceRequest =
    | GetUnreadCountRequest
    | GetMenuRequest
    | GetIndexRequest
    | SearchIndexRequest
    | GetMessageRequest
    | SetUnreadRequest;

export type ServiceResponse<T extends ServiceRequest> = T extends GetUnreadCountRequest
    ? GetUnreadCountResponse
    : T extends GetMenuRequest
    ? GetMenuResponse
    : T extends GetIndexRequest
    ? GetIndexResponse
    : T extends SearchIndexRequest
    ? SearchIndexResponse
    : T extends GetMessageRequest
    ? GetMessageResponse
    : T extends SetUnreadRequest
    ? SetUnreadResponse
    : unknown;

export type GetUnreadCountRequest = {
    methodname: 'get_unread_count';
};

export type GetUnreadCountResponse = number;

export type GetMenuRequest = {
    methodname: 'get_menu';
};

export type GetMenuResponse = {
    unread: number;
    drafts: number;
    courses: MenuCourse[];
    labels: MenuLabel[];
};

export type MenuCourse = {
    id: number;
    shortname: string;
    fullname: string;
    unread: number;
    visible: boolean;
};

export type MenuLabel = {
    id: number;
    name: string;
    color: string;
    unread: number;
};

export type GetIndexRequest = {
    methodname: 'get_index';
    type: 'inbox' | 'drafts' | 'sent' | 'starred' | 'course' | 'label' | 'trash';
    itemid: number;
    offset: number;
    limit: number;
};

export type GetIndexResponse = {
    totalcount: number;
    messages: IndexMessage[];
};

export type IndexMessage = {
    id: number;
    subject: string;
    attachments: number;
    draft: boolean;
    time: number;
    unread: boolean;
    starred: boolean;
    course: MessageCourse;
    sender: Sender;
    recipients: Recipient[];
    labels: MessageLabel[];
};

export type MessageCourse = {
    id: number;
    shortname: string;
};

export type Sender = {
    id: number;
    fullname: string;
    pictureurl: string;
};

export type Recipient = {
    id: number;
    fullname: string;
    pictureurl: string;
    type: 'to' | 'cc' | 'bcc';
};

export type MessageLabel = {
    id: number;
    name: string;
    color: string;
};

export type SearchIndexRequest = {
    methodname: 'search_index';
    type: 'inbox' | 'drafts' | 'sent' | 'starred' | 'course' | 'label' | 'trash';
    query: SearchQuery;
};

export type SearchQuery = {
    beforeid: number;
    afterid: number;
    content: string;
    sender: string;
    recipients: string;
    unread: boolean;
    attachments: boolean;
    time: number;
    limit: number;
};

export type SearchIndexResponse = {
    totalcount: number;
    messages: IndexMessage[];
};

export type GetMessageRequest = {
    methodname: 'get_message';
    id: number;
};

export type GetMessageResponse = Message;

export type Message = {
    id: number;
    subject: string;
    content: string;
    format: number;
    draft: boolean;
    time: number;
    unread: boolean;
    starred: boolean;
    course: MessageCourse;
    sender: Sender;
    recipients: Recipient[];
    attachments: Attachment[];
    references: Reference[];
    labels: MessageLabel[];
};

export type Reference = {
    id: number;
    subject: string;
    content: string;
    format: number;
    time: number;
    sender: Sender;
    attachments: Attachment[];
};

export type Attachment = {
    filename: string;
    mimetype: string;
    filesize: number;
    fileurl: string;
};

export type SetUnreadRequest = {
    methodname: 'set_unread';
    id: number;
    unread: boolean;
};

export type SetUnreadResponse = void;

/**
 * Calls a web service method.
 *
 * @param request Request with method name and arguments.
 * @returns A promise to the web service response.
 */
export async function call<T extends ServiceRequest>(request: T): Promise<ServiceResponse<T>> {
    let responses = await callMany([request]);
    return responses[0];
}

/**
 * Calls one or more web service methods in a single HTTP request.
 *
 * @param requests List of request with method name and arguments.
 * @returns A promise to the web service responses.
 */
export async function callMany<T extends ServiceRequest[]>(
    requests: T,
): Promise<{ [P in keyof T]: ServiceResponse<T[P]> }> {
    let ajax = await require('core/ajax');
    try {
        return (await Promise.all(
            ajax.call(
                requests.map(({ methodname, ...args }) => ({
                    methodname: `local_mail_${methodname}`,
                    args,
                })),
            ),
        )) as any;
    } catch (error) {
        let notification = await require('core/notification');
        notification.exception(error);
    }
}
