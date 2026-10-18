<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$isCcmd = ($_SESSION['user_role'] ?? '') === 'CCMD';
$returnToReports = ($_GET['return'] ?? '') === 'reports';
$reportsTab = $_GET['tab'] ?? '';
$reportsTabQuery = in_array($reportsTab, ['overview', 'students', 'subjects'], true) ? '?tab=' . $reportsTab : '';
if ($returnToReports) {
    $examsBackUrl = $isCcmd ? '../ccmd/reports.php' . $reportsTabQuery : 'reports.php' . $reportsTabQuery;
    $backLabel = 'Back';
} else {
    $examsBackUrl = $isCcmd ? '../ccmd/exams.php' : 'exams.php';
    $backLabel = 'Back';
}

if (!$examId) {
    header('Location: ' . $examsBackUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Exam Responses - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/question-display.css?v=2">
    <link rel="stylesheet" href="../../assets/css/exam-responses.css?v=1">
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Exam Responses';
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
                <?php if ($isCcmd): ?>
                <li><a href="../ccmd/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../ccmd/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="../ccmd/monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examinations</a></li>
                <li><a href="../ccmd/exams.php" class="active"><i class="bi bi-journal-text"></i>View Exams</a></li>
                <li><a href="../ccmd/enrolled-trainees.php"><i class="bi bi-people-fill"></i>View Trainees</a></li>
                <li><a href="../ccmd/reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <?php else: ?>
                <li><a href="../admin/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php" class="active"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <a href="<?= htmlspecialchars($examsBackUrl) ?>" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="bi bi-arrow-left"></i> <?= htmlspecialchars($backLabel) ?>
                        </a>
                        <h2 id="examTitle" class="mb-1">Exam Responses</h2>
                        <p id="examMeta" class="text-muted mb-0">Loading exam details...</p>
                        <div id="examAssignments" class="d-flex flex-wrap gap-1 mt-2"></div>
                    </div>
                    <button type="button" class="btn btn-add-user" id="exportCsvBtn" disabled>
                        <i class="bi bi-download me-2"></i>Export
                    </button>
                </div>
            </div>

            <div id="loadingBlock" class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2">Loading responses...</p>
            </div>

            <div id="contentBlock" style="display: none;">
                <div class="row g-3 mb-4" id="summaryStats"></div>

                <ul class="nav nav-tabs mb-3" id="responseTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-tab="rankings" type="button">
                            Rankings <span class="badge bg-primary ms-1" id="rankingsCount">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-tab="not-submitted" type="button">
                            Not submitted <span class="badge bg-secondary ms-1" id="notSubmittedCount">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-tab="item-analysis" type="button">
                            Item Analysis
                        </button>
                    </li>
                </ul>

                <div id="responseFilters" class="d-flex flex-wrap gap-2 mb-3">
                    <input type="search" id="searchInput" class="form-control form-control-sm rankings-only-filter" style="max-width: 260px;" placeholder="Search name, ID, or rank...">
                    <select id="statusFilter" class="form-select form-select-sm rankings-only-filter" style="max-width: 160px;">
                        <option value="all">All results</option>
                        <option value="passed">Passed only</option>
                        <option value="failed">Failed only</option>
                    </select>
                </div>

                <div id="rankingsPanel">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Batch</th>
                                    <th>Section</th>
                                    <th>Score</th>
                                    <th>Ave %</th>
                                    <th style="width:80px;">Standing</th>
                                    <th>Remarks</th>
                                    <th>Time</th>
                                    <th>Submitted</th>
                                    <th style="width:104px;"></th>
                                </tr>
                            </thead>
                            <tbody id="rankingsBody"></tbody>
                        </table>
                    </div>
                    <div id="rankingsEmpty" class="text-center text-muted py-4" style="display:none;">
                        <i class="bi bi-clipboard-data" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No finished submissions yet.</p>
                    </div>
                </div>

                <div id="notSubmittedPanel" style="display:none;">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Batch</th>
                                    <th>Section</th>
                                </tr>
                            </thead>
                            <tbody id="notSubmittedBody"></tbody>
                        </table>
                    </div>
                    <div id="notSubmittedEmpty" class="text-center text-muted py-4" style="display:none;">
                        <p class="mb-0">All assigned trainees have submitted.</p>
                    </div>
                </div>

                <div id="itemAnalysisPanel" style="display:none;">
                    <div id="itemAnalysisNotice" class="alert alert-info py-2 small mb-3" style="display:none;"></div>
                    <div id="itemAnalysisLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2 mb-0">Loading item analysis...</p>
                    </div>
                    <div id="itemAnalysisContent" style="display:none;">
                        <div class="item-analysis-toolbar mb-3">
                            <input type="search" id="itemAnalysisSearch" class="form-control form-control-sm"
                                placeholder="Search question text..." autocomplete="off">
                            <span class="toolbar-count" id="itemAnalysisCount"></span>
                        </div>
                        <div class="item-analysis-list" id="itemAnalysisList"></div>
                    </div>
                    <div id="itemAnalysisEmpty" class="text-center text-muted py-4" style="display:none;">
                        <i class="bi bi-bar-chart-line" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">No finished submissions yet for item analysis.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="detailModalTitle">Trainee Response</h5>
                        <small class="text-muted" id="detailModalSub"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.EXAM_RESPONSES_EXAM_ID = <?= (int)$examId ?>;
        window.EXAM_RESPONSES_CAN_RETAKE = <?= $isCcmd ? 'false' : 'true' ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/masterfiles/exam-responses.js?v=38"></script>
</body>
</html>
