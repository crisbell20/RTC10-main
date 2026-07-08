const API = '../../api/masterfiles/audit-logs.php';

let currentPage = 1;
let totalPages = 1;
let searchTimer = null;
let detailModal = null;
let metaData = null;

document.addEventListener('DOMContentLoaded', () => {
    detailModal = new bootstrap.Modal(document.getElementById('auditDetailModal'));
    setDefaultDateRange();
    bindEvents();
    loadMeta().then(() => loadLogs());
});

function setDefaultDateRange() {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - 30);
    document.getElementById('dateTo').value = formatDateInput(to);
    document.getElementById('dateFrom').value = formatDateInput(from);
}

function formatDateInput(date) {
    return date.toISOString().slice(0, 10);
}

function bindEvents() {
    document.getElementById('logoutBtn')?.addEventListener('click', handleLogout);
    ['dateFrom', 'dateTo', 'filterModule', 'filterAction', 'filterStatus'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => {
            currentPage = 1;
            loadLogs();
        });
    });
    document.getElementById('searchAudit')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            currentPage = 1;
            loadLogs();
        }, 350);
    });
    document.getElementById('clearAuditFiltersBtn')?.addEventListener('click', clearFilters);
    document.getElementById('prevPageBtn')?.addEventListener('click', () => changePage(-1));
    document.getElementById('nextPageBtn')?.addEventListener('click', () => changePage(1));
    document.getElementById('exportAuditBtn')?.addEventListener('click', exportCsv);
    document.getElementById('purgeAuditBtn')?.addEventListener('click', purgeOldLogs);
}

function getFilters() {
    return {
        date_from: document.getElementById('dateFrom')?.value || '',
        date_to: document.getElementById('dateTo')?.value || '',
        module: document.getElementById('filterModule')?.value || '',
        audit_action: document.getElementById('filterAction')?.value || '',
        status: document.getElementById('filterStatus')?.value || '',
        search: document.getElementById('searchAudit')?.value.trim() || '',
    };
}

function hasActiveFilters() {
    const f = getFilters();
    return !!(f.module || f.audit_action || f.status || f.search);
}

async function loadMeta() {
    try {
        const res = await axios.get(`${API}?action=meta`);
        if (res.data.success) {
            metaData = res.data.data;
            populateSelect('filterModule', metaData.modules, 'All modules');
            populateSelect('filterAction', metaData.actions, 'All actions');
        }
    } catch (err) {
        console.error('Failed to load audit meta', err);
    }
}

function populateSelect(id, items, placeholder) {
    const select = document.getElementById(id);
    if (!select) return;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    (items || []).forEach(item => {
        select.add(new Option(item, item));
    });
}

async function loadLogs() {
    const tbody = document.getElementById('auditTableBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Loading...</td></tr>';

    const params = new URLSearchParams({ action: 'list', page: currentPage, per_page: 25, ...getFilters() });

    try {
        const res = await axios.get(`${API}?${params.toString()}`);
        if (!res.data.success) throw new Error(res.data.message || 'Failed to load logs');

        const rows = res.data.data || [];
        const pagination = res.data.pagination || {};
        totalPages = pagination.total_pages || 1;
        currentPage = pagination.page || 1;

        updateSummary(pagination.total || 0);
        updatePagination(pagination);
        renderTable(rows);
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(err.response?.data?.message || err.message)}</td></tr>`;
    }
}

function updateSummary(total) {
    document.getElementById('auditSummary').textContent = `Showing ${total} log entr${total === 1 ? 'y' : 'ies'}`;
    document.getElementById('clearAuditFiltersBtn').style.display = hasActiveFilters() ? 'inline-block' : 'none';
}

function updatePagination(pagination) {
    const total = pagination.total || 0;
    const page = pagination.page || 1;
    const perPage = pagination.per_page || 25;
    const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const to = Math.min(page * perPage, total);

    document.getElementById('paginationInfo').textContent = total
        ? `Page ${page} of ${pagination.total_pages || 1} • ${from}–${to} of ${total}`
        : 'No entries';
    document.getElementById('prevPageBtn').disabled = page <= 1;
    document.getElementById('nextPageBtn').disabled = page >= (pagination.total_pages || 1);
}

function renderTable(rows) {
    const tbody = document.getElementById('auditTableBody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No audit logs found for the selected filters.</td></tr>';
        return;
    }

    tbody.innerHTML = rows.map(row => `
        <tr>
            <td><small>${formatDateTime(row.Timestamp)}</small></td>
            <td>
                <div class="fw-medium">${escapeHtml(row.user_display)}</div>
                <small class="text-muted">${escapeHtml(row.User_Role || row.role_name || '')}</small>
            </td>
            <td><span class="badge bg-light text-dark border audit-module-badge">${escapeHtml(row.Module || '—')}</span></td>
            <td>${renderActionBadge(row.Action)}</td>
            <td class="audit-details-cell" title="${escapeHtml(row.Outcome || '')}">${escapeHtml(row.Outcome || '—')}</td>
            <td>${renderStatusBadge(row.Status)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewAuditDetail(${row.Log_ID})">Details</button>
            </td>
        </tr>
    `).join('');
}

function renderActionBadge(action) {
    const code = escapeHtml(action || '—');
    let cls = 'bg-secondary';
    const a = (action || '').toUpperCase();
    if (a.includes('CREATE') || a.includes('RESTORE') || a.includes('LOGIN')) cls = 'bg-success';
    else if (a.includes('UPDATE') || a.includes('VIEW') || a.includes('EXPORT')) cls = 'bg-primary';
    else if (a.includes('DELETE') || a.includes('FAILED') || a.includes('PURGE')) cls = 'bg-danger';
    else if (a.includes('ARCHIVE') || a.includes('CLOSE') || a.includes('RESET')) cls = 'bg-warning text-dark';
    return `<span class="badge audit-action-badge ${cls}">${code}</span>`;
}

function renderStatusBadge(status) {
    const s = (status || 'SUCCESS').toUpperCase();
    return s === 'FAILED'
        ? '<span class="badge bg-danger">Failed</span>'
        : '<span class="badge bg-success">Success</span>';
}

async function viewAuditDetail(logId) {
    const body = document.getElementById('auditDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    detailModal.show();

    try {
        const res = await axios.get(`${API}?action=detail&log_id=${logId}`);
        if (!res.data.success) throw new Error(res.data.message);
        const d = res.data.data;
        body.innerHTML = `
            <dl class="detail-grid">
                <dt>Log ID</dt><dd>${d.Log_ID}</dd>
                <dt>Timestamp</dt><dd>${formatDateTime(d.Timestamp)}</dd>
                <dt>User</dt><dd>${escapeHtml(d.user_display)} ${d.user_email ? '(' + escapeHtml(d.user_email) + ')' : ''}</dd>
                <dt>Role</dt><dd>${escapeHtml(d.User_Role || d.role_name || '—')}</dd>
                <dt>Module</dt><dd>${escapeHtml(d.Module || '—')}</dd>
                <dt>Action</dt><dd>${renderActionBadge(d.Action)}</dd>
                <dt>Status</dt><dd>${renderStatusBadge(d.Status)}</dd>
                <dt>Entity</dt><dd>${escapeHtml(d.Entity_Type || '—')}${d.Entity_ID ? ' #' + d.Entity_ID : ''}</dd>
                <dt>IP Address</dt><dd>${escapeHtml(d.IP_Address || '—')}</dd>
                <dt>Details</dt><dd>${escapeHtml(d.Outcome || '—')}</dd>
            </dl>
        `;
    } catch (err) {
        body.innerHTML = `<div class="alert alert-danger">${escapeHtml(err.response?.data?.message || err.message)}</div>`;
    }
}

async function exportCsv() {
    const params = new URLSearchParams({ action: 'export', limit: 5000, ...getFilters() });
    try {
        const res = await axios.get(`${API}?${params.toString()}`);
        if (!res.data.success) throw new Error(res.data.message);

        const rows = res.data.data || [];
        const headers = ['Timestamp', 'User', 'Role', 'Module', 'Action', 'Status', 'Details', 'Entity Type', 'Entity ID', 'IP Address'];
        const lines = [headers.join(',')];
        rows.forEach(r => {
            lines.push([
                csvCell(r.Timestamp),
                csvCell(r.user_display),
                csvCell(r.User_Role || r.role_name),
                csvCell(r.Module),
                csvCell(r.Action),
                csvCell(r.Status),
                csvCell(r.Outcome),
                csvCell(r.Entity_Type),
                csvCell(r.Entity_ID),
                csvCell(r.IP_Address),
            ].join(','));
        });

        const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `audit-logs-${formatDateInput(new Date())}.csv`;
        link.click();
        URL.revokeObjectURL(url);

        axios.post(API, { action: 'log_export', row_count: rows.length }).catch(() => {});
    } catch (err) {
        Swal.fire('Export failed', err.response?.data?.message || err.message, 'error');
    }
}

function purgeOldLogs() {
    if (!window.AUDIT_LOGS_IS_ADMIN) return;

    Swal.fire({
        title: 'Retention purge',
        html: 'Delete audit entries older than <input id="purgeDays" type="number" class="form-control form-control-sm d-inline-block" style="width:90px" value="365" min="30" max="3650"> days?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Purge old logs',
        confirmButtonColor: '#ef4444',
        preConfirm: () => parseInt(document.getElementById('purgeDays').value, 10) || 365,
    }).then(async result => {
        if (!result.isConfirmed) return;
        try {
            const res = await axios.post(API, { action: 'purge', days: result.value });
            if (res.data.success) {
                Swal.fire('Done', res.data.message, 'success');
                loadLogs();
            }
        } catch (err) {
            Swal.fire('Error', err.response?.data?.message || 'Purge failed', 'error');
        }
    });
}

function clearFilters() {
    document.getElementById('filterModule').value = '';
    document.getElementById('filterAction').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('searchAudit').value = '';
    setDefaultDateRange();
    currentPage = 1;
    loadLogs();
}

function changePage(delta) {
    currentPage = Math.max(1, Math.min(totalPages, currentPage + delta));
    loadLogs();
}

function csvCell(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function handleLogout(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, logout',
    }).then(result => {
        if (result.isConfirmed) {
            fetch('../../api/auth/logout.php', { method: 'POST' })
                .finally(() => { window.location.href = '../../html/auth/login.html'; });
        }
    });
}

window.viewAuditDetail = viewAuditDetail;
