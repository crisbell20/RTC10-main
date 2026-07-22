<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Examination - CCMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/ccmd.css">
    <link rel="stylesheet" href="../../assets/css/monitor.css?v=2">
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
        .sidebar-menu .menu-label i { margin-right: 6px; font-size: 0.8rem; opacity: 0.6; }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Live Exam Monitoring</p>
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
                <li><a href="monitor-examination.php" class="active"><i class="bi bi-activity"></i>Monitor Examination</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="monitor-examination.php"><i class="bi bi-people-fill"></i>Enrolled Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../masterfiles/audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h2>Monitor Examination</h2>
                    <p class="text-muted mb-0">Live tracking of trainees during active exams</p>
                </div>
            </div>

            <div class="filter-card monitor-filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label small mb-1" for="examSelect">Exam</label>
                        <select id="examSelect" class="form-select form-select-sm">
                            <option value="">Loading exams...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="monitorEmpty" class="section-card text-center py-5 d-none">
                <i class="bi bi-activity text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No published exams</h5>
                <p class="text-muted">There are no active exams to monitor right now.</p>
            </div>

            <div id="monitorBody" class="d-none">
                <div class="stats-grid mb-4" id="summaryStats"></div>

                <div class="section-card mb-4">
                    <div class="section-header">
                        <i class="bi bi-people-fill" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                        <div>
                            <h5 id="examTitle">Trainee Progress</h5>
                            <p id="examMeta">Live session status per assigned trainee</p>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-end monitor-page-filters">
                            <div>
                                <label class="form-label small mb-1" for="statusFilter">Status</label>
                                <select id="statusFilter" class="form-select form-select-sm">
                                    <option value="all">All</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="not_started">Not started</option>
                                </select>
                            </div>
                            <div class="flex-grow-1">
                                <label class="form-label small mb-1" for="searchInput">Search trainee</label>
                                <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Name or academic number...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover monitor-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Trainee</th>
                                        <th>Batch</th>
                                        <th>Status</th>
                                        <th>Progress / Score</th>
                                        <th>Time Left</th>
                                    </tr>
                                </thead>
                                <tbody id="sessionsTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-4">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <small class="text-muted" id="paginationInfo"></small>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" id="prevPageBtn">Previous</button>
                                <button type="button" class="btn btn-outline-secondary" id="nextPageBtn">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const INITIAL_EXAM_ID = <?= json_encode($examId > 0 ? $examId : null) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/ccmd/monitor-examination.js?v=9"></script>
</body>
</html>
