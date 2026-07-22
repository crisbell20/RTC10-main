<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
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
    <title>Reports & Analytics - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/reports.css">
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Reports & Analytics</p>
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
                <li><a href="reports.php" class="active"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../admin/cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2>Reports & Analytics</h2>
                    <button class="btn btn-add-user" id="exportReportBtn">
                        <i class="bi bi-download me-2"></i>Export CSV
                    </button>
                </div>
            </div>

            <div class="filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Batch</label>
                        <select id="filterBatch" class="form-select form-select-sm">
                            <option value="">All batches</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Section</label>
                        <select id="filterSection" class="form-select form-select-sm" disabled>
                            <option value="">All sections</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                    <div id="filterChips" class="reports-filter-chips"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto d-none" id="clearFiltersBtn">
                        <i class="bi bi-x-circle me-1"></i>Clear filters
                    </button>
                </div>
            </div>

            <div id="reportsLoadingBar" class="reports-loading-bar d-none" role="status" aria-live="polite">
                <div class="spinner-border spinner-border-sm text-primary me-2" aria-hidden="true"></div>
                Updating reports…
            </div>

            <ul class="nav nav-tabs mb-4" id="reportTabs">
                <li class="nav-item"><button class="nav-link active" data-tab="overview" type="button">Overview</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="students" type="button">By Student</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="subjects" type="button">By Subject</button></li>
            </ul>

            <!-- OVERVIEW TAB -->
            <div id="tabOverview">
                <div class="row g-3 mb-4" id="metricsRow">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-people-fill metric-icon text-primary"></i>
                            <div class="metric-value" id="activeExaminees">--</div>
                            <div class="metric-label">Students Assigned</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-pencil-square metric-icon text-success"></i>
                            <div class="metric-value" id="examsAdministered">--</div>
                            <div class="metric-label">Exams</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-check-circle-fill metric-icon text-warning"></i>
                            <div class="metric-value" id="passRate">--</div>
                            <div class="metric-label">Overall Pass Rate</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-star-fill metric-icon text-purple"></i>
                            <div class="metric-value" id="avgScore">--</div>
                            <div class="metric-label">Average Score</div>
                        </div>
                    </div>
                </div>

                <div class="section-card mb-3">
                    <div class="section-header">
                        <div>
                            <h5><i class="bi bi-exclamation-triangle text-danger me-2"></i>At-Risk Students</h5>
                            <p id="atRiskSummary">Students flagged when overdue exams are missing or pass rate falls below thresholds</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none" id="viewAllAtRiskBtn">
                            View all at-risk
                        </button>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Assigned</th>
                                        <th>Taken</th>
                                        <th>Missing</th>
                                        <th>Avg %</th>
                                        <th>Pass Rate</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="atRiskBody">
                                    <tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Performance Trend</h5><p>Average score per exam, ordered by date</p></div></div>
                            <div class="section-body">
                                <div class="chart-wrapper" data-chart="performance">
                                    <div class="chart-container"><canvas id="performanceChart"></canvas></div>
                                    <div class="chart-empty d-none" id="performanceChartEmpty">No finished attempts for the selected filters.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Pass/Fail Distribution</h5><p id="passFailSubtitle">Due assigned exams in scope</p></div></div>
                            <div class="section-body">
                                <div class="chart-wrapper" data-chart="passFail">
                                    <div class="chart-container"><canvas id="passFailChart"></canvas></div>
                                    <div class="chart-empty d-none" id="passFailChartEmpty">No due assigned exams in scope for the selected filters.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Top Performing Subjects</h5><p>Top 5 by average score</p></div></div>
                            <div class="section-body">
                                <div class="chart-wrapper" data-chart="topSubjects">
                                    <div class="chart-container"><canvas id="subjectsChart"></canvas></div>
                                    <div class="chart-empty d-none" id="subjectsChartEmpty">No subject data for the selected filters.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Subjects Needing Attention</h5><p>Bottom 5 by average score</p></div></div>
                            <div class="section-body">
                                <div class="chart-wrapper" data-chart="weakSubjects">
                                    <div class="chart-container"><canvas id="weakSubjectsChart"></canvas></div>
                                    <div class="chart-empty d-none" id="weakSubjectsChartEmpty">No subject data for the selected filters.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STUDENTS TAB -->
            <div id="tabStudents" style="display:none;">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select id="studentStatusFilter" class="form-select form-select-sm" style="max-width:200px;">
                        <option value="all">All students</option>
                        <option value="on_track">On track</option>
                        <option value="needs_review">Needs review</option>
                        <option value="at_risk">At risk</option>
                    </select>
                </div>
                <div class="section-card">
                    <div class="section-header">
                        <div><h5>Student Performance Summary</h5><p>Overall results rolled up per examinee</p></div>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Batch</th>
                                        <th>Section</th>
                                        <th>Assigned</th>
                                        <th>Taken</th>
                                        <th>Missing</th>
                                        <th>Avg %</th>
                                        <th>Pass Rate</th>
                                        <th>Best Subject</th>
                                        <th>Weakest Subject</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="studentSummaryBody"></tbody>
                            </table>
                        </div>
                        <div id="studentSummaryEmpty" class="text-center text-muted py-4" style="display:none;">No student data for selected filters.</div>
                    </div>
                </div>
            </div>

            <!-- SUBJECTS TAB -->
            <div id="tabSubjects" style="display:none;">
                <div class="section-card">
                    <div class="section-header">
                        <div><h5>Subject Performance Summary</h5><p>Health metrics per subject</p></div>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Subject</th>
                                        <th>Course</th>
                                        <th>Exams</th>
                                        <th>Students</th>
                                        <th>Avg %</th>
                                        <th>Pass Rate</th>
                                        <th>Range</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="subjectSummaryBody"></tbody>
                            </table>
                        </div>
                        <div id="subjectSummaryEmpty" class="text-center text-muted py-4" style="display:none;">No subject data for selected filters.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="studentDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="studentDetailTitle">Student Detail</h5>
                        <small class="text-muted" id="studentDetailSub"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailBody"></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="subjectDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="subjectDetailTitle">Subject Detail</h5>
                        <small class="text-muted" id="subjectDetailSub"></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="subjectDetailBody"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script>window.REPORTS_LINK_BASE = '';</script>
    <script src="../../js/masterfiles/reports.js?v=20"></script>
</body>
</html>
