<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheating Incidents - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/monitor.css">
    <style>
        .sidebar-menu .menu-label {
            display: block;
            padding: 12px 20px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 20px;
            margin-bottom: 8px;
            cursor: default;
            pointer-events: none;
        }

        .sidebar-menu .menu-label i {
            margin-right: 6px;
            font-size: 0.8rem;
            opacity: 0.6;
        }

        .incidents-table-wrap {
            overflow-x: auto;
        }

        .incidents-table th.incidents-actions-col,
        .incidents-table td.incidents-actions-col {
            position: sticky;
            right: 0;
            z-index: 2;
            min-width: 3rem;
            width: 3rem;
            white-space: nowrap;
            background-color: #fff;
            box-shadow: -6px 0 10px rgba(15, 23, 42, 0.06);
        }

        .incidents-actions-trigger {
            line-height: 1;
        }

        .incidents-table thead th.incidents-actions-col {
            z-index: 3;
            background-color: #f8f9fa;
        }

        .incidents-table.table-hover tbody tr:hover td.incidents-actions-col {
            background-color: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Cheating Incident Management</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="../masterfiles/users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="../masterfiles/courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="../masterfiles/subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="../masterfiles/exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="../masterfiles/question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="../masterfiles/reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php" class="active"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="../masterfiles/archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="../masterfiles/audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="../masterfiles/settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h2>Cheating Incidents</h2>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="stats-grid mb-4">
                <div class="stat-card red">
                    <div class="stat-header">
                        <div class="stat-label">Open Incidents</div>
                        <div class="stat-icon icon-red"><i class="bi bi-exclamation-triangle"></i></div>
                    </div>
                    <div class="stat-value" id="openIncidentsCount">--</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Total Recorded</div>
                        <div class="stat-icon icon-purple"><i class="bi bi-list-check"></i></div>
                    </div>
                    <div class="stat-value" id="totalIncidentsCount">--</div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-shield-exclamation" style="color: var(--danger-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>Cheating Incidents</h5>
                    </div>
                </div>
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
                        <div>
                            <label class="form-label small mb-1" for="statusFilter">Status</label>
                            <select id="statusFilter" class="form-select form-select-sm">
                                <option value="">All statuses</option>
                                <option value="Open" selected>Open</option>
                                <option value="Escalated">Escalated</option>
                                <option value="Reviewed">Reviewed</option>
                                <option value="Dismissed">Dismissed</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-responsive incidents-table-wrap">
                        <table class="table table-hover mb-0 incidents-table">
                            <thead>
                                <tr>
                                    <th>Trainee</th>
                                    <th>Exam</th>
                                    <th>Violation</th>
                                    <th>Detected</th>
                                    <th>Events</th>
                                    <th>Status</th>
                                    <th class="incidents-actions-col text-center" aria-label="Actions"></th>
                                </tr>
                            </thead>
                            <tbody id="incidentsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cheatingLogModal" tabindex="-1" aria-labelledby="cheatingLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cheatingLogModalLabel">Proctoring Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="cheatingLogModalBody">
                    <div class="text-center py-4 text-muted">Loading...</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/admin/cheating-incidents.js?v=6"></script>
</body>

</html>