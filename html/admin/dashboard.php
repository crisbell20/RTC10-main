<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check if user is Admin
if ($_SESSION['user_role'] !== 'Admin') {
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
    <title>Admin Dashboard - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    </head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Administration & System Management';
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
                <li><a href="../masterfiles/users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="../masterfiles/courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="../masterfiles/subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="../masterfiles/exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="../masterfiles/question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="../masterfiles/reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../masterfiles/cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="../masterfiles/archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="../masterfiles/audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="../masterfiles/settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
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
                        <div class="stat-label">Active Users</div>
                        <div class="stat-icon icon-blue"><i class="bi bi-people-fill"></i></div>
                    </div>
                    <div class="stat-value" id="totalUsers">--</div>
                    <div class="stat-footer">
                        <span id="usersChange">--</span>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-header">
                        <div class="stat-label">Active Courses</div>
                        <div class="stat-icon icon-green"><i class="bi bi-book-fill"></i></div>
                    </div>
                    <div class="stat-value" id="activeCourses">--</div>
                    <div class="stat-footer">
                        <span id="coursesChange">--</span>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Active Exams</div>
                        <div class="stat-icon icon-purple"><i class="bi bi-pencil-square"></i></div>
                    </div>
                    <div class="stat-value" id="activeExams">--</div>
                    <div class="stat-footer">
                        <span id="activeExamsLabel">Published exams</span>
                    </div>
                </div>

                <a href="../masterfiles/cheating-incidents.php" class="stat-card red text-decoration-none text-reset">
                    <div class="stat-header">
                        <div class="stat-label">Cheating Incidents</div>
                        <div class="stat-icon icon-red"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    </div>
                    <div class="stat-value" id="cheatingIncidents">--</div>
                    <div class="stat-footer">
                        <span id="cheatingIncidentsLabel">Across all exams</span>
                    </div>
                </a>
            </div>

            <!-- System Activity Log -->
            <div class="section-card">
                <div class="section-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                        <div>
                            <h5 class="mb-0">Recent Activity</h5>
                            <p class="mb-0">Latest audit log entries</p>
                        </div>
                    </div>
                    <a href="../masterfiles/audit-logs.php" class="btn btn-sm btn-outline-primary">View all</a>
                </div>
                <div class="section-body">
                    <div class="table-responsive">
                    <table class="table text-nowrap">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Module</th>
                                <th>Timestamp</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="recentAuditBody">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    <i class="bi bi-hourglass-split"></i> Loading activity...
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
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/admin/dashboard.js?v=2"></script>
</body>
</html>
