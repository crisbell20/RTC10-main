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
    <link rel="stylesheet" href="../../assets/css/exam-question-editor.css">
    <style>
        .course-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
    </style>
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
                <li><a href="monitor-examination.php"><i class="bi bi-people-fill"></i>Enrolled Trainees</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i>Reports & Analytics</a></li>
                <li><a href=""><i class="bi bi-clock-history"></i>Audit Logs</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="page-header">
                <div>
                    <h2 id="examPageTitle">Manage Exams</h2>
                    <p id="examPageSubtitle" class="text-muted mb-0">Review exams created by administrators. You can edit questions, view participants, and view results.</p>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card exams-list-card">
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <div>
                            <label class="form-label small mb-1">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 180px;">
                                <option value="">All Courses</option>
                            </select>
                        </div>
                        <div id="filterModuleWrap">
                            <label class="form-label small mb-1">Module</label>
                            <select id="filterModule" class="form-select form-select-sm" style="min-width: 180px;">
                                <option value="">All Modules</option>
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

    <!-- Question Selection Modal -->
    <div class="modal fade" id="questionSelectionModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Exam Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border mb-3 py-2" id="questionPickerContext">
                        <div class="small text-muted mb-1">Questions for</div>
                        <div class="fw-semibold" id="questionPickerContextLabel">—</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <h6 class="mb-0"><i class="bi bi-list-check me-1"></i>Exam questions</h6>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-primary" id="addNewQuestionBtn">
                                        <i class="bi bi-plus-lg me-1"></i>New question
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="examImportQuestionsBtn">
                                        <i class="bi bi-upload me-1"></i>Import
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllQuestionsBtn">Clear all</button>
                                    <span class="badge bg-primary" id="selectedQuestionCount">0 on exam</span>
                                </div>
                            </div>
                            <p class="small text-muted mb-2">Create new questions or add from the bank. Drag cards to reorder.</p>
                            <div id="examQuestionCanvas" class="exam-question-canvas">
                                <div class="exam-question-canvas-empty">
                                    <i class="bi bi-ui-checks fs-2 d-block mb-2"></i>
                                    Add questions from the bank on the right
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <h6 class="mb-2"><i class="bi bi-collection me-1"></i>Question bank</h6>
                            <div class="mb-2">
                                <input type="text" id="questionSearchInput" class="form-control form-control-sm" placeholder="Search questions in this subject...">
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                <small class="text-muted" id="questionPickerSummary"></small>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllQuestionsBtn">Add all shown</button>
                            </div>
                            <div id="questionBankListContainer" class="question-bank-panel">
                                <div class="text-center text-muted py-4">
                                    <i class="bi bi-hourglass-split"></i> Loading questions...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveQuestionSelectionBtn" class="btn btn-add-user">Save Questions</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="examImportQuestionsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="examImportSubjectNote">Questions are added to this exam as drafts until the exam is published.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline" id="downloadExamImportTemplateBtn">
                            <i class="bi bi-download me-2"></i>Download Template
                        </button>
                    </div>
                    <div class="alert alert-secondary small mb-3">
                        Use the <strong>Questions</strong> sheet with columns:
                        <code>Question</code>, <code>Option_A</code>–<code>Option_D</code>, <code>Option_E</code>, <code>Correct_Answer</code>.
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="examImportFile">Select File</label>
                        <input type="file" class="form-control" id="examImportFile" accept=".xlsx,.xls,.csv">
                        <small class="text-muted">Supported formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                    </div>
                    <div id="examImportValidation" class="mt-3" style="display: none;"></div>
                    <div id="examImportPreview" class="mt-3" style="display: none;">
                        <h6 class="mb-2">Preview</h6>
                        <div id="examImportPreviewCards" class="d-none"></div>
                        <div class="table-responsive" id="examImportPreviewTableWrap">
                            <table class="table table-sm table-bordered" id="examImportPreviewTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-add-user" id="examImportBtn" disabled>
                        <i class="bi bi-upload me-2"></i>Import Questions
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Participants Modal -->
    <div class="modal fade" id="participantsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="participantsModalTitle">View Participants</h5>
                        <small class="text-muted" id="participantsModalSubtitle">Participants selected for this exam</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="participantsLoading" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="text-muted mt-2 mb-0">Loading participants...</p>
                    </div>
                    <div id="participantsContent" style="display:none;">
                        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
                            <input type="search" id="participantSearchInput" class="form-control form-control-sm" style="max-width:260px;" placeholder="Search name or ID...">
                            <select id="participantSectionFilter" class="form-select form-select-sm" style="max-width:240px; display:none;" aria-label="Filter by section">
                                <option value="">All sections</option>
                            </select>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="participantSelectAllBtn">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="participantClearAllBtn">Clear all</button>
                            <span class="badge bg-primary ms-auto" id="participantSelectionCount">0 selected</span>
                        </div>
                        <div id="participantsList"></div>
                        <div id="participantsEmpty" class="text-center text-muted py-4" style="display:none;">
                            No participants are selected for this exam.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-add-user d-none" id="saveParticipantsBtn" disabled>Save Participants</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.EXAM_UI_CONFIG = {
            role: 'CCMD',
            canCreateExam: false,
            canEditExamDetails: false,
            canEditQuestions: true,
            canManageParticipants: false,
            canViewParticipants: true,
            canViewResults: true,
            responsesPath: '../masterfiles/exam-responses.php',
            pageTitle: 'Manage Exams',
            pageSubtitle: 'Review exams created by administrators. You can edit questions, view participants, and view results.',
            participantsModalTitle: 'View Participants',
            participantsModalSubtitle: 'Participants selected for this exam'
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script src="../../js/masterfiles/drag-autoscroll.js?v=1"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="../../js/masterfiles/question-import.js?v=2"></script>
    <script src="../../js/masterfiles/exams.js?v=48"></script>
</body>
</html>
