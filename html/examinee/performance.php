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
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>My Performance - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/performance.css?v=3">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=14">
</head>
<body class="examinee-portal">
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'My Performance';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php
            $examineeNavFrom = 'examinee';
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
                        <p class="text-muted mb-0">Track your progress</p>
                    </div>
                </div>

                <p id="emptyState" class="text-center text-muted py-5 d-none">No exam results yet</p>

                <div id="performanceBody" class="perf-stack">
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

                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-check-circle" style="color: var(--success-color); font-size: 1.3rem;"></i>
                            <div>
                                <h5>On Track</h5>
                                <p>Subjects with 75% average or above</p>
                            </div>
                        </div>
                        <div class="section-body" id="strongSubjectsList"></div>
                    </div>

                    <div class="section-card">
                        <div class="section-header">
                            <i class="bi bi-exclamation-triangle" style="color: var(--warning-color); font-size: 1.3rem;"></i>
                            <div>
                                <h5>At Risk</h5>
                                <p>Subjects with 74% average or below</p>
                            </div>
                        </div>
                        <div class="section-body" id="weakSubjectsList"></div>
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
                </div>
                <div class="modal-body" id="subjectDetailBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php
    $examineeNavFrom = 'examinee';
    $examineeNavActive = 'performance';
    require __DIR__ . '/../includes/examinee-bottom-nav.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/examinee-responsive-nav.js?v=7"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/parse-datetime.js?v=1"></script>
    <script src="../../js/examinee/performance.js?v=14"></script>
</body>
</html>
