<?php
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'CCMD'])) {
    header('Location: ../../login.php');
    exit;
}

if (($_SESSION['user_role'] ?? '') === 'CCMD') {
    header('Location: ../ccmd/dashboard.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? '';
$isCcmd = $userRole === 'CCMD';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title>Question Bank - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/staff-responsive.css?v=3">
    <link rel="stylesheet" href="../../assets/css/exam-question-editor.css">
    <link rel="stylesheet" href="../../assets/css/question-bank.css?v=7">
    <link rel="stylesheet" href="../../assets/css/question-display.css?v=2">
<?php if ($isCcmd): ?>
    <link rel="stylesheet" href="../../assets/css/ccmd.css">
<?php endif; ?>
    <style>
        .difficulty-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="question-bank-page">
    <div class="top-nav">
        <?php
        $navBrandSubtitle = $isCcmd ? 'CCMD - Question Bank' : 'Question Bank Management';
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
<?php if ($isCcmd): ?>
                <li><a href="../ccmd/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../ccmd/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="../ccmd/monitor-examination.php"><i class="bi bi-activity"></i>Monitor Examinations</a></li>
                <li><a href="../ccmd/exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php" class="active"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="../ccmd/enrolled-trainees.php"><i class="bi bi-people-fill"></i>View Trainees</a></li>
                <li><a href="../ccmd/reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
<?php else: ?>
                <li><a href="../admin/profile.php"><i class="bi bi-person-circle"></i>Profile</a></li>
                <li><a href="../../html/admin/dashboard.php"><i class="bi bi-grid-1x2-fill"></i>Dashboard</a></li>
                <li class="menu-label"><i class="bi bi-folder-fill"></i>Masterfiles</li>
                <li><a href="users.php"><i class="bi bi-people-fill"></i>Manage Users</a></li>
                <li><a href="courses.php"><i class="bi bi-book-fill"></i>Manage Courses</a></li>
                <li><a href="subjects.php"><i class="bi bi-journal-text"></i>Manage Subjects</a></li>
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php" class="active"><i class="bi bi-folder"></i>Question Bank</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href="cheating-incidents.php"><i class="bi bi-exclamation-triangle"></i>Cheating Incidents</a></li>
                <li><a href="archive.php"><i class="bi bi-archive"></i>Archive</a></li>
                <li><a href="audit-logs.php"><i class="bi bi-clock-history"></i>Audit Logs</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i>System Settings</a></li>
<?php endif; ?>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center qb-header-row">
                    <h2>Question Bank</h2>
                    <div class="qb-header-actions">
                        <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#importQuestionsModal" aria-label="Import questions">
                            <i class="bi bi-upload"></i><span class="btn-label-short">Import</span><span class="btn-label-long ms-2">Import</span>
                        </button>
                        <button type="button" class="btn btn-add-user btn-sm" data-bs-toggle="modal" data-bs-target="#addQuestionsModal" aria-label="Add questions">
                            <i class="bi bi-plus-lg"></i><span class="btn-label-short">Add</span><span class="btn-label-long ms-2">Add Questions</span>
                        </button>
                    </div>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card">
                <div class="section-body">
                    <div class="qb-filters">
                        <div class="collapse qb-filters-curriculum" id="qbFiltersCurriculum">
                            <div class="qb-filters-curriculum-inner">
                                <div>
                                    <label class="form-label small mb-1" for="filterCourse">Course</label>
                                    <select id="filterCourse" class="form-select form-select-sm">
                                        <option value="">All courses</option>
                                    </select>
                                </div>
                                <div id="filterModuleWrap">
                                    <label class="form-label small mb-1" for="filterModule">Module</label>
                                    <select id="filterModule" class="form-select form-select-sm">
                                        <option value="">All modules</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small mb-1" for="filterSubject">Subject</label>
                                    <select id="filterSubject" class="form-select form-select-sm">
                                        <option value="">All subjects</option>
                                    </select>
                                </div>
                                <div class="qb-filters-clear">
                                    <button type="button" class="btn btn-sm btn-outline-secondary w-md-auto w-100" id="clearFiltersBtn" style="display: none;">
                                        <i class="bi bi-x-circle"></i> Clear filters
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="qb-filters-search">
                            <label class="form-label small mb-1" for="searchQuestion">Search</label>
                            <input id="searchQuestion" type="text" class="form-control form-control-sm" placeholder="Search questions...">
                        </div>
                        <button type="button"
                            class="btn btn-sm btn-outline-secondary qb-filters-toggle d-md-none"
                            id="qbFiltersToggle"
                            data-bs-toggle="collapse"
                            data-bs-target="#qbFiltersCurriculum"
                            aria-expanded="false"
                            aria-controls="qbFiltersCurriculum">
                            <i class="bi bi-funnel"></i> Filters<span class="qb-filter-count d-none" id="qbFilterCount"></span>
                        </button>
                    </div>
                    <p class="text-muted small mb-3" id="questionsFilterSummary">Loading questions...</p>
                    <div id="questionsContainer">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-hourglass-split"></i> Loading...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Questions Modal -->
    <div class="modal fade" id="importQuestionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="importCourse">Course *</label>
                            <select id="importCourse" class="form-select" required>
                                <option value="">Select course...</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="importModuleWrap">
                            <label class="form-label" for="importModule">Module *</label>
                            <select id="importModule" class="form-select" required disabled>
                                <option value="">Select module...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="importSubject">Subject *</label>
                            <select id="importSubject" class="form-select" required disabled>
                                <option value="">Select subject...</option>
                            </select>
                            <small class="text-muted">All imported questions will be assigned to this subject</small>
                        </div>
                    </div>

                    <div class="alert alert-secondary small mb-3">
                        The <strong>Questions</strong> sheet includes Course, Module, and Subject at the top, then columns:
                        <code>Question</code>, <code>Option_A</code>–<code>Option_D</code>, <code>Option_E</code>, <code>Correct_Answer</code>.
                    </div>
                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label" for="importFile">Select File</label>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="flex-grow-1" style="min-width: 220px;">
                                    <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                                </div>
                                <button type="button" class="btn btn-outline text-nowrap" id="downloadImportTemplateBtn">
                                    <i class="bi bi-download me-1"></i>Download Template
                                </button>
                            </div>
                            <small class="text-muted">Supported formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                        </div>
                    </form>

                    <div id="importValidation" class="mt-3" style="display: none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-add-user" id="importBtn" disabled>
                        <i class="bi bi-upload me-2"></i>Import Questions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Questions Modal (canvas builder) -->
    <div class="modal fade" id="addQuestionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Questions to Bank</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label" for="builderCourse">Course *</label>
                            <select id="builderCourse" class="form-select" required>
                                <option value="">Select course...</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="builderModuleWrap">
                            <label class="form-label" for="builderModule">Module *</label>
                            <select id="builderModule" class="form-select" required disabled>
                                <option value="">Select module...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="builderSubject">Subject *</label>
                            <select id="builderSubject" class="form-select" required disabled>
                                <option value="">Select subject...</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-light border mb-3 py-2">
                        <div class="small text-muted mb-1">Adding questions for</div>
                        <div class="fw-semibold" id="builderContextLabel">Select course, module, and subject</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Questions</h6>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-primary" id="builderAddQuestionBtn">
                                <i class="bi bi-plus-lg me-1"></i>New question
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="builderClearBtn">Clear all</button>
                            <span class="badge bg-primary" id="builderQuestionCount">0 questions</span>
                        </div>
                    </div>
                    <p class="small text-muted mb-2">Create multiple questions at once. Drag cards to reorder before saving.</p>
                    <div id="builderQuestionCanvas" class="exam-question-canvas">
                        <div class="exam-question-canvas-empty">
                            <i class="bi bi-ui-checks fs-2 d-block mb-2"></i>
                            Select course, module, and subject, then click <strong>New question</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-add-user" id="saveBuilderQuestionsBtn">Save Questions</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div class="modal fade" id="editQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editQuestionForm">
                    <input type="hidden" id="editQuestionId" name="question_id">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Course *</label>
                                <select id="editQuestionCourse" class="form-select" required>
                                    <option value="">Select course...</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="editQuestionModuleWrap">
                                <label class="form-label">Module *</label>
                                <select id="editQuestionModule" class="form-select" required disabled>
                                    <option value="">Select module...</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Subject *</label>
                                <select id="editQuestionSubject" class="form-select" required disabled>
                                    <option value="">Select subject...</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Question Text *</label>
                            <textarea class="form-control" id="editQuestionText" rows="3" required></textarea>
                        </div>
                        <div id="editAnswersSection">
                            <label class="form-label">Answer Options (Multiple Choice) *</label>
                            <div id="editAnswersList"></div>
                            <small class="text-muted">Select the radio button for the correct answer</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" id="editQuestionSubmitBtn" class="btn btn-add-user">Update Question</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../../js/masterfiles/drag-autoscroll.js?v=1"></script>
    <script src="../../js/masterfiles/question-builder.js?v=3"></script>
    <script src="../../assets/js/logout-handler.js?v=2"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../js/masterfiles/question-bank.js?v=5"></script>
</body>
</html>
