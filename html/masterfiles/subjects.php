<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
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
    <title>Manage Subjects - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .course-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .section-item { margin-left: 1.5rem; margin-top: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; }
        .batch-item { margin-left: 3rem; margin-top: 0.25rem; padding: 0.25rem 0.5rem; background: #e9ecef; border-radius: 4px; font-size: 0.9rem; }
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
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Subject Management</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php" class="active"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="../admin/cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Manage Subjects</h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline" type="button" onclick="openModuleModalFromHeader()">
                            <i class="bi bi-folder-plus me-2"></i>Add Module
                        </button>
                        <button class="btn btn-add-user" type="button" onclick="openSubjectModalFromHeader()">
                            <i class="bi bi-plus-lg me-2"></i>Add New Subject
                        </button>
                    </div>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div id="migrationWarning" class="alert alert-warning d-none" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Module schema is not installed. Run <code>scripts/migrations/run_course_modules_migration.php</code>.
            </div>

            <div class="section-card">
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-end">
                        <div>
                            <label class="form-label small mb-1" for="filterCourse">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 220px;">
                                <option value="">All courses</option>
                            </select>
                        </div>
                    </div>
                    <div id="subjectsContainer">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-hourglass-split"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Module Modal -->
    <div class="modal fade" id="addModuleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="moduleModalLabel">Add Module</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="moduleForm">
                    <input type="hidden" id="moduleId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course *</label>
                            <select id="moduleCourse" class="form-select" required>
                                <option value="">Select course...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Module Name *</label>
                            <input type="text" class="form-control" id="moduleName" required placeholder="e.g., Module 1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="moduleDescription" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="moduleSubmitBtn" class="btn btn-add-user">Add Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add/Edit Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectModalLabel">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="subjectForm">
                    <input type="hidden" id="subjectId" name="subject_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course *</label>
                            <select id="subjectCourse" class="form-select" required>
                                <option value="">Select course...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Module *</label>
                            <select id="subjectModule" class="form-select" required disabled>
                                <option value="">Select module...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject Name *</label>
                            <input type="text" class="form-control" id="subjectName" required placeholder="e.g., Mathematics">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="subjectDescription" rows="3" placeholder="Brief description of the subject"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="subjectSubmitBtn" class="btn btn-add-user">Add Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/subjects.js?v=9"></script>
</body>
</html>
