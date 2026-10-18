<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'CCMD') {
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
    <title>My Profile - CCMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/profile.css">
    </head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'My Profile';
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
                <li><a href="profile.php" class="active"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examinations</a></li>
                <li><a href="exams.php"><i class="bi bi-journal-text"></i>View Exams</a></li>
                <li><a href="enrolled-trainees.php"><i class="bi bi-people-fill"></i>View Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
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
                                </div>
                            </div>
                            <div class="section-body">
                                <div class="profile-info-grid" id="accountInfoGrid"></div>
                                <div class="mt-4 pt-3 border-top">
                                    <label class="form-label" for="profileEmailInput">Email address</label>
                                    <div class="input-group input-group-sm">
                                        <input type="email" class="form-control" id="profileEmailInput" maxlength="255" autocomplete="email">
                                        <button type="button" class="btn btn-primary" id="saveEmailBtn">Save</button>
                                    </div>
                                </div>
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
                                <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="changePasswordBtn">
                                    <i class="bi bi-shield-lock me-1"></i>Change Password
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>window.STAFF_PROFILE_DASHBOARD_URL = 'dashboard.php';</script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/password-policy.js?v=3"></script>
    <script src="../../js/staff/profile.js?v=4"></script>
</body>
</html>
