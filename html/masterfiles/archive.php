<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
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
    <title>Archive - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/archive.css">
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Archive</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../admin/cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php" class="active"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2>Archive</h2>
                        <p class="text-muted small mb-0">Archived records are kept for <strong>2 years</strong>, then permanently removed automatically.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-add-user" id="exportArchiveBtn" disabled>
                            <i class="bi bi-download me-2"></i>Export CSV
                        </button>
                    </div>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div id="migrationWarning" class="alert alert-warning d-none" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Archive columns are not fully installed. Run the database migrations to enable archiving.
            </div>

            <ul class="nav nav-pills archive-type-tabs mb-3" id="archiveTypeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-archive-type="exams" type="button"><i class="bi bi-pencil-square me-1"></i>Exams</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="courses" type="button"><i class="bi bi-book me-1"></i>Courses</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="modules" type="button"><i class="bi bi-collection me-1"></i>Modules</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="subjects" type="button"><i class="bi bi-journal-text me-1"></i>Subjects</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="batches" type="button"><i class="bi bi-layers me-1"></i>Batches</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="sections" type="button"><i class="bi bi-grid-3x3-gap me-1"></i>Sections</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="questions" type="button"><i class="bi bi-question-circle me-1"></i>Questions</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-archive-type="users" type="button"><i class="bi bi-people me-1"></i>Users</button>
                </li>
            </ul>

            <div class="filter-card archive-filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-2" id="filterCourseCol">
                        <label class="form-label small" for="filterCourse">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-2" id="filterSubjectCol">
                        <label class="form-label small" for="filterSubject">Subject</label>
                        <select id="filterSubject" class="form-select form-select-sm">
                            <option value="">All subjects</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="filterDateRange">Archived</label>
                        <select id="filterDateRange" class="form-select form-select-sm">
                            <option value="all">All time</option>
                            <option value="7">Last 7 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                            <option value="730">Last 2 years</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="searchArchive">Search</label>
                        <input type="search" id="searchArchive" class="form-control form-control-sm" placeholder="Search archived records...">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="clearFiltersBtn" style="display:none;">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <p class="text-muted small mb-0" id="archiveSummary">Loading...</p>
                    <p class="text-muted small mb-0"><span id="retentionDaysLabel">730</span>-day retention · Auto-purge daily</p>
                </div>
            </div>

            <div class="section-card">
                <div class="section-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover archive-table mb-0">
                            <thead class="table-light" id="archiveTableHead"></thead>
                            <tbody id="archiveTableBody">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-hourglass-split"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.ARCHIVE_IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/archive.js?v=10"></script>
</body>
</html>
