/**
 * Auto-scroll a container while dragging near its top or bottom edge.
 */
(function(global) {
    let rafId = null;
    let scrollContainer = null;
    let pointerY = 0;
    const EDGE_PX = 52;
    const MAX_STEP = 14;

    function stop() {
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        scrollContainer = null;
    }

    function tick() {
        if (!scrollContainer) {
            rafId = null;
            return;
        }

        const rect = scrollContainer.getBoundingClientRect();
        let delta = 0;

        if (pointerY < rect.top + EDGE_PX) {
            const depth = rect.top + EDGE_PX - pointerY;
            delta = -Math.min(MAX_STEP, Math.max(1, depth * 0.3));
        } else if (pointerY > rect.bottom - EDGE_PX) {
            const depth = pointerY - (rect.bottom - EDGE_PX);
            delta = Math.min(MAX_STEP, Math.max(1, depth * 0.3));
        }

        if (delta !== 0) {
            scrollContainer.scrollTop += delta;
            rafId = requestAnimationFrame(tick);
        } else {
            rafId = null;
        }
    }

    function update(clientY, container) {
        if (!container) {
            return;
        }
        pointerY = clientY;
        scrollContainer = container;
        if (!rafId) {
            rafId = requestAnimationFrame(tick);
        }
    }

    global.DragAutoScroll = {
        update,
        stop
    };
})(window);
