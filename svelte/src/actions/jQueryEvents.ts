export function jQueryEvents(node: HTMLElement, handlers: Readonly<Record<string, () => void>>) {
    const setHandlers = () => {
        for (const event in handlers) {
            window.jQuery(node).on(event, handlers[event]);
        }
    };

    const unsetHandlers = () => {
        for (const event in handlers) {
            window.jQuery(node).off(event, handlers[event]);
        }
    };

    setHandlers();

    return {
        update(updatedHandlers: Readonly<Record<string, () => void>>) {
            unsetHandlers();
            handlers = updatedHandlers;
            setHandlers();
        },
        destroy() {
            unsetHandlers();
        },
    };
}
