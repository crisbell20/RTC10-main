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
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Cheating Incidents - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/monitor.css">
    <link rel="stylesheet" href="../../assets/css/cheating-incidents.css?v=5">
</head>

<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Cheating Incident Management';
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
                <li><a href="cheating-incidents.php" class="active"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h2>Cheating Incidents</h2>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="filter-card mb-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1" for="filterCourse">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section-card incidents-section-card">
                <div class="section-header">
                    <i class="bi bi-shield-exclamation" style="color: var(--danger-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5 id="incidentsSectionTitle">Incidents by Exam</h5>
                        <p class="text-muted small mb-0" id="incidentsSectionSub">Select an exam to review trainees</p>
                    </div>
                </div>
                <div class="section-body">
                    <div id="examSummaryPanel">
                        <div id="examSummaryCards" class="incident-exam-card-grid">
                            <div class="incident-exam-empty">Loading...</div>
                        </div>
                    </div>

                    <div id="examIncidentsPanel" style="display:none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="backToExamsBtn">
                            <i class="bi bi-arrow-left"></i> Back
                        </button>
                        <div class="table-responsive incidents-table-wrap">
                            <table class="table table-hover mb-0 incidents-table">
                                <thead>
                                    <tr>
                                        <th>Trainee</th>
                                        <th>Batch</th>
                                        <th>Section</th>
                                        <th>Violation</th>
                                        <th>Detected</th>
                                        <th>Events</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="incidentsTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
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
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/masterfiles/cheating-incidents.js?v=21"></script>
</body>

</html>
