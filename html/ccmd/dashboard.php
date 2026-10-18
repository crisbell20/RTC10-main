<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user is CCMD
if ($_SESSION['user_role'] !== 'CCMD') {
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
    <title>CCMD Dashboard - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    </head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Chief of Curriculum Management Division';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="dashboard.php" class="active"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examinations</a></li>
                <li><a href="../ccmd/exams.php"><i class="bi bi-journal-text"></i>View Exams</a></li>
                <li><a href="enrolled-trainees.php"><i class="bi bi-people-fill"></i>View Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Dashboard</h2>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-header">
                        <div class="stat-label">Total Active Exams</div>
                        <div class="stat-icon icon-blue"><i class="bi bi-book-fill"></i></div>
                    </div>
                    <div class="stat-value">3</div>
                    <div class="stat-footer">
                        <span class="trend-up">Live monitoring</span>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-label">Total Participants</div>
                        <div class="stat-icon icon-green"><i class="bi bi-people-fill"></i></div>
                    </div>
                    <div class="stat-value">142</div>
                    <div class="stat-footer">
                        <span class="trend-up">Currently taking exams</span>
                    </div>
                </div>
            </div>

            <!-- Live Exam Monitoring -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-activity" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>Live Exam Monitoring</h5>
                        <p>Real-time exam activity tracking</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="table-responsive">
                    <table class="table text-nowrap">
                        <thead>
                            <tr>
                                <th>Exam Name</th>
                                <th>Course</th>
                                <th>Total Participants</th>
                                <th>Active Participants</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="examTableBody">
                            <!-- Data populated from database -->
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
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/ccmd/ccmd-dashboard.js"></script>
</body>
</html>
