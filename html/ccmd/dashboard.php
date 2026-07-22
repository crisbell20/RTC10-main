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
    <title>CCMD Dashboard - PNP RTC X</title>
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
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Career Course Management Division</p>
        </div>
        <div class="nav-user">
            <span><?= $userName ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="active"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examination</a></li>
                <li><a href="../ccmd/exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="monitor-examination.php"><i class="bi bi-people-fill"></i>Enrolled Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href=""><i class="bi bi-clock-history"></i>Audit Logs</a></li>
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
                        <span class="trend-up">+12%</span>
                    </div>
                </div>

                <div class="stat-card red">
                    <div class="stat-header">
                        <div class="stat-label">Completed Today</div>
                        <div class="stat-icon icon-red"><i class="bi bi-check2-circle"></i></div>
                    </div>
                    <div class="stat-value" id="completedToday">--</div>
                    <div class="stat-footer">
                        <span>Sessions finished today</span>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Published Exams</div>
                        <div class="stat-icon icon-purple"><i class="bi bi-journal-check"></i></div>
                    </div>
                    <div class="stat-value" id="publishedExams">--</div>
                    <div class="stat-footer">
                        <span>Available for monitoring</span>
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
                    <table class="table">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/ccmd/ccmd-dashboard.js"></script>
</body>
</html>
