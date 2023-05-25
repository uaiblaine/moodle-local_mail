export function jQueryEvents(node: HTMLElement, handlers: Readonly<Record<string, () => void>>) {
    for (const event in handlers) {
        window.jQuery(node).on(event, handlers[event]);
    }

    return {
        destroy() {
            for (const event in handlers) {
                window.jQuery(node).off(event, handlers[event]);
            }
        },
    };
}
