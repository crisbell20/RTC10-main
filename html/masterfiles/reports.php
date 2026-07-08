<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    header('Location: ../../html/auth/login.html');
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
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label small">Date Range</label>
                        <select id="dateRange" class="form-select form-select-sm">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                            <option value="all">All time</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Subject</label>
                        <select id="filterSubject" class="form-select form-select-sm">
                            <option value="">All subjects</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Batch</label>
                        <select id="filterBatch" class="form-select form-select-sm">
                            <option value="">All batches</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Search student</label>
                        <input type="search" id="studentSearch" class="form-control form-control-sm" placeholder="Name or ID">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">&nbsp;</label>
                        <button class="btn btn-primary btn-sm w-100" id="applyFiltersBtn">
                            <i class="bi bi-funnel me-1"></i>Apply
                        </button>
                    </div>
                </div>
            </div>

            <ul class="nav nav-tabs mb-4" id="reportTabs">
                <li class="nav-item"><button class="nav-link active" data-tab="overview" type="button">Overview</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="students" type="button">By Student</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="subjects" type="button">By Subject</button></li>
            </ul>

            <!-- OVERVIEW TAB -->
            <div id="tabOverview">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-people-fill metric-icon text-primary"></i>
                            <div class="metric-value" id="activeExaminees">--</div>
                            <div class="metric-label">Active Examinees</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <i class="bi bi-pencil-square metric-icon text-success"></i>
                            <div class="metric-value" id="examsAdministered">--</div>
                            <div class="metric-label">Exams Administered</div>
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
                            <p>Students needing intervention based on pass rate and scores</p>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Student</th>
                                        <th>Exams</th>
                                        <th>Avg %</th>
                                        <th>Pass Rate</th>
                                        <th>Status</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="atRiskBody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Performance Trend</h5><p>Average scores over time</p></div></div>
                            <div class="section-body"><div class="chart-container"><canvas id="performanceChart"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Pass/Fail Distribution</h5><p>Finished submissions</p></div></div>
                            <div class="section-body"><div class="chart-container"><canvas id="passFailChart"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Top Performing Subjects</h5><p>Highest average scores</p></div></div>
                            <div class="section-body"><div class="chart-container"><canvas id="subjectsChart"></canvas></div></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="section-card">
                            <div class="section-header"><div><h5>Subjects Needing Attention</h5><p>Lowest pass rates (min. 3 attempts)</p></div></div>
                            <div class="section-body"><div class="chart-container"><canvas id="weakSubjectsChart"></canvas></div></div>
                        </div>
                    </div>
                </div>

                <div class="section-card mb-3">
                    <div class="section-header">
                        <div>
                            <h5>Exam Participation</h5>
                            <p>Click a bar to view exam responses</p>
                        </div>
                    </div>
                    <div class="section-body"><div class="chart-container"><canvas id="participationChart"></canvas></div></div>
                </div>

                <div class="section-card">
                    <div class="section-header">
                        <div><h5>Recent Exam Attempts</h5><p>Latest finished submissions</p></div>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Examinee</th>
                                        <th>Exam</th>
                                        <th>Subject</th>
                                        <th>Date</th>
                                        <th>Score</th>
                                        <th>%</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="resultsTableBody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted" id="attemptsPaginationInfo"></small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" id="attemptsPrevBtn" disabled>Prev</button>
                                <button class="btn btn-outline-secondary" id="attemptsNextBtn" disabled>Next</button>
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
                                        <th>Exams</th>
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
                                        <th>Attempts</th>
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
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/reports.js?v=3"></script>
</body>
</html>
