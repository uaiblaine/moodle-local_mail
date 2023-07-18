import type { SearchParams, ViewParams, ViewTray } from './store';

export function preferencesUrl(): string {
    return baseUrl() + 'preferences.php';
}

export function viewUrl(params: ViewParams): string {
    let url = baseUrl() + 'view.php?t=' + params.tray;

    if (params.courseid) {
        url += '&c=' + params.courseid;
    }
    if (params.tray == 'label' && params.labelid) {
        url += '&l=' + params.labelid;
    }
    if (params.messageid) {
        url += '&m=' + params.messageid;
    }
    if (params.search && params.offset) {
        url += '&o=' + params.offset;
    }
    if (params.search?.content) {
        url += '&q=' + params.search.content;
    }
    if (params.search?.sendername) {
        url += '&qs=' + params.search.sendername;
    }
    if (params.search?.recipientname) {
        url += '&qr=' + params.search.recipientname;
    }
    if (params.search?.unread) {
        url += '&u=1';
    }
    if (params.search?.withfilesonly) {
        url += '&f=1';
    }
    if (params.search?.maxtime) {
        url += '&d=' + params.search.maxtime;
    }
    if (params.search?.startid) {
        url += '&s=' + params.search.startid;
    }
    if (params.search?.reverse) {
        url += '&r=1';
    }

    return url;
}

export function getViewParamsFromUrl(): ViewParams {
    const url = new URL(window.location.href);
    const params: ViewParams = {
        tray: (url.searchParams.get('t') as ViewTray) || 'inbox',
        courseid: parseInt(url.searchParams.get('c') || '') || undefined,
        labelid: parseInt(url.searchParams.get('l') || '') || undefined,
        messageid: parseInt(url.searchParams.get('m') || '') || undefined,
        offset: parseInt(url.searchParams.get('o') || '') || undefined,
    };
    const search: SearchParams = {
        content: url.searchParams.get('q') || undefined,
        sendername: url.searchParams.get('qs') || undefined,
        recipientname: url.searchParams.get('qr') || undefined,
        unread: url.searchParams.get('u') == '1' || undefined,
        withfilesonly: url.searchParams.get('f') == '1' || undefined,
        maxtime: parseInt(url.searchParams.get('d') || '') || undefined,
        startid: parseInt(url.searchParams.get('s') || '') || undefined,
        reverse: url.searchParams.get('r') == '1' || undefined,
    };
    return Object.values(search).some((v) => v != null) ? { ...params, search } : params;
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
