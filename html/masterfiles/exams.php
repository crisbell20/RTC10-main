<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    header('Location: ../../html/auth/login.html');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exams - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <style>
        .course-card { 
            border: 1px solid #dee2e6; 
            border-radius: 8px; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            background: white;
        }
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
            <p>Exam Management</p>
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
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php" class="active"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2>Manage Exams</h2>
                    <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#examModal">
                        <i class="bi bi-plus-lg me-2"></i>Add New Exam
                    </button>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-pencil-square" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>All Exams</h5>
                        <p>Exams grouped by course and subject</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <div>
                            <label class="form-label small mb-1">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 180px;">
                                <option value="">All Courses</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Subject</label>
                            <select id="filterSubject" class="form-select form-select-sm" style="min-width: 180px;">
                                <option value="">All Subjects</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Status</label>
                            <select id="filterExamStatus" class="form-select form-select-sm" style="min-width: 150px;">
                                <option value="">All Status</option>
                                <option value="Draft">Draft</option>
                                <option value="Published">Published</option>
                                <option value="Closed">Closed</option>
                                <option value="Archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div id="examsContainer">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-hourglass-split"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Exam Modal -->
    <div class="modal fade" id="examModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="examModalLabel">Add New Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="examForm">
                    <input type="hidden" id="examId" name="exam_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Course *</label>
                            <select id="examCourse" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject *</label>
                            <select id="examSubject" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" id="examTitle" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="examDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Schedule Date/Time</label>
                            <input type="datetime-local" class="form-control" id="examSchedule">
                            <small class="text-muted">When the exam becomes available</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="examDeadline" required>
                            <small class="text-muted">Students must START the exam before this time</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="examDuration" min="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Passing Score (%)</label>
                            <input type="number" class="form-control" id="examPassing" min="0" max="100" step="0.01">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select id="examStatus" class="form-select">
                                <option value="Draft">Draft</option>
                                <option value="Published">Published</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="examRandomized">
                            <label class="form-check-label" for="examRandomized">Randomize questions</label>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Exam Questions</label>
                            <div class="d-flex justify-content-between align-items-center">
                                <span id="questionCount" class="badge bg-info">0 questions assigned</span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="manageQuestionsBtn">
                                    <i class="bi bi-list-check"></i> Manage Questions
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assign to Batches *</label>
                            <div id="batchSelectionContainer" class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                <div class="text-center text-muted py-2">
                                    <i class="bi bi-hourglass-split"></i> Loading batches...
                                </div>
                            </div>
                            <small class="text-muted">
                                <span id="batchCount">0</span> batch(es) selected
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="examSubmitBtn" class="btn btn-add-user">Add Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Question Selection Modal -->
    <div class="modal fade" id="questionSelectionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Filter by Course</label>
                            <select id="questionCourseFilter" class="form-select">
                                <option value="">All Courses</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Filter by Subject</label>
                            <select id="questionSubjectFilter" class="form-select">
                                <option value="">All Subjects</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Search Questions</label>
                        <input type="text" id="questionSearchInput" class="form-control" placeholder="Search by question, subject, or course...">
                    </div>
                    <div class="mb-2">
                        <small class="text-muted" id="questionPickerSummary"></small>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted">Available Questions</span>
                            <span class="badge bg-primary" id="selectedQuestionCount">0 selected</span>
                        </div>
                        <div id="questionListContainer" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 10px;">
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-hourglass-split"></i> Loading questions...
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveQuestionSelectionBtn" class="btn btn-add-user">Save Selection</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/exams.js?v=8"></script>
</body>
</html>

