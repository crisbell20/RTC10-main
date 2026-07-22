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
    <title>Manage Courses - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .course-card { border: 1px solid #dee2e6; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .section-item { margin-left: 1.5rem; margin-top: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; }
        .batch-item { margin-left: 3rem; margin-top: 0.25rem; padding: 0.25rem 0.5rem; background: #e9ecef; border-radius: 4px; font-size: 0.9rem; }
        .section-users-panel { border-color: #ced4da !important; }
        .expand-btn { cursor: pointer; }
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
            <p>Course Management</p>
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
                <li><a href="courses.php" class="active"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
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
                    <h2>Manage Courses</h2>
                    <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                        <i class="bi bi-plus-lg me-2"></i>Add New Course
                    </button>
                </div>
            </div>

            <div id="messageAlert"></div>

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
                    <div id="coursesContainer">
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-hourglass-split"></i> Loading...
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Course Modal -->
    <div class="modal fade" id="addCourseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="courseModalTitle">Add New Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addCourseForm">
                    <input type="hidden" name="course_id" id="editCourseId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course Name *</label>
                            <input type="text" class="form-control" name="course_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Details</label>
                            <textarea class="form-control" name="details" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration</label>
                            <input type="text" class="form-control" name="duration" placeholder="e.g., 6 months">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user" id="courseSubmitBtn">Add Course</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Batch Modal -->
    <div class="modal fade" id="addBatchModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="batchModalTitle">Add Batch to Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addBatchForm">
                    <input type="hidden" name="course_id" id="batchCourseId">
                    <input type="hidden" name="batch_id" id="editBatchId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Batch Name *</label>
                            <input type="text" class="form-control" name="batch_name" required placeholder="e.g., PSJLC 2025-01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Started</label>
                            <input type="date" class="form-control" name="date_started">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date Ended</label>
                            <input type="date" class="form-control" name="date_ended">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user" id="batchSubmitBtn">Add Batch</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sectionModalTitle">Add Section to Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="addSectionForm">
                    <input type="hidden" name="batch_id" id="sectionBatchId">
                    <input type="hidden" name="section_id" id="editSectionId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Name *</label>
                            <input type="text" class="form-control" name="section_name" required placeholder="e.g., Alpha, Bravo">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Capacity</label>
                            <input type="number" class="form-control" name="capacity" value="40" min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-add-user" id="sectionSubmitBtn">Add Section</button>
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
    <script src="../../js/masterfiles/courses.js?v=7"></script>
</body>
</html>
