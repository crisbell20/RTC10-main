<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
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
    <title>System Settings - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'System Settings';
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
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php" class="active"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h2>System Settings</h2>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-shield-lock" style="font-size: 1.3rem; color: var(--primary-color);"></i>
                    <div>
                        <h5>Security Policy</h5>
                        <p class="text-muted small mb-0">These values apply on the next login attempt or page load. Existing lockouts keep their original duration until they expire.</p>
                    </div>
                </div>
                <div class="section-body">
                    <form id="securitySettingsForm" class="row g-3" style="max-width: 720px;">
                        <div class="col-md-4">
                            <label class="form-label" for="loginMaxAttempts">Maximum login attempts</label>
                            <input type="number" class="form-control" id="loginMaxAttempts" min="3" max="10" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="loginLockoutMinutes">Lock duration (minutes)</label>
                            <input type="number" class="form-control" id="loginLockoutMinutes" min="5" max="60" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="idleTimeoutMinutes">Auto logout (minutes)</label>
                            <input type="number" class="form-control" id="idleTimeoutMinutes" min="5" max="60" step="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="cheatingAttemptLimit">Cheating attempt limit</label>
                            <input type="number" class="form-control" id="cheatingAttemptLimit" min="1" max="20" step="1" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-add-user" id="saveSecurityBtn">
                                <i class="bi bi-check2-circle me-2"></i>Save security policy
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-hourglass-split" style="font-size: 1.3rem; color: var(--primary-color);"></i>
                    <div>
                        <h5>Retention &amp; Cleanup</h5>
                        <p class="text-muted small mb-0">Changing these values does not delete data immediately. Records become eligible for purge after the configured period.</p>
                    </div>
                </div>
                <div class="section-body">
                    <form id="retentionSettingsForm" class="row g-3" style="max-width: 720px;">
                        <div class="col-md-6">
                            <label class="form-label" for="archiveRetentionDays">Archive retention (days)</label>
                            <input type="number" class="form-control" id="archiveRetentionDays" min="30" max="3650" step="1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="auditRetentionDays">Audit log retention (days)</label>
                            <input type="number" class="form-control" id="auditRetentionDays" min="30" max="3650" step="1" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-add-user" id="saveSettingsBtn">
                                <i class="bi bi-check2-circle me-2"></i>Save settings
                            </button>
                        </div>
                    </form>

                    <hr class="my-4" style="max-width: 720px;">

                    <div style="max-width: 720px;">
                        <h6 class="mb-2">Manual retention purge</h6>
                        <p class="text-muted small mb-3">Permanently delete archived records or audit logs that are older than the retention period. Restore is not possible after purge.</p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-danger btn-sm" id="purgeArchiveBtn">
                                <i class="bi bi-trash me-1"></i>Purge archived records
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="purgeAuditBtn">
                                <i class="bi bi-trash me-1"></i>Purge audit logs
                            </button>
                        </div>
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
    <script src="../../js/masterfiles/settings.js?v=6"></script>
</body>
</html>
