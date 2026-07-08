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
    <link rel="stylesheet" href="../../assets/css/ccmd.css">
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
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examination</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="#"><i class="bi bi-people-fill"></i>Enrolled Trainees</a></li>
                <li><a href="monitor-examination.php#incidents"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="reports.php" class="active"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../masterfiles/audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Reports & Analytics</h2>
                    <button class="btn btn-add-user" onclick="exportReport()">
                        <i class="bi bi-download me-2"></i>Export Report
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-card">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Date Range</label>
                        <select id="dateRange" class="form-select form-select-sm">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="365">Last year</option>
                            <option value="all">All time</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Course</label>
                        <select id="filterCourse" class="form-select form-select-sm">
                            <option value="">All courses</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Subject</label>
                        <select id="filterSubject" class="form-select form-select-sm">
                            <option value="">All subjects</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">&nbsp;</label>
                        <button class="btn btn-primary btn-sm w-100" onclick="loadReports()">
                            <i class="bi bi-funnel me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="metric-card">
                        <i class="bi bi-people-fill" style="font-size: 2rem; color: #2563eb;"></i>
                        <div class="metric-value" id="totalExaminees">--</div>
                        <div class="metric-label">Total Examinees</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <i class="bi bi-pencil-square" style="font-size: 2rem; color: #10b981;"></i>
                        <div class="metric-value" id="totalExams">--</div>
                        <div class="metric-label">Total Exams</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <i class="bi bi-check-circle-fill" style="font-size: 2rem; color: #f59e0b;"></i>
                        <div class="metric-value" id="completionRate">--</div>
                        <div class="metric-label">Completion Rate</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <i class="bi bi-star-fill" style="font-size: 2rem; color: #a855f7;"></i>
                        <div class="metric-value" id="avgScore">--</div>
                        <div class="metric-label">Average Score</div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h5>Exam Performance Trend</h5>
                                <p>Average scores over time</p>
                            </div>
                        </div>
                        <div class="section-body">
                            <div class="chart-container">
                                <canvas id="performanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h5>Pass/Fail Distribution</h5>
                                <p>Overall exam results</p>
                            </div>
                        </div>
                        <div class="section-body">
                            <div class="chart-container">
                                <canvas id="passFailChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h5>Top Performing Subjects</h5>
                                <p>Average scores by subject</p>
                            </div>
                        </div>
                        <div class="section-body">
                            <div class="chart-container">
                                <canvas id="subjectsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="section-card">
                        <div class="section-header">
                            <div>
                                <h5>Exam Participation</h5>
                                <p>Number of examinees per exam</p>
                            </div>
                        </div>
                        <div class="section-body">
                            <div class="chart-container">
                                <canvas id="participationChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Table -->
            <div class="section-card">
                <div class="section-header">
                    <div>
                        <h5>Detailed Exam Results</h5>
                        <p>Complete breakdown of all exam attempts</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Examinee</th>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Score</th>
                                    <th>Percentage</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/reports.js"></script>
</body>
</html>
