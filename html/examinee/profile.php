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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>My Profile - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/profile.css">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=13">
</head>
<body class="examinee-portal">
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
            <?php
            $examineeNavFrom = 'examinee';
            $examineeNavActive = 'profile';
            require __DIR__ . '/../includes/examinee-sidebar.php';
            ?>
        </div>

        <div class="main-content">
            <div id="loadingMessage" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading profile...</p>
            </div>

            <div id="profileContent" style="display: none;">
                <div id="passwordAlertBanner" class="alert alert-warning d-none mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    You are encouraged to change your password. Use Account Security below, or dismiss the prompt to continue with your current password.
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
                                <div class="profile-note mt-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    To update your name, rank, badge number, or batch assignment, contact your training administrator.
                                </div>
                            </div>
                        </div>

                        <div class="section-card mt-4">
                            <div class="section-header">
                                <i class="bi bi-mortarboard" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                                <div>
                                    <h5>Your Course, Batch, and Section Information</h5>
                                </div>
                            </div>
                            <div class="section-body" id="enrollmentSection">
                                <p class="text-muted mb-0">No assignment records found.</p>
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

    <?php
    $examineeNavFrom = 'examinee';
    $examineeNavActive = 'profile';
    require __DIR__ . '/../includes/examinee-bottom-nav.php';
    ?>

    <script>
        const MUST_CHANGE_PASSWORD = <?= json_encode($mustChangePassword === 1) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/examinee-responsive-nav.js?v=7"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/password-policy.js?v=3"></script>
    <script src="../../assets/js/parse-datetime.js?v=1"></script>
    <script src="../../js/examinee/profile.js?v=12"></script>
</body>
</html>
