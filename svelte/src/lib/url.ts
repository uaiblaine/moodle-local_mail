export type ViewType = 'inbox' | 'sent' | 'drafts' | 'starred' | 'course' | 'label' | 'trash';

export interface ViewParams {
    readonly type: ViewType;
    readonly courseid?: number;
    readonly labelid?: number;
    readonly messageid?: number;
    readonly offset?: number;
}

export function composeUrl(messageid: number): string {
    return baseUrl() + 'compose.php?m=' + messageid;
}

export function createUrl(courseid?: number): string {
    let url = baseUrl() + 'create.php?sesskey=' + sesskey();
    if (courseid) {
        url += '&c=' + courseid;
    }
    return url;
}

export function viewUrl(params: ViewParams): string {
    let url = baseUrl() + 'view2.php?t=' + params.type;
    if (params.courseid) {
        url += '&c=' + params.courseid;
    }
    if (params.labelid) {
        url += '&l=' + params.labelid;
    }
    if (params.messageid) {
        url += '&m=' + params.messageid;
    }
    if (params.offset) {
        url += '&offset=' + params.offset;
    }
    return url;
}

export function getViewParams(): ViewParams {
    const url = new URL(window.location.href);
    return {
        type: (url.searchParams.get('t') as ViewType) || 'inbox',
        courseid: parseInt(url.searchParams.get('c')) || undefined,
        labelid: parseInt(url.searchParams.get('l')) || undefined,
        offset: parseInt(url.searchParams.get('offset')) || undefined,
    };
}

export function setViewParams(params: ViewParams) {
    const url = new URL(viewUrl(params));
    if (url.search != window.location.search) {
        window.history.pushState({}, '', url.toString());
    }
}

function baseUrl() {
    return window['M'].cfg.wwwroot + '/local/mail/';
}

function sesskey() {
    return window['M'].cfg.sesskey;
}
