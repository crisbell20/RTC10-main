/**
 * Shared staff portal UI helpers (action menus, labels).
 */
(function () {
    'use strict';

    function buildStaffActionMenu(items) {
        const menuItems = (items || []).map(item =>
            `<li><button class="dropdown-item" type="button" data-action="${item.action}" ${item.attrs || ''}>${item.label}</button></li>`
        ).join('');
        return `<div class="dropdown staff-action-menu">
            <button type="button" class="btn btn-sm btn-link text-secondary staff-action-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="More actions">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">${menuItems}</ul>
        </div>`;
    }

    window.buildStaffActionMenu = buildStaffActionMenu;
    // Backward-compatible alias used in some inline scripts
    window.buildActionMenu = buildStaffActionMenu;
})();
