document.addEventListener('DOMContentLoaded', () => {
    const viewport = document.querySelector('.invoice-list-panel .table-responsive');
    const table = viewport?.querySelector('table');
    if (!table) return;

    const scrollbar = document.createElement('div');
    scrollbar.className = 'invoice-floating-scroll';
    scrollbar.tabIndex = 0;
    scrollbar.setAttribute('role', 'region');
    scrollbar.setAttribute('aria-label', 'Scroll invoice table horizontally');
    scrollbar.hidden = true;
    const track = document.createElement('div');
    scrollbar.appendChild(track);
    document.body.appendChild(scrollbar);

    // Both native scrollbars control the same columns, including touch/keyboard scrolling.
    scrollbar.addEventListener('scroll', () => {
        if (viewport.scrollLeft !== scrollbar.scrollLeft) viewport.scrollLeft = scrollbar.scrollLeft;
    }, { passive: true });
    viewport.addEventListener('scroll', () => {
        if (scrollbar.scrollLeft !== viewport.scrollLeft) scrollbar.scrollLeft = viewport.scrollLeft;
    }, { passive: true });

    let frame = 0;
    const update = () => {
        frame = 0;
        const bounds = viewport.getBoundingClientRect();
        const height = 24;
        const top = Math.min(window.innerHeight - height - 8, bounds.bottom - height);
        const visible = viewport.scrollWidth > viewport.clientWidth + 1
            && top > Math.max(0, bounds.top) && bounds.bottom > height;
        scrollbar.hidden = !visible;
        if (!visible) return;
        scrollbar.style.left = bounds.left + 'px';
        scrollbar.style.top = top + 'px';
        scrollbar.style.width = viewport.clientWidth + 'px';
        track.style.width = viewport.scrollWidth + 'px';
        scrollbar.scrollLeft = viewport.scrollLeft;
    };
    const scheduleUpdate = () => {
        if (!frame) frame = requestAnimationFrame(update);
    };
    window.addEventListener('scroll', scheduleUpdate, { passive: true, capture: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    const observer = new ResizeObserver(scheduleUpdate);
    observer.observe(viewport);
    observer.observe(table);
    update();
});
