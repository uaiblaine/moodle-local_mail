import type { ViewParams, ViewType } from "./store";

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

export function forwardeUrl(messageid: number): string {
    return baseUrl() + 'view.php?forward=1&sesskey=' + sesskey() + '&m=' + messageid;
}

export function preferencesUrl(): string {
    return baseUrl() + 'preferences.php';
}

export function replyUrl(messageid: number): string {
    return baseUrl() + 'view.php?reply=1&sesskey=' + sesskey() + '&m=' + messageid;
}

export function replyAllUrl(messageid: number): string {
    return baseUrl() + 'view.php?replyall=1&sesskey=' + sesskey() + '&m=' + messageid;
}

export function viewUrl(params: ViewParams): string {
    let url = baseUrl() + 'view.php?t=' + params.type;
    if (params.courseid) {
        url += '&c=' + params.courseid;
    }
    if (params.labelid) {
        url += '&l=' + params.labelid;
    }
    if (params.messageid) {
        url += '&m=' + params.messageid;
    }
    return url;
}

export function getViewParamsFromUrl(): ViewParams {
    const url = new URL(window.location.href);
    return {
        type: (url.searchParams.get('t') as ViewType) || 'inbox',
        courseid: parseInt(url.searchParams.get('c') || '') || undefined,
        labelid: parseInt(url.searchParams.get('l') || '') || undefined,
        messageid: parseInt(url.searchParams.get('m') || '') || undefined,
    };
}

export function setUrlFromViewParams(params: ViewParams, replace: boolean) {
    const url = new URL(viewUrl(params));
    if (url.search != window.location.search) {
        if (replace) {
            window.history.replaceState(undefined, '', url.toString());
        } else {
            window.history.pushState(undefined, '', url.toString());
        }
    }
}

function baseUrl() {
    return window.M.cfg.wwwroot + '/local/mail/';
}

function sesskey() {
    return window.M.cfg.sesskey;
}
