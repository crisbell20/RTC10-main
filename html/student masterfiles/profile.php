<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/profile.css">
    <style>
        .sidebar-menu .menu-label {
            display: block;
            padding: 12px 20px;
            color: #9ca3af;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: default;
            pointer-events: none;
        }
        .sidebar-menu .menu-label i {
            margin-right: 8px;
            font-size: 1rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Trainee Profile</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="../examinee/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="../examinee/dashboard.php#available-exams"><i class="bi bi-pencil-square"></i>Take Exam</a></li>
                <li><a href="result.php"><i class="bi bi-clipboard-check"></i>Results</a></li>
                <li><a href="perfomance.php"><i class="bi bi-graph-up"></i>Performance</a></li>
                <li><a href="schedule.php"><i class="bi bi-calendar-event"></i>Schedule</a></li>
                <li><a href="profile.php" class="active"><i class="bi bi-person-circle"></i>Profile</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div id="loadingMessage" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading profile...</p>
            </div>

            <div id="profileContent" style="display: none;">
                <div class="page-header profile-page-header">
                    <div>
                        <h2>My Profile</h2>
                        <p class="text-muted mb-0">View your account information and training enrollment</p>
                    </div>
                    <button type="button" class="btn btn-primary" id="changePasswordBtn">
                        <i class="bi bi-shield-lock me-2"></i>Change Password
                    </button>
                </div>

                <div id="passwordAlertBanner" class="alert alert-warning d-none mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    You must change your password before continuing. Use the button above to update it.
                </div>

                <div class="profile-hero section-card">
                    <div class="profile-avatar" id="profileAvatar">--</div>
                    <div class="profile-hero-info">
                        <h3 id="profileFullname">--</h3>
                        <div class="profile-meta" id="profileMeta"></div>
                        <div class="profile-badges" id="profileBadges"></div>
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="section-card">
                            <div class="section-header">
                                <i class="bi bi-person-vcard" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                <div>
                                    <h5>Account Information</h5>
                                    <p>Official record on file (read-only)</p>
                                </div>
                            </div>
                            <div class="section-body">
                                <div class="profile-info-grid" id="accountInfoGrid"></div>
                                <div class="profile-note mt-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    To update your name, rank, academic number, or batch assignment, contact your training administrator.
                                </div>
                            </div>
                        </div>

                        <div class="section-card mt-4">
                            <div class="section-header">
                                <i class="bi bi-mortarboard" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                <div>
                                    <h5>Training Enrollment</h5>
                                    <p>Your course and batch assignments</p>
                                </div>
                            </div>
                            <div class="section-body" id="enrollmentSection">
                                <p class="text-muted mb-0">No enrollment records found.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="section-card">
                            <div class="section-header">
                                <i class="bi bi-shield-check" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                <div>
                                    <h5>Account Security</h5>
                                    <p>Login and password details</p>
                                </div>
                            </div>
                            <div class="section-body">
                                <div class="profile-info-grid" id="securityInfoGrid"></div>
                            </div>
                        </div>

                        <div class="section-card mt-4">
                            <div class="section-header">
                                <i class="bi bi-bar-chart-line" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                <div>
                                    <h5>Exam Summary</h5>
                                    <p>Quick snapshot of your progress</p>
                                </div>
                            </div>
                            <div class="section-body">
                                <div class="profile-stats-grid" id="examSummaryGrid"></div>
                                <div id="lastExamBlock" class="last-exam-block mt-3"></div>
                                <div class="profile-quick-links mt-3">
                                    <a href="result.php" class="btn btn-outline-primary btn-sm me-2">
                                        <i class="bi bi-clipboard-check me-1"></i>View Results
                                    </a>
                                    <a href="../examinee/dashboard.php#available-exams" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil-square me-1"></i>Take Exam
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const MUST_CHANGE_PASSWORD = <?= json_encode($mustChangePassword === 1) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/examinee/profile.js"></script>
</body>
</html>
