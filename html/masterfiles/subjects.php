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
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Manage Subjects - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/courses.css?v=21">
</head>
<body>
    <div class="top-nav">
        <?php
        $navBrandSubtitle = 'Subject Management';
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
                <li><a href="subjects.php" class="active"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h2>Manage Subjects</h2>
                    </div>
                    <button type="button" class="btn btn-add-user" id="addModuleBtn">
                        <i class="bi bi-folder-plus me-2"></i><span class="staff-label-long">Add Module</span><span class="staff-label-short">Module</span>
                    </button>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div id="migrationWarning" class="alert alert-warning d-none" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Module schema is not installed. Import <code>scripts/migrations/add_course_modules.sql</code> on the database.
            </div>

            <div class="section-card">
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3 align-items-end curriculum-toolbar">
                        <div>
                            <label class="form-label small mb-1" for="filterCourse">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 180px;">
                                <option value="">All courses</option>
                            </select>
                        </div>
                        <div id="filterModuleWrap">
                            <label class="form-label small mb-1" for="filterModule">Module</label>
                            <select id="filterModule" class="form-select form-select-sm" style="min-width: 180px;" disabled>
                                <option value="">Select a course first</option>
                            </select>
                        </div>
                        <div class="ms-auto curriculum-search-wrap">
                            <label class="form-label small mb-1" for="filterSearch">Search</label>
                            <input id="filterSearch" type="search" class="form-control form-control-sm" placeholder="Search modules or subjects" autocomplete="off">
                        </div>
                    </div>
                    <div id="subjectsContainer" class="course-card-grid">
                        <div class="curriculum-empty text-muted">
                            <i class="bi bi-hourglass-split"></i>
                            <div>Loading...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                        <div id="moduleFormAlert"></div>
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
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="moduleSubmitBtn" class="btn btn-add-user">Add Module</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="subjectModalLabel">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="subjectForm">
                    <input type="hidden" id="subjectId" name="subject_id">
                    <input type="hidden" id="subjectCourse" name="course_id">
                    <input type="hidden" id="subjectModule" name="module_id">
                    <div class="modal-body">
                        <div id="subjectFormAlert"></div>
                        <div id="subjectEntryList">
                            <div class="subject-entry">
                                <div class="mb-3">
                                    <label class="form-label">Subject Name *</label>
                                    <input type="text" class="form-control" id="subjectName" required placeholder="e.g., Mathematics">
                                </div>
                            </div>
                        </div>
                        <div id="addAnotherSubjectWrap">
                            <button type="button" class="btn btn-outline btn-sm" id="addAnotherSubjectBtn" title="Add another subject" aria-label="Add another subject">
                                <i class="bi bi-plus-lg"></i>
                            </button>
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
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/staff-ui.js"></script>
    <script src="../../assets/js/modal-form-validation.js?v=1"></script>
    <script src="../../js/masterfiles/subjects.js?v=29"></script>
</body>
</html>
