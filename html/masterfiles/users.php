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
    <title>Manage Users - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    </head>
<body>
    <!-- Top Navigation -->
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'User Management';
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
                <li><a href="../admin/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php" class="active"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h2>Manage Users</h2>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="openBulkImportModalBtn" class="btn btn-outline">
                            <i class="bi bi-upload me-2"></i>Import Trainees
                        </button>
                        <button type="button" id="openAddUserModalBtn" class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-plus-lg me-2"></i>Add New User
                        </button>
                    </div>
                </div>
            </div>

            <!-- Message Alert -->
            <div id="messageAlert"></div>

            <!-- Users Table -->
            <div class="section-card">
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
                            </select>
                        </div>
                        <div class="ms-auto">
                            <label class="form-label small mb-1" for="filterSearch">Search</label>
                            <input id="filterSearch" type="text" class="form-control form-control-sm" placeholder="Name, academic no., email">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Badge No.</th>
                                    <th>Personnel Rank</th>
                                    <th>Role</th>
                                    <th>Batch Assignment</th>
                                    <th>Status</th>
                                    <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
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

    <!-- Assign Modal -->
    <div class="modal fade" id="assignBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="assignBatchForm">
                    <input type="hidden" name="user_id" id="assignUserId">
                    <div class="modal-body">
                        <div id="assignBatchFormAlert"></div>
                        <div id="assignEnrollmentWarning" class="alert alert-warning d-none small mb-3"></div>
                        <p class="text-muted small">Each trainee can only belong to one course, batch, and section at a time.</p>
                        <div class="mb-3">
                            <label class="form-label" for="assignCourseSelect">Course *</label>
                            <select class="form-select" id="assignCourseSelect" required>
                                <option value="">Loading courses...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="assignParentBatchSelect">Batch *</label>
                            <select class="form-select" id="assignParentBatchSelect" required disabled>
                                <option value="">Select a course first</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="assignSectionSelect">Section *</label>
                            <select class="form-select" id="assignSectionSelect" name="section_id" required disabled>
                                <option value="">Select a batch first</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user" id="assignBatchSubmitBtn">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Trainees Modal -->
    <div class="modal fade" id="bulkImportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Trainees</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Each trainee can only belong to one course, batch, and section. Existing assignments must be removed before reassigning.</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="importCourseSelect">Course *</label>
                            <select class="form-select" id="importCourseSelect">
                                <option value="">Select a course</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="importBatchSelect">Batch *</label>
                            <select class="form-select" id="importBatchSelect" disabled>
                                <option value="">Select a course first</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="importSectionSelect">Section *</label>
                            <select class="form-select" id="importSectionSelect" disabled>
                                <option value="">Select a batch first</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="importFile">Excel File *</label>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="flex-grow-1" style="min-width: 220px;">
                                <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                            </div>
                            <button type="button" id="downloadImportTemplateBtn" class="btn btn-outline text-nowrap">
                                <i class="bi bi-download me-1"></i>Download Template
                            </button>
                            <button type="button" id="previewImportBtn" class="btn btn-outline text-nowrap" disabled>
                                <i class="bi bi-search me-1"></i>Preview
                            </button>
                        </div>
                    </div>
                    <div id="importCapacityInfo" class="alert alert-info d-none py-2 small mb-3"></div>
                    <div id="importSummary" class="d-none mb-2 small"></div>
                    <div class="table-responsive" id="importPreviewWrap" style="max-height: 320px; display: none;">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Row</th>
                                    <th>Name</th>
                                    <th>Badge No.</th>
                                    <th>Rank</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="importPreviewBody"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="commitImportBtn" class="btn btn-add-user" disabled>
                        <i class="bi bi-upload me-2"></i>Import Trainees
                    </button>
                </div>
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
                        <div id="addUserMessageAlert"></div>
                        <input type="hidden" id="editUserId" name="edit_user_id" value="">
                        <div class="mb-3">
                            <label for="firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="firstname" name="firstname"
                                   minlength="2"
                                   maxlength="100"
                                   title="First name must contain only letters, spaces, dots, commas, and hyphens"
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="lastname" name="lastname"
                                   minlength="2"
                                   maxlength="100"
                                   title="Last name must contain only letters, spaces, dots, commas, and hyphens"
                                   required>
                        </div>
                        <div class="mb-3">
                            <label for="middlename" class="form-label">Middle Name <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control" id="middlename" name="middlename"
                                   maxlength="100"
                                   title="Middle name must contain only letters, spaces, dots, commas, and hyphens">
                        </div>
                        <div class="mb-3" id="badgeNumberSection">
                            <label for="badge_number" class="form-label">Badge Number <span id="badgeRequiredMark" class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="badge_number" name="badge_number"
                                   inputmode="numeric"
                                   pattern="[0-9]{6,}"
                                   minlength="6"
                                   maxlength="20"
                                   title="Badge number must be at least 6 digits"
                                   placeholder="e.g. 123456">
                            
                        </div>
                        <div class="mb-3" id="emailSection">
                            <label for="email" class="form-label">Email <span id="emailRequiredMark" class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   maxlength="100"
                                   title="Please enter a valid email address">
                            
                        </div>
                        <div class="mb-3" id="personnelRankSection">
                            <label for="personnel_rank" class="form-label">Rank</label>
                            <select class="form-select" id="personnel_rank" name="personnel_rank">
                                <option value="">— None —</option>
                            </select>
                            
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span id="passwordOptionalLabel" style="display:none;">(Leave blank to keep current)</span></label>
                            <input type="text" class="form-control" id="password" name="password" 
                                   minlength="8"
                                   maxlength="100"
                                   title="Password must be at least 8 characters and include upper, lower, number, and special character"
                                   required>
                           
                            <small class="form-text text-muted" id="passwordHint">8+ characters with upper, lower, number, and special character</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="role_id" class="form-label">Role</label>
                                <select class="form-select" id="role_id" name="role_id" required>
                                    <option value="">Select a role</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="userStatusSection" style="display: none;">
                                <label for="user_status" class="form-label">Status</label>
                                <select class="form-select" id="user_status" name="status">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        <div id="addUserEnrollmentSection" class="border rounded p-3 mb-2" data-validate-group="1" style="display: none;">
                            <label class="form-label fw-semibold">Section Assignment <span class="text-danger">*</span></label>
                            <p class="text-muted small mb-2">Trainees must be assigned to a course, batch, and section when created.</p>
                            <div class="mb-2">
                                <select class="form-select" id="addUserCourseSelect">
                                    <option value="">Select a course</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <select class="form-select" id="addUserBatchSelect" disabled>
                                    <option value="">Select a batch</option>
                                </select>
                            </div>
                            <div>
                                <select class="form-select" id="addUserSectionSelect" disabled>
                                    <option value="">Select a section</option>
                                </select>
                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/password-policy.js?v=3"></script>
    <script src="../../assets/js/modal-form-validation.js?v=1"></script>
    <script src="../../js/masterfiles/users.js?v=30"></script>
</body>
</html>
