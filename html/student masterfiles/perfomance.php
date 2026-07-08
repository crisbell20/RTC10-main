<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
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
    <title>My Performance - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/performance.css">
    <style>
        .sidebar-menu .menu-label {
            display: block;
            padding: 12px 20px;
            color: #9ca3af;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: default;
            pointer-events: none;
        }
        .sidebar-menu .menu-label i {
            margin-right: 8px;
            font-size: 1rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Trainee Performance</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="../examinee/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="../examinee/dashboard.php#available-exams"><i class="bi bi-pencil-square"></i>Take Exam</a></li>
                <li><a href="result.php"><i class="bi bi-clipboard-check"></i>Results</a></li>
                <li><a href="perfomance.php" class="active"><i class="bi bi-graph-up"></i>Performance</a></li>
                <li><a href="schedule.php"><i class="bi bi-calendar-event"></i>Schedule</a></li>
                <li><a href="profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div id="loadingMessage" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading performance data...</p>
            </div>

            <div id="performanceContent" style="display: none;">
                <div class="page-header perf-page-header">
                    <div>
                        <h2>My Performance</h2>
                        <p class="text-muted mb-0">Track your progress by subject and discover where to focus your study</p>
                    </div>
                    <div class="perf-filters">
                        <label class="form-label small mb-1" for="dateRangeFilter">Period</label>
                        <select id="dateRangeFilter" class="form-select form-select-sm">
                            <option value="all" selected>All time</option>
                            <option value="90">Last 90 days</option>
                            <option value="30">Last 30 days</option>
                        </select>
                    </div>
                </div>

                <div id="emptyState" class="section-card text-center py-5 d-none">
                    <i class="bi bi-bar-chart text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No performance data yet</h5>
                    <p class="text-muted">Complete your first exam to see performance insights and study recommendations.</p>
                    <a href="../examinee/dashboard.php#available-exams" class="btn btn-primary mt-2">
                        <i class="bi bi-pencil-square me-1"></i>Take an Exam
                    </a>
                </div>

                <div id="performanceBody">
                    <div id="statusBanner" class="perf-status-banner mb-4"></div>

                    <div class="stats-grid mb-4" id="summaryStats"></div>

                    <div class="row g-4 mb-4">
                        <div class="col-lg-8">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="bi bi-graph-up-arrow" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                    <div>
                                        <h5>Score Trend</h5>
                                        <p>Your average scores over time</p>
                                    </div>
                                </div>
                                <div class="section-body">
                                    <div class="chart-container">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                    <p id="trendEmpty" class="text-muted text-center py-4 d-none">Not enough data for a trend chart yet.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="section-card h-100">
                                <div class="section-header">
                                    <i class="bi bi-bullseye" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                    <div>
                                        <h5>Study Focus</h5>
                                        <p>Where you should study more</p>
                                    </div>
                                </div>
                                <div class="section-body" id="recommendationsList"></div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="bi bi-check-circle" style="color: var(--success-color); font-size: 1.3rem;"></i>
                                    <div>
                                        <h5>Strong Subjects</h5>
                                        <p>Areas where you perform well</p>
                                    </div>
                                </div>
                                <div class="section-body" id="strongSubjectsList"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="bi bi-exclamation-triangle" style="color: var(--warning-color); font-size: 1.3rem;"></i>
                                    <div>
                                        <h5>Needs Improvement</h5>
                                        <p>Subjects that need more attention</p>
                                    </div>
                                </div>
                                <div class="section-body" id="weakSubjectsList"></div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-journal-text" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                            <div>
                                <h5>Subject Performance</h5>
                                <p>Click a subject to view detailed performance and exam history</p>
                            </div>
                        </div>
                        <div class="section-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover perf-subjects-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Course</th>
                                            <th>Exams</th>
                                            <th>Avg Score</th>
                                            <th>Pass Rate</th>
                                            <th>Trend</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="subjectsTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Detail Modal -->
    <div class="modal fade" id="subjectDetailModal" tabindex="-1" aria-labelledby="subjectDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectDetailModalLabel">Subject Performance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="subjectDetailBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="result.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-clipboard-check me-1"></i>View All Results
                    </a>
                    <a href="../examinee/dashboard.php#available-exams" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>Take Exam
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/examinee/performance.js"></script>
</body>
</html>
