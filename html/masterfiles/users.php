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
    <title>Manage Users - PNP RTC X</title>
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
            <p>User Management</p>
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
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php" class="active"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Manage Users</h2>
                    <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg me-2"></i>Add New User
                    </button>
                </div>
            </div>

            <!-- Message Alert -->
            <div id="messageAlert"></div>

            <!-- Users Table -->
            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-people-fill" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>All Users</h5>
                        <p>System users management</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
                        <div>
                            <label class="form-label small mb-1" for="filterRole">Filter by role</label>
                            <select id="filterRole" class="form-select form-select-sm" style="min-width: 140px;">
                                <option value="All">All</option>
                                <option value="Admin">Admin</option>
                                <option value="CCMD">CCMD</option>
                                <option value="Examinee">Examinee</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="filterStatus">Filter by status</label>
                            <select id="filterStatus" class="form-select form-select-sm" style="min-width: 140px;">
                                <option value="All">All</option>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="ms-auto">
                            <label class="form-label small mb-1" for="filterSearch">Search</label>
                            <input id="filterSearch" type="text" class="form-control form-control-sm" placeholder="Name, email, username">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Username</th>
                                    <th>Academic No.</th>
                                    <th>Personnel Rank</th>
                                    <th>Role</th>
                                    <th>Batch Assignment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                    <th>Date Created</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        <i class="bi bi-hourglass-split"></i> Loading...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign to Batch Modal -->
    <div class="modal fade" id="assignBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Examinee to Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignBatchForm">
                    <input type="hidden" name="user_id" id="assignUserId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Batch *</label>
                            <select class="form-select" name="batch_id" id="batchSelect" required>
                                <option value="">Loading batches...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="Active">Active</option>
                                <option value="Completed">Completed</option>
                                <option value="Dropped">Dropped</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addUserForm">
                    <div class="modal-body">
                        <input type="hidden" id="editUserId" name="edit_user_id" value="">
                        <div class="mb-3">
                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="fullname" name="fullname" 
                                   pattern="[a-zA-Z\s.\-']+" 
                                   minlength="2" 
                                   maxlength="100"
                                   title="Full name must contain only letters, spaces, dots, hyphens, and apostrophes"
                                   required>
                            <small class="form-text text-muted">Letters, spaces, dots, hyphens, and apostrophes only</small>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   maxlength="100"
                                   title="Please enter a valid email address"
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   pattern="[a-zA-Z0-9_\-]+" 
                                   minlength="3" 
                                   maxlength="50"
                                   title="Username must contain at least one letter and only letters, numbers, underscores, and hyphens"
                                   required>
                            <small class="form-text text-muted">Must contain at least one letter (3-50 characters)</small>
                        </div>
                        <div class="mb-3">
                            <label for="academic_number" class="form-label">Academic Number (optional)</label>
                            <input type="text" class="form-control" id="academic_number" name="academic_number"
                                   maxlength="50">
                        </div>
                        <div class="mb-3" id="personnelRankSection">
                            <label for="personnel_rank" class="form-label">Personnel Rank (optional)</label>
                            <select class="form-select" id="personnel_rank" name="personnel_rank">
                                <option value="">— None —</option>
                            </select>
                            <small class="form-text text-muted">PNP rank (e.g. Pat, SSg, PLt)</small>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span id="passwordOptionalLabel" style="display:none;">(Leave blank to keep current)</span></label>
                            <input type="text" class="form-control" id="password" name="password" 
                                   minlength="6"
                                   maxlength="100"
                                   title="Password must be at least 6 characters"
                                   required>
                            <small class="form-text text-muted" id="passwordHint">Minimum 6 characters</small>
                        </div>
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Role</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">Select a role</option>
                            </select>
                        </div>
                        
                        <!-- Batch Enrollment Section (visible only for Examinees) -->
                        <div class="mb-3" id="batchEnrollmentSection" style="display: none;">
                            <label class="form-label">Batch Enrollment</label>
                            <div id="userBatchList" class="border rounded p-2 mb-2" style="max-height: 200px; overflow-y: auto;">
                                <p class="text-muted text-center py-2 mb-0">No batches assigned</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addToBatchBtn">
                                <i class="bi bi-plus"></i> Add to Batch
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="addUserSubmitBtn" class="btn btn-add-user">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add to Batch Modal -->
    <div class="modal fade" id="addToBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Examinee to Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addToBatchForm">
                    <input type="hidden" name="user_id" id="addToBatchUserId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Batch *</label>
                            <select class="form-select" name="batch_id" id="addToBatchSelect" required>
                                <option value="">Loading batches...</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user">Add to Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/users.js"></script>
</body>
</html>
