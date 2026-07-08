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
    <title>Manage Exams - CCMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/ccmd.css">
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>CCMD - Exam Management</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examination</a></li>
                <li><a href="exams.php" class="active"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="#"><i class="bi bi-people-fill"></i>Enrolled Trainees</a></li>
                <li><a href="monitor-examination.php#incidents"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href=""><i class="bi bi-clock-history"></i>Audit Logs</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h2>Manage Exam Questions</h2>
                <p class="text-muted">Edit questions assigned to exams</p>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card">
                <div class="section-header">
                    <i class="bi bi-pencil-square" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                    <div>
                        <h5>All Exams</h5>
                        <p>Select an exam to manage its questions</p>
                    </div>
                </div>
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-4 align-items-end">
                        <div>
                            <label class="form-label small mb-1" for="filterCourse">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All courses</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="filterSubject">Subject</label>
                            <select id="filterSubject" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All subjects</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="filterExamStatus">Status</label>
                            <select id="filterExamStatus" class="form-select form-select-sm" style="min-width: 150px;">
                                <option value="">All statuses</option>
                                <option value="Draft">Draft</option>
                                <option value="Published">Published</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>

                    <div id="examsContainer" class="row g-3">
                        <div class="col-12 text-center py-4">Loading...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Management Modal -->
    <div class="modal fade" id="questionModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionModalLabel">Manage Exam Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="currentExamId">
                    <div class="mb-3">
                        <h6 id="examTitle"></h6>
                        <p class="text-muted small" id="examDetails"></p>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="badge bg-info" id="questionCount">0 questions</span>
                        </div>
                        <button class="btn btn-sm btn-primary" id="addQuestionsBtn">
                            <i class="bi bi-plus-lg"></i> Add Questions
                        </button>
                    </div>

                    <div id="questionsList">
                        <!-- Questions will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Question Selection Modal -->
    <div class="modal fade" id="questionSelectionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Questions to Add</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label small">Filter by Subject</label>
                                <select id="questionSubjectFilter" class="form-select form-select-sm">
                                    <option value="">All Subjects</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Search</label>
                                <input type="text" id="questionSearchInput" class="form-control form-control-sm" placeholder="Search questions...">
                            </div>
                        </div>
                    </div>

                    <div style="max-height: 400px; overflow-y: auto;" id="questionListContainer">
                        <!-- Question checkboxes will be loaded here -->
                    </div>

                    <div class="mt-3">
                        <span class="badge bg-primary" id="selectedQuestionCount">0 selected</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveQuestionSelectionBtn">Add Selected Questions</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/ccmd/exams.js"></script>
    <script>
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
    </script>
</body>
</html>
