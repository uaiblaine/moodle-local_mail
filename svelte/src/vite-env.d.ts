/// <reference types="svelte" />
/// <reference types="vite/client" />

declare interface Window {
    M: {
        cfg: {
            wwwroot: string;
            sesskey: string;
        };
    };
    require: (deps: string[], callback: (...modules: unknown[]) => void) => void;
    local_mail_navbar_data: Record<string, unknown> | undefined;
    local_mail_view_data: Record<string, unknown> | undefined;
}
