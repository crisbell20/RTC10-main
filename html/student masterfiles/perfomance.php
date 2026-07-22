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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>My Performance - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/performance.css">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=4">
</head>
<body class="examinee-portal">
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
            <?php
            $examineeNavFrom = 'masterfiles';
            $examineeNavActive = 'performance';
            require __DIR__ . '/../includes/examinee-sidebar.php';
            ?>
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
                    <a href="../examinee/dashboard.php" class="btn btn-primary mt-2">
                        <i class="bi bi-pencil-square me-1"></i>Go to Exams
                    </a>
                </div>

                <div id="performanceBody">
                    <div id="statusBanner" class="perf-status-banner mb-4"></div>

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

                    <div class="row g-4 mb-4">
                        <div class="col-lg-8">
                            <div class="section-card">
                                <div class="section-header">
                                    <i class="bi bi-graph-up-arrow" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                    <div>
                                        <h5>Score Trend</h5>
                                        <p>Your scores across all completed exams</p>
                                    </div>
                                </div>
                                <div class="section-body">
                                    <div class="chart-container">
                                        <canvas id="trendChart"></canvas>
                                    </div>
                                    <p id="trendEmpty" class="text-muted text-center py-4 d-none">Complete more exams to see your overall score trend.</p>
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
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Detail Modal -->
    <div class="modal fade" id="subjectDetailModal" tabindex="-1" aria-labelledby="subjectDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
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
                    <a href="../examinee/dashboard.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square me-1"></i>View Exams
                    </a>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $examineeNavFrom = 'masterfiles';
    $examineeNavActive = 'performance';
    require __DIR__ . '/../includes/examinee-bottom-nav.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/examinee-responsive-nav.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/examinee/performance.js?v=4"></script>
</body>
</html>
