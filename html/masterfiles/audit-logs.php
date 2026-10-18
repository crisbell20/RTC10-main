<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin = $userRole === 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Audit Logs - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/audit-logs.css">
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Audit Logs';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="../admin/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php" class="active"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2>Audit Logs</h2>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-add-user" id="exportAuditBtn">
                            <i class="bi bi-download me-2"></i>Export
                        </button>
                    </div>
                </div>
            </div>

            <div class="filter-card audit-filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small" for="filterDateRange">Date range</label>
                        <select id="filterDateRange" class="form-select form-select-sm">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                            <option value="730">Last 2 years</option>
                            <option value="all">All time</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="filterModule">Module</label>
                        <select id="filterModule" class="form-select form-select-sm">
                            <option value="">All modules</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="filterAction">Action</label>
                        <select id="filterAction" class="form-select form-select-sm">
                            <option value="">All actions</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="filterStatus">Status</label>
                        <select id="filterStatus" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="SUCCESS">Success</option>
                            <option value="FAILED">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="searchAudit">Search</label>
                        <input type="search" id="searchAudit" class="form-control form-control-sm" placeholder="User, action, details...">
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p class="text-muted small mb-0" id="auditSummary">Loading audit logs...</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAuditFiltersBtn" style="display:none;">
                        <i class="bi bi-x-circle"></i> Clear filters
                    </button>
                </div>
            </div>

            <div class="section-card">
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover audit-table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Module</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="auditTableBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-hourglass-split"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center p-3 border-top">
                    <small class="text-muted" id="paginationInfo">—</small>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" id="prevPageBtn" disabled>Previous</button>
                        <button type="button" class="btn btn-outline-secondary" id="nextPageBtn" disabled>Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/masterfiles/audit-logs.js?v=11"></script>
</body>
</html>
