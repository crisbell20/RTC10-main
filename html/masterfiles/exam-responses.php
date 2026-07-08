<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    header('Location: ../../html/auth/login.html');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if (!$examId) {
    header('Location: exams.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Responses - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
        .stat-chip {
            background: #f8f9fa;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        .stat-chip h4 {
            margin: 0;
            font-weight: 700;
        }
        .stat-chip p {
            margin: 0;
            color: #6b7280;
            font-size: 0.85rem;
        }
        .rank-medal {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.85rem;
        }
        .rank-1 { background: #fef3c7; color: #92400e; }
        .rank-2 { background: #e5e7eb; color: #374151; }
        .rank-3 { background: #ffedd5; color: #9a3412; }
        .question-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #fff;
        }
        .question-card.correct { border-left: 4px solid #10b981; }
        .question-card.incorrect { border-left: 4px solid #ef4444; }
        .choice-item {
            padding: 0.6rem 0.75rem;
            margin: 0.35rem 0;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        .choice-item.user-answer { border-color: #3b82f6; background: #dbeafe; }
        .choice-item.correct-answer { border-color: #10b981; background: #d1fae5; }
        .choice-item.user-answer.incorrect { border-color: #ef4444; background: #fee2e2; }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Exam Responses</p>
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
                <li><a href="exams.php" class="active"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header mb-3">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <a href="exams.php" class="btn btn-sm btn-outline-secondary mb-2">
                            <i class="bi bi-arrow-left"></i> Back to Exams
                        </a>
                        <h2 id="examTitle" class="mb-1">Exam Responses</h2>
                        <p id="examMeta" class="text-muted mb-0">Loading exam details...</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="exportCsvBtn" disabled>
                        <i class="bi bi-download"></i> Export CSV
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
                </ul>

                <div id="rankingsPanel">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <input type="search" id="searchInput" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Search name, ID, or rank...">
                        <select id="statusFilter" class="form-select form-select-sm" style="max-width: 160px;">
                            <option value="all">All results</option>
                            <option value="passed">Passed only</option>
                            <option value="failed">Failed only</option>
                        </select>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Personnel Rank</th>
                                    <th>Name</th>
                                    <th>Score</th>
                                    <th>Grade (%)</th>
                                    <th style="width:80px;">Standing</th>
                                    <th>Remarks</th>
                                    <th>Time</th>
                                    <th>Submitted</th>
                                    <th style="width:90px;"></th>
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
                                    <th>Examinee</th>
                                    <th>Academic No.</th>
                                    <th>Batch</th>
                                </tr>
                            </thead>
                            <tbody id="notSubmittedBody"></tbody>
                        </table>
                    </div>
                    <div id="notSubmittedEmpty" class="text-center text-muted py-4" style="display:none;">
                        <p class="mb-0">All assigned examinees have submitted.</p>
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
                        <h5 class="modal-title" id="detailModalTitle">Student Response</h5>
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
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/exam-responses.js?v=4"></script>
</body>
</html>
