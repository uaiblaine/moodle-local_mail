export function truncate(node: HTMLElement, tooltip: string) {
    function handleMouseEnter() {
        if (node.offsetWidth < node.scrollWidth) {
            node.setAttribute('title', tooltip);
        } else {
            node.removeAttribute('title');
        }
    }

    node.style.overflow = 'hidden';
    node.style.textOverflow = 'ellipsis';
    node.style.whiteSpace = 'nowrap';
    node.addEventListener('mouseenter', handleMouseEnter);

    return {
        destroy() {
            node.removeEventListener('mouseenter', handleMouseEnter, true);
        },
    };
}
