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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examinee Dashboard - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
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
    <!-- Top Navigation -->
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Trainee Assessment & Learning Portal</p>
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
                <li><a href="dashboard.php#available-exams"><i class="bi bi-pencil-square"></i>Take Exam</a></li>
                <li><a href="../student masterfiles/result.php"><i class="bi bi-clipboard-check"></i>Results</a></li>
                <li><a href="../student masterfiles/perfomance.php"><i class="bi bi-graph-up"></i>Performance</a></li>
                <li><a href="../student masterfiles/schedule.php"><i class="bi bi-calendar-event"></i>Schedule</a></li>
                <li><a href="../student masterfiles/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
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
                    <div class="stat-footer">
                        <span id="completionRate">--</span>
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

                <div class="stat-card purple">
                    <div class="stat-header">
                        <div class="stat-label">Learning Points</div>
                        <div class="stat-icon icon-purple"><i class="bi bi-award-fill"></i></div>
                    </div>
                    <div class="stat-value" id="learningPoints">--</div>
                    <div class="stat-footer">
                        <span id="pointsLabel">--</span>
                    </div>
                </div>
            </div>

            <!-- Available Exams -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-pencil-square" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>Available Exams</h5>
                        <p>Exams you can take now</p>
                    </div>
                </div>
                <div class="section-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Description</th>
                                <th>Course</th>
                                <th>Subject</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="examsTableBody">
                            <!-- Data populated from database -->
                        </tbody>
                    </table>
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
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Date Taken</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Status</th>
                                <th>Details</th>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/examinee/examinee-dashboard.js?v=4"></script>
    <script>
        const MUST_CHANGE_PASSWORD = <?= $mustChangePassword ? 'true' : 'false' ?>;

        // Logout handler
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Logout',
                text: 'Are you sure you want to logout?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, logout',
                cancelButtonText: 'No, stay',
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('../../api/auth/logout.php', {
                        method: 'POST'
                    }).then(() => {
                        window.location.href = '../../login.php';
                    }).catch(() => {
                        window.location.href = '../../login.php';
                    });
                }
            });
        });

        // Password reset flow for first login
        function openPasswordResetModal() {
            Swal.fire({
                title: 'Reset Password',
                html: `
                    <div class="text-start">
                        <label class="form-label">Current Password*</label>
                        <input type="password" id="curPwd" class="form-control mb-2">
                        <label class="form-label">New Password*</label>
                        <input type="password" id="newPwd" class="form-control mb-2">
                        <ul class="small text-muted mb-2">
                            <li>8 characters minimum</li>
                            <li>One uppercase letter</li>
                            <li>One lowercase letter</li>
                            <li>One number</li>
                            <li>One special character</li>
                        </ul>
                        <label class="form-label">Confirm New Password*</label>
                        <input type="password" id="confPwd" class="form-control mb-2">
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" id="togglePwdVisibility">
                            <label class="form-check-label" for="togglePwdVisibility">
                                Show passwords
                            </label>
                        </div>
                    </div>
                `,
                didOpen: () => {
                    const cur = document.getElementById('curPwd');
                    const next = document.getElementById('newPwd');
                    const conf = document.getElementById('confPwd');
                    const toggle = document.getElementById('togglePwdVisibility');
                    if (toggle && cur && next && conf) {
                        toggle.addEventListener('change', (e) => {
                            const type = e.target.checked ? 'text' : 'password';
                            cur.type = type;
                            next.type = type;
                            conf.type = type;
                        });
                    }
                },
                focusConfirm: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                confirmButtonText: 'Reset Password',
                showCancelButton: false,
                preConfirm: () => {
                    const current = document.getElementById('curPwd').value;
                    const next = document.getElementById('newPwd').value;
                    const confirm = document.getElementById('confPwd').value;
                    if (!current || !next || !confirm) {
                        Swal.showValidationMessage('All fields are required');
                        return false;
                    }
                    if (next !== confirm) {
                        Swal.showValidationMessage('New password and confirmation do not match');
                        return false;
                    }
                    return { current, next, confirm };
                }
            }).then(result => {
                if (!result.value) return;
                axios.post('../../api/auth/change-password.php', {
                    current_password: result.value.current,
                    new_password: result.value.next,
                    confirm_password: result.value.confirm
                }).then(res => {
                    if (res.data.success) {
                        Swal.fire('Success', 'Password updated successfully', 'success');
                    } else {
                        Swal.fire('Error', res.data.message || 'Failed to update password', 'error')
                            .then(() => openPasswordResetModal());
                    }
                }).catch(err => {
                    const msg = err.response?.data?.message || 'Failed to update password';
                    Swal.fire('Error', msg, 'error').then(() => openPasswordResetModal());
                });
            });
        }

        if (MUST_CHANGE_PASSWORD) {
            // slight delay so UI renders behind the modal
            setTimeout(openPasswordResetModal, 500);
        }
    </script>
</body>
</html>
