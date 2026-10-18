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
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Reports & Analytics - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/reports.css?v=2">
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Reports & Analytics';
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
                <li><a href="reports.php" class="active"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2>Reports & Analytics</h2>
                    <button class="btn btn-add-user d-none" id="exportReportBtn" hidden>
                        <i class="bi bi-download me-2"></i>Export
                    </button>
                </div>
            </div>

            <div class="filter-card">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Batch</label>
                        <select id="filterBatch" class="form-select form-select-sm">
                            <option value="">All batches</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Section</label>
                        <select id="filterSection" class="form-select form-select-sm" disabled>
                            <option value="">All sections</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Subject</label>
                        <select id="filterSubject" class="form-select form-select-sm">
                            <option value="">All subjects</option>
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
                <li class="nav-item"><button class="nav-link" data-tab="trainees" type="button">By Trainee</button></li>
                <li class="nav-item"><button class="nav-link" data-tab="subjects" type="button">By Subject</button></li>
            </ul>

            <!-- OVERVIEW TAB -->
            <div id="tabOverview">
                <div class="stats-grid mb-4" id="metricsRow">
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <div class="stat-label">Trainees Assigned</div>
                        </div>
                        <div class="stat-value" id="activeTrainees">--</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-header">
                            <div class="stat-label">Exams</div>
                        </div>
                        <div class="stat-value" id="examsAdministered">--</div>
                    </div>
                    <div class="stat-card purple">
                        <div class="stat-header">
                            <div class="stat-label">Overall Pass Rate</div>
                        </div>
                        <div class="stat-value" id="passRate">--</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <div class="stat-label">Average Grade</div>
                        </div>
                        <div class="stat-value" id="avgScore">--</div>
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
                            <div class="section-header"><div><h5>Pass/Fail Distribution</h5><p id="passFailSubtitle">Exams in scope</p></div></div>
                            <div class="section-body">
                                <div class="chart-wrapper" data-chart="passFail">
                                    <div class="chart-container"><canvas id="passFailChart"></canvas></div>
                                    <div class="chart-empty d-none" id="passFailChartEmpty">No exams in scope for the selected filters.</div>
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

            <!-- TRAINEES TAB -->
            <div id="tabTrainees" style="display:none;">
                <div class="stats-grid mb-4" id="traineeMetricsRow">
                    <div class="stat-card blue">
                        <div class="stat-header">
                            <div class="stat-label">Trainees</div>
                        </div>
                        <div class="stat-value" id="traineeTotalCount">--</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-header">
                            <div class="stat-label">On Track</div>
                        </div>
                        <div class="stat-value" id="traineeOnTrackCount">--</div>
                    </div>
                    <div class="stat-card red">
                        <div class="stat-header">
                            <div class="stat-label">At Risk</div>
                        </div>
                        <div class="stat-value" id="traineeAtRiskCount">--</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select id="traineeStatusFilter" class="form-select form-select-sm" style="max-width:200px;">
                        <option value="all">All Trainees</option>
                        <option value="on_track">On Track</option>
                        <option value="at_risk">At Risk</option>
                    </select>
                </div>
                <div class="section-card">
                    <div class="section-header">
                        <div>
                            <h5 id="traineeSummaryTitle">Trainee Rankings</h5>
                            <p id="traineeSummarySub">Ranked by average grade</p>
                        </div>
                    </div>
                    <div class="section-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light" id="traineeSummaryHead">
                                    <tr>
                                        <th>Standing</th>
                                        <th>Rank</th>
                                        <th>Trainee</th>
                                        <th>Assigned</th>
                                        <th>Taken</th>
                                        <th>Missing</th>
                                        <th>Average</th>
                                        <th>Pass Rate</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="traineeSummaryBody"></tbody>
                            </table>
                        </div>
                        <div id="traineeSummaryEmpty" class="text-center text-muted py-4" style="display:none;">No trainee data for selected filters.</div>
                    </div>
                </div>
            </div>

            <!-- SUBJECTS TAB -->
            <div id="tabSubjects" style="display:none;">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <select id="subjectSortFilter" class="form-select form-select-sm" style="max-width:220px;">
                        <option value="top_to_lowest">Top to lowest</option>
                        <option value="lowest_to_top">Lowest to top</option>
                    </select>
                </div>
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
                                        <th>Avg %</th>
                                        <th>Pass Rate</th>
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

    <div class="modal fade" id="traineeDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="traineeDetailTitle">Trainee Detail</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="traineeDetailBody"></div>
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
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script>window.REPORTS_LINK_BASE = '';</script>
    <script src="../../js/masterfiles/reports.js?v=46"></script>
</body>
</html>
