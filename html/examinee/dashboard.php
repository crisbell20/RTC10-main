<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user is Examinee
if ($_SESSION['user_role'] !== 'Examinee') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$mustChangePassword = isset($_SESSION['must_change_password']) ? (int)$_SESSION['must_change_password'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Exams - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=13">
</head>
<body class="examinee-portal">
    <!-- Top Navigation -->
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Exams';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span class="nav-user-name"><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php
            $examineeNavFrom = 'examinee';
            $examineeNavActive = 'exams';
            require __DIR__ . '/../includes/examinee-sidebar.php';
            ?>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Exams</h2>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-label">Exams Available</div>
                        <div class="stat-icon icon-blue"><i class="bi bi-pencil-square"></i></div>
                    </div>
                    <div class="stat-value" id="examsAvailable">--</div>
                    <div class="stat-footer">
                        <span id="availableStatus">--</span>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-label">Exams Completed</div>
                        <div class="stat-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
                    </div>
                    <div class="stat-value" id="examsCompleted">--</div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Passed / Failed</div>
                        <div class="stat-icon icon-purple"><i class="bi bi-clipboard-check"></i></div>
                    </div>
                    <div class="stat-value" id="passedFailedCount">--</div>
                    <div class="stat-footer">
                        <span id="passedFailedLabel">--</span>
                    </div>
                </div>

                <div class="stat-card red">
                    <div class="stat-header">
                        <div class="stat-label">Average Score</div>
                        <div class="stat-icon icon-red"><i class="bi bi-star-fill"></i></div>
                    </div>
                    <div class="stat-value" id="averageScore">--</div>
                    <div class="stat-footer">
                        <span id="scoreChange">--</span>
                    </div>
                </div>
            </div>

            <!-- Available Exams -->
            <div class="section-card" id="available-exams">
                <div class="section-header">
                    <i class="bi bi-pencil-square" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>Available Exams</h5>
                        <p>Exams you can take now</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="examinee-mobile-cards" id="examsCardsList" aria-label="Available exams"></div>
                    <div class="examinee-desktop-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Description</th>
                                    <th>Subject</th>
                                    <th>Questions</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="examsTableBody">
                                <!-- Data populated from database -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-bar-chart" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>Recent Results</h5>
                        <p>Your latest exam scores and feedback</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="examinee-mobile-cards" id="resultsCardsList" aria-label="Recent results"></div>
                    <div class="examinee-desktop-table">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Date Taken</th>
                                    <th>Score</th>
                                    <th>Average</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <!-- Data populated from database -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $examineeNavFrom = 'examinee';
    $examineeNavActive = 'exams';
    require __DIR__ . '/../includes/examinee-bottom-nav.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/examinee-responsive-nav.js?v=7"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/password-policy.js?v=3"></script>
    <script src="../../assets/js/parse-datetime.js?v=1"></script>
    <script src="../../js/examinee/examinee-dashboard.js?v=17"></script>
    <script>
        const MUST_CHANGE_PASSWORD = <?= $mustChangePassword ? 'true' : 'false' ?>;
    </script>
    <script src="../../js/examinee/first-login-password.js?v=1"></script>
</body>
</html>
