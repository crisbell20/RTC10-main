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
    <title>Question Bank - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/exam-question-editor.css">
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
        .question-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        .question-text {
            font-weight: 500;
            margin-bottom: 0.75rem;
        }
        .answer-option {
            padding: 0.5rem;
            margin: 0.25rem 0;
            border-radius: 4px;
            background: #f9fafb;
        }
        .answer-option.correct {
            background: #d1fae5;
            border-left: 3px solid #10b981;
        }
        .difficulty-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Question Bank Management</p>
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
                <li><a href="exams.php"><i class="bi bi-pencil-square"></i>Manage Exams</a></li>
                <li><a href="question-bank.php" class="active"><i class="bi bi-folder"></i>Question Bank</a></li>
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
                    <h2>Question Bank</h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#importQuestionsModal">
                            <i class="bi bi-upload me-2"></i>Import Questions
                        </button>
                        <button class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addQuestionsModal">
                            <i class="bi bi-plus-lg me-2"></i>Add Questions
                        </button>
                    </div>
                </div>
            </div>

            <div id="messageAlert"></div>

            <div class="section-card">
                <div class="section-body">
                    <div class="d-flex flex-wrap gap-2 mb-2 align-items-end">
                        <div>
                            <label class="form-label small mb-1" for="filterCourse">Course</label>
                            <select id="filterCourse" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All courses</option>
                            </select>
                        </div>
                        <div id="filterModuleWrap">
                            <label class="form-label small mb-1" for="filterModule">Module</label>
                            <select id="filterModule" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All modules</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="filterSubject">Subject</label>
                            <select id="filterSubject" class="form-select form-select-sm" style="min-width: 200px;">
                                <option value="">All subjects</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1" for="searchQuestion">Search</label>
                            <input id="searchQuestion" type="text" class="form-control form-control-sm" style="min-width: 220px;" placeholder="Search question, subject, course...">
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearFiltersBtn" style="display: none;">
                                <i class="bi bi-x-circle"></i> Clear filters
                            </button>
                        </div>
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

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline" id="downloadImportTemplateBtn">
                            <i class="bi bi-download me-2"></i>Download Template
                        </button>
                    </div>
                    <div class="alert alert-secondary small mb-3">
                        Use the <strong>Questions</strong> sheet with columns:
                        <code>Question</code>, <code>Option_A</code>–<code>Option_D</code>, <code>Option_E</code>, <code>Correct_Answer</code>.
                    </div>
                    <form id="importForm" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label" for="importFile">Select File</label>
                            <input type="file" class="form-control" id="importFile" accept=".xlsx,.xls,.csv">
                            <small class="text-muted">Supported formats: Excel (.xlsx, .xls) or CSV (.csv)</small>
                        </div>
                    </form>

                    <div id="importValidation" class="mt-3" style="display: none;"></div>

                    <div id="importPreview" class="mt-3" style="display: none;">
                        <h6 class="mb-2">Preview</h6>
                        <div id="importPreviewCards" class="d-none"></div>
                        <div class="table-responsive" id="importPreviewTableWrap">
                            <table class="table table-sm table-bordered" id="previewTable">
                                <thead></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
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
                        <div class="mb-3 mt-3">
                            <label class="form-label">Points</label>
                            <input type="number" class="form-control" id="editQuestionPoints" min="1" value="1">
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
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script>
        const CHOICE_COUNT = 5;
        const MODULES_API = '../../api/masterfiles/modules.php';
        let allCourses = [];
        let allSubjects = [];
        let modulesEnabled = false;
        let modulesByCourse = {};
        let subjectsByModule = {};
        let currentFilterModule = '';

        function renderAnswerOptions(existingAnswers = [], targetId = 'editAnswersList', radioName = 'editCorrectAnswer') {
            const answersList = document.getElementById(targetId);
            if (!answersList) return;
            answersList.innerHTML = '';

            for (let i = 0; i < CHOICE_COUNT; i++) {
                const label = String.fromCharCode(65 + i);
                const existing = existingAnswers[i] || null;
                const answerDiv = document.createElement('div');
                answerDiv.className = 'input-group mb-2';
                const isCorrect = existing && String(existing.Is_Correct) === '1';
                const noCorrectMarked = !existingAnswers.some(a => String(a.Is_Correct) === '1');
                answerDiv.innerHTML = `
                    <span class="input-group-text">
                        <input type="radio" name="${radioName}" value="${i}" ${isCorrect || (noCorrectMarked && i === 0) ? 'checked' : ''} required>
                    </span>
                    <span class="input-group-text fw-bold">${label}</span>
                    <input type="text" class="form-control" placeholder="Option ${label}" data-answer-index="${i}" value="${existing ? escapeHtml(existing.Choice_Text) : ''}" required>
                `;
                answersList.appendChild(answerDiv);
            }
        }

        function updateBuilderContextLabel() {
            const label = document.getElementById('builderContextLabel');
            if (!label) return;

            const courseId = document.getElementById('builderCourse')?.value || '';
            const moduleId = document.getElementById('builderModule')?.value || '';
            const subjectId = document.getElementById('builderSubject')?.value || '';
            const course = allCourses.find(c => String(c.Course_ID) === String(courseId));
            const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
            const module = (modulesByCourse[courseId] || []).find(m => String(m.Module_ID) === String(moduleId));

            if (!courseId || !subjectId || (modulesEnabled && !moduleId)) {
                label.textContent = 'Select course, module, and subject';
                return;
            }

            const subjectLabel = subject
                ? `${subject.Subject_Name}${subject.Subject_Code ? ' (' + subject.Subject_Code + ')' : ''}`
                : 'Selected subject';
            const modulePart = module ? `${module.Module_Name} → ` : '';
            label.textContent = `${course?.Course_Name || 'Course'} → ${modulePart}${subjectLabel}`;
        }

        function getBuilderSubjectId() {
            return document.getElementById('builderSubject')?.value || '';
        }

        function getCourseName(courseId) {
            const course = allCourses.find(c => String(c.Course_ID) === String(courseId));
            return course ? course.Course_Name : '';
        }

        function setModuleFieldsVisible(visible) {
            ['filterModuleWrap', 'importModuleWrap', 'builderModuleWrap', 'editQuestionModuleWrap'].forEach(id => {
                document.getElementById(id)?.classList.toggle('d-none', !visible);
            });
            ['importModule', 'builderModule', 'editQuestionModule'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.required = visible;
            });
        }

        function buildCurriculumMaps(tree) {
            modulesByCourse = {};
            subjectsByModule = {};
            allSubjects = [];

            (tree || []).forEach(course => {
                modulesByCourse[course.Course_ID] = course.modules || [];
                (course.modules || []).forEach(mod => {
                    const subjects = (mod.subjects || []).map(s => ({
                        ...s,
                        Module_ID: mod.Module_ID,
                        Module_Name: mod.Module_Name,
                        Course_ID: course.Course_ID,
                    }));
                    subjectsByModule[mod.Module_ID] = subjects;
                    allSubjects.push(...subjects);
                });
            });
        }

        function populateModuleOptions(selectEl, courseId, placeholder, selectedId) {
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder || 'All modules'}</option>`;
            if (!courseId) {
                selectEl.disabled = true;
                return;
            }
            const modules = modulesByCourse[courseId] || [];
            modules.forEach(mod => {
                const option = new Option(mod.Module_Name, mod.Module_ID);
                if (selectedId && String(selectedId) === String(mod.Module_ID)) {
                    option.selected = true;
                }
                selectEl.add(option);
            });
            selectEl.disabled = modules.length === 0;
        }

        function populateSubjectOptions(selectEl, options = {}) {
            const {
                courseId = '',
                moduleId = '',
                placeholder = 'All subjects',
                selectedId = '',
            } = options;
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;

            let list = [];
            if (modulesEnabled && moduleId) {
                list = subjectsByModule[moduleId] || [];
            } else if (modulesEnabled && courseId) {
                list = allSubjects.filter(s => String(s.Course_ID) === String(courseId));
            } else if (courseId) {
                list = allSubjects.filter(s => String(s.Course_ID) === String(courseId));
            } else {
                list = allSubjects;
            }

            list.forEach(subject => {
                const option = new Option(
                    `${subject.Subject_Name}${subject.Subject_Code ? ' (' + subject.Subject_Code + ')' : ''}`,
                    subject.Subject_ID
                );
                selectEl.add(option);
            });

            if (selectedId) {
                selectEl.value = selectedId;
            }

            if (selectEl.id === 'importSubject' || selectEl.id === 'builderSubject' || selectEl.id === 'editQuestionSubject') {
                selectEl.disabled = modulesEnabled ? !moduleId : !courseId;
            }
        }

        function findModuleIdForSubject(subjectId, courseId) {
            for (const mod of modulesByCourse[courseId] || []) {
                if ((subjectsByModule[mod.Module_ID] || []).some(s => String(s.Subject_ID) === String(subjectId))) {
                    return mod.Module_ID;
                }
            }
            return '';
        }

        function hasActiveFilters() {
            return !!(document.getElementById('filterCourse').value
                || document.getElementById('filterModule')?.value
                || document.getElementById('filterSubject').value
                || document.getElementById('searchQuestion').value.trim());
        }

        function updateFilterSummary(count) {
            const summary = document.getElementById('questionsFilterSummary');
            const clearBtn = document.getElementById('clearFiltersBtn');
            const courseId = document.getElementById('filterCourse').value;
            const moduleId = document.getElementById('filterModule')?.value || '';
            const subjectId = document.getElementById('filterSubject').value;
            const parts = [`Showing ${count} question${count === 1 ? '' : 's'}`];

            if (courseId) {
                parts.push(getCourseName(courseId));
            }
            if (moduleId) {
                const mod = (modulesByCourse[courseId] || []).find(m => String(m.Module_ID) === String(moduleId));
                if (mod) parts.push(mod.Module_Name);
            }
            if (subjectId) {
                const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
                if (subject) parts.push(subject.Subject_Name);
            }

            summary.textContent = parts.join(' • ');
            clearBtn.style.display = hasActiveFilters() ? 'inline-block' : 'none';
        }

        function clearFilters() {
            document.getElementById('filterCourse').value = '';
            currentFilterModule = '';
            document.getElementById('filterModule').value = '';
            document.getElementById('filterSubject').value = '';
            document.getElementById('searchQuestion').value = '';
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('filterModule'), '', 'All modules');
            }
            populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
            loadQuestions();
        }

        function loadCourses() {
            return axios.get('../../api/masterfiles/courses.php?action=list')
                .then(response => {
                    if (response.data.success) {
                        allCourses = response.data.data || [];
                        const filterSelect = document.getElementById('filterCourse');
                        const builderCourseSelect = document.getElementById('builderCourse');
                        const editCourseSelect = document.getElementById('editQuestionCourse');

                        filterSelect.innerHTML = '<option value="">All courses</option>';
                        builderCourseSelect.innerHTML = '<option value="">Select course...</option>';
                        editCourseSelect.innerHTML = '<option value="">Select course...</option>';

                        allCourses.forEach(course => {
                            filterSelect.add(new Option(course.Course_Name, course.Course_ID));
                            builderCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                            editCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                        });

                        const importCourseSelect = document.getElementById('importCourse');
                        if (importCourseSelect) {
                            importCourseSelect.innerHTML = '<option value="">Select course...</option>';
                            allCourses.forEach(course => {
                                importCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                            });
                        }
                    }
                })
                .catch(error => console.error('Error loading courses:', error));
        }

        function loadCurriculumData() {
            return axios.get(`${MODULES_API}?action=tree`)
                .then(response => {
                    if (response.data.success) {
                        modulesEnabled = true;
                        buildCurriculumMaps(response.data.data || []);
                        setModuleFieldsVisible(true);
                        populateModuleOptions(document.getElementById('filterModule'), '', 'All modules');
                        populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
                        return;
                    }
                    return loadSubjectsLegacy();
                })
                .catch(err => {
                    if (err.response?.status === 503) {
                        return loadSubjectsLegacy();
                    }
                    console.error('Error loading curriculum:', err);
                    return loadSubjectsLegacy();
                });
        }

        function loadSubjectsLegacy() {
            modulesEnabled = false;
            setModuleFieldsVisible(false);
            return axios.get('../../api/masterfiles/subjects.php?action=list')
                .then(response => {
                    if (response.data.success) {
                        allSubjects = response.data.data || [];
                        populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
                    }
                })
                .catch(error => console.error('Error loading subjects:', error));
        }

        function loadSubjectsData() {
            return loadCurriculumData();
        }

        // Load questions
        function loadQuestions() {
            const courseFilter = document.getElementById('filterCourse').value;
            const moduleFilter = document.getElementById('filterModule')?.value || '';
            const subjectFilter = document.getElementById('filterSubject').value;
            const searchTerm = document.getElementById('searchQuestion').value.trim();

            let url = '../../api/masterfiles/exam.php?action=get_questions';
            if (courseFilter) url += `&course_id=${encodeURIComponent(courseFilter)}`;
            if (moduleFilter) url += `&module_id=${encodeURIComponent(moduleFilter)}`;
            if (subjectFilter) url += `&subject_id=${encodeURIComponent(subjectFilter)}`;
            if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;

            axios.get(url)
                .then(response => {
                    const container = document.getElementById('questionsContainer');
                    const questions = response.data.success ? (response.data.questions || []) : [];

                    updateFilterSummary(questions.length);

                    if (questions.length > 0) {
                        container.innerHTML = questions.map(q => {
                            const usageCount = parseInt(q.exam_usage_count, 10) || 0;
                            const usageBadge = usageCount > 0
                                ? `<span class="badge bg-warning text-dark ms-1">Used in ${usageCount} exam${usageCount === 1 ? '' : 's'}</span>`
                                : '<span class="badge bg-light text-muted border ms-1">Not used</span>';

                            return `
                            <div class="question-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <span class="badge bg-dark me-1">${escapeHtml(q.Course_Name || 'N/A')}</span>
                                        ${q.Module_Name ? `<span class="badge bg-info text-dark me-1">${escapeHtml(q.Module_Name)}</span>` : ''}
                                        <span class="badge bg-secondary me-2">${escapeHtml(q.Subject_Name || 'N/A')}</span>
                                        <span class="badge bg-primary">Multiple Choice</span>
                                        ${usageBadge}
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline" onclick="editQuestion(${q.Question_ID})">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline" onclick="archiveQuestion(${q.Question_ID})" title="Archive">
                                            <i class="bi bi-archive"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="question-text">${escapeHtml(q.Question_Text)}</div>
                                <div class="mt-2">
                                    ${q.answers ? q.answers.map(a => `
                                        <div class="answer-option ${a.Is_Correct ? 'correct' : ''}">
                                            ${a.Is_Correct ? '<i class="bi bi-check-circle-fill text-success me-2"></i>' : ''}
                                            ${escapeHtml(a.Choice_Text)}
                                        </div>
                                    `).join('') : '<small class="text-muted">No answers available</small>'}
                                </div>
                            </div>
                        `;
                        }).join('');
                    } else {
                        container.innerHTML = '<div class="text-center text-muted py-4">No questions found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading questions:', error);
                    document.getElementById('questionsContainer').innerHTML =
                        '<div class="text-center text-danger py-4">Error loading questions</div>';
                });
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Edit question form submission
        document.getElementById('editQuestionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const questionId = document.getElementById('editQuestionId').value;
            const formData = {
                subject_id: document.getElementById('editQuestionSubject').value,
                question_text: document.getElementById('editQuestionText').value,
                question_type: 'Multiple Choice',
                points: document.getElementById('editQuestionPoints').value,
                answers: []
            };

            const answerInputs = document.querySelectorAll('#editAnswersList input[type="text"]');
            const correctAnswerIndex = parseInt(document.querySelector('input[name="editCorrectAnswer"]:checked')?.value ?? '0', 10);

            if (answerInputs.length !== CHOICE_COUNT) {
                Swal.fire('Error', `Exactly ${CHOICE_COUNT} answer options are required`, 'error');
                return;
            }

            answerInputs.forEach((input, index) => {
                const text = input.value.trim();
                if (!text) {
                    return;
                }
                formData.answers.push({
                    answer_text: text,
                    is_correct: index === correctAnswerIndex
                });
            });

            if (formData.answers.length !== CHOICE_COUNT) {
                Swal.fire('Error', `Please fill in all ${CHOICE_COUNT} answer options (A through E)`, 'error');
                return;
            }

            axios.post(`../../api/masterfiles/exam.php?action=update_question&question_id=${questionId}`, formData)
                .then(response => {
                    if (response.data.success) {
                        Swal.fire('Success', response.data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editQuestionModal')).hide();
                        loadQuestions();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to update question', 'error');
                });
        });

        // Edit question
        function editQuestion(questionId) {
            // Find the question in the loaded questions
            axios.get(`../../api/masterfiles/exam.php?action=get_questions`)
                .then(response => {
                    if (response.data.success) {
                        const question = response.data.questions.find(q => q.Question_ID == questionId);
                        if (!question) {
                            Swal.fire('Error', 'Question not found', 'error');
                            return;
                        }

                        // Populate the form
                        document.getElementById('editQuestionId').value = question.Question_ID;
                        const subject = allSubjects.find(s => String(s.Subject_ID) === String(question.Subject_ID));
                        const courseId = subject ? subject.Course_ID : '';
                        document.getElementById('editQuestionCourse').value = courseId || '';
                        const moduleId = subject ? (subject.Module_ID || findModuleIdForSubject(subject.Subject_ID, courseId)) : '';
                        if (modulesEnabled) {
                            populateModuleOptions(document.getElementById('editQuestionModule'), courseId, 'Select module...', moduleId);
                        }
                        populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                            courseId,
                            moduleId,
                            placeholder: 'Select subject...',
                            selectedId: question.Subject_ID,
                        });
                        document.getElementById('editQuestionText').value = question.Question_Text;
                        document.getElementById('editQuestionPoints').value = question.Points || 1;

                        const paddedAnswers = [...(question.answers || [])];
                        while (paddedAnswers.length < CHOICE_COUNT) {
                            paddedAnswers.push({ Choice_Text: '', Is_Correct: 0 });
                        }
                        renderAnswerOptions(paddedAnswers.slice(0, CHOICE_COUNT));

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editQuestionModal')).show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to load question', 'error');
                });
        }

        // Archive question
        function archiveQuestion(questionId) {
            Swal.fire({
                title: 'Archive Question?',
                html: 'The question will be hidden from the bank and permanently removed after <strong>2 years</strong>. You can restore it from the Archive page.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Archive',
                confirmButtonColor: '#d97706'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(`../../api/masterfiles/exam.php?action=archive_question`, { question_id: questionId })
                        .then(response => {
                            if (response.data.success) {
                                Swal.fire('Archived', response.data.message, 'success');
                                loadQuestions();
                            } else {
                                Swal.fire('Error', response.data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            const errorMsg = error.response?.data?.message || 'Failed to archive question';
                            Swal.fire('Error', errorMsg, 'error');
                        });
                }
            });
        }

        // Filter handlers
        document.getElementById('filterCourse').addEventListener('change', function() {
            const courseId = this.value;
            currentFilterModule = '';
            document.getElementById('filterModule').value = '';
            document.getElementById('filterSubject').value = '';
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('filterModule'), courseId, 'All modules');
            }
            populateSubjectOptions(document.getElementById('filterSubject'), {
                courseId,
                placeholder: 'All subjects',
            });
            loadQuestions();
        });
        document.getElementById('filterModule')?.addEventListener('change', function() {
            currentFilterModule = this.value || '';
            document.getElementById('filterSubject').value = '';
            populateSubjectOptions(document.getElementById('filterSubject'), {
                courseId: document.getElementById('filterCourse').value,
                moduleId: currentFilterModule,
                placeholder: 'All subjects',
            });
            loadQuestions();
        });
        document.getElementById('filterSubject').addEventListener('change', loadQuestions);
        document.getElementById('searchQuestion').addEventListener('input', debounce(loadQuestions, 500));
        document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);

        document.getElementById('builderCourse')?.addEventListener('change', function() {
            const courseId = this.value;
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('builderModule'), courseId, 'Select module...');
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
            } else {
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
            }
            updateBuilderContextLabel();
        });

        document.getElementById('builderModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('builderSubject'), {
                courseId: document.getElementById('builderCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
            updateBuilderContextLabel();
        });

        document.getElementById('builderSubject')?.addEventListener('change', updateBuilderContextLabel);

        document.getElementById('editQuestionCourse')?.addEventListener('change', function() {
            const courseId = this.value;
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('editQuestionModule'), courseId, 'Select module...');
                populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
                return;
            }
            populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                courseId,
                placeholder: 'Select subject...',
            });
        });

        document.getElementById('editQuestionModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                courseId: document.getElementById('editQuestionCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
        });

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Initialize
        Promise.all([loadCourses(), loadSubjectsData()])
            .then(() => {
                QuestionBuilder.init({
                    canvasId: 'builderQuestionCanvas',
                    countId: 'builderQuestionCount',
                    addBtnId: 'builderAddQuestionBtn',
                    clearBtnId: 'builderClearBtn',
                    getSubjectId: getBuilderSubjectId,
                    subjectRequiredMessage: modulesEnabled
                        ? 'Please select course, module, and subject first'
                        : 'Please select course and subject first',
                    onError: (message) => Swal.fire('Cannot add question', message, 'warning'),
                    onCountChange: (count) => {
                        const saveBtn = document.getElementById('saveBuilderQuestionsBtn');
                        if (saveBtn) {
                            saveBtn.textContent = count > 0 ? `Save ${count} Question${count === 1 ? '' : 's'}` : 'Save Questions';
                        }
                    }
                });
                return loadQuestions();
            })
            .catch(error => {
                console.error('Error initializing question bank:', error);
                document.getElementById('questionsContainer').innerHTML =
                    '<div class="text-center text-danger py-4">Failed to load question bank. Please refresh the page.</div>';
                document.getElementById('questionsFilterSummary').textContent = 'Error loading filters';
            });

        document.getElementById('addQuestionsModal').addEventListener('show.bs.modal', function() {
            QuestionBuilder.clear();

            const filterCourse = document.getElementById('filterCourse').value;
            const filterModule = document.getElementById('filterModule')?.value || '';
            const filterSubject = document.getElementById('filterSubject').value;

            document.getElementById('builderSubject').disabled = true;
            document.getElementById('builderSubject').innerHTML = '<option value="">Select subject...</option>';
            if (modulesEnabled) {
                document.getElementById('builderModule').disabled = true;
                document.getElementById('builderModule').innerHTML = '<option value="">Select module...</option>';
            }

            if (filterCourse) {
                document.getElementById('builderCourse').value = filterCourse;
                if (modulesEnabled) {
                    populateModuleOptions(document.getElementById('builderModule'), filterCourse, 'Select module...', filterModule || '');
                }
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId: filterCourse,
                    moduleId: filterModule,
                    placeholder: 'Select subject...',
                    selectedId: filterSubject || '',
                });
            } else {
                document.getElementById('builderCourse').value = '';
            }

            updateBuilderContextLabel();
            const saveBtn = document.getElementById('saveBuilderQuestionsBtn');
            if (saveBtn) saveBtn.textContent = 'Save Questions';
        });

        document.getElementById('saveBuilderQuestionsBtn').addEventListener('click', async function() {
            const saveBtn = this;
            saveBtn.disabled = true;
            try {
                const savedCount = await QuestionBuilder.saveAll();
                bootstrap.Modal.getInstance(document.getElementById('addQuestionsModal')).hide();
                Swal.fire('Success', `${savedCount} question${savedCount === 1 ? '' : 's'} added to the bank`, 'success');
                loadQuestions();
            } catch (err) {
                Swal.fire('Error', err.response?.data?.message || err.message || 'Failed to save questions', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        // Import functionality
        let importData = [];
        let importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };
        let importPreviewMode = 'table';

        const IMPORT_COLUMN_ALIASES = {
            Question: ['Question', 'question', 'QUESTION', 'Question Text', 'Question_Text'],
            Option_A: ['Option_A', 'Option A', 'option_a', 'A'],
            Option_B: ['Option_B', 'Option B', 'option_b', 'B'],
            Option_C: ['Option_C', 'Option C', 'option_c', 'C'],
            Option_D: ['Option_D', 'Option D', 'option_d', 'D'],
            Option_E: ['Option_E', 'Option E', 'option_e', 'E'],
            Correct_Answer: ['Correct_Answer', 'Correct Answer', 'correct_answer', 'Answer', 'Correct'],
            Subject: ['Subject', 'subject', 'Subject_Name', 'Subject Code', 'Subject_Code']
        };

        function normalizeImportRow(rawRow) {
            const normalized = {};
            Object.entries(rawRow || {}).forEach(([key, value]) => {
                const trimmedKey = String(key).trim();
                Object.entries(IMPORT_COLUMN_ALIASES).forEach(([canonical, aliases]) => {
                    if (aliases.some(alias => alias.toLowerCase() === trimmedKey.toLowerCase())) {
                        normalized[canonical] = value;
                    }
                });
            });
            return normalized;
        }

        function getSelectedImportSubjectId() {
            return document.getElementById('importSubject')?.value || '';
        }

        function ensureImportSubjectSelected() {
            if (!getSelectedImportSubjectId()) {
                const msg = modulesEnabled
                    ? 'Please select a course, module, and subject first.'
                    : 'Please select a course and subject first.';
                Swal.fire('Select Subject', msg, 'warning');
                return false;
            }
            return true;
        }

        function resetImportState() {
            importData = [];
            importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };
            importPreviewMode = 'table';
            document.getElementById('importBtn').disabled = true;
            document.getElementById('importPreview').style.display = 'none';
            document.getElementById('importValidation').style.display = 'none';
            document.getElementById('importValidation').innerHTML = '';
            document.getElementById('importPreviewCards').innerHTML = '';
            document.getElementById('importPreviewCards').classList.add('d-none');
            document.getElementById('importPreviewTableWrap').classList.remove('d-none');
        }

        function validateImportRow(row) {
            const errors = [];
            const question = String(row.Question || '').trim();

            if (!question) {
                errors.push('Question text is required');
            }

            ['A', 'B', 'C', 'D'].forEach(letter => {
                const key = 'Option_' + letter;
                if (!String(row[key] || '').trim()) {
                    errors.push(`Choice ${letter} is required`);
                }
            });

            const correct = String(row.Correct_Answer || '').trim().toUpperCase();
            if (!['A', 'B', 'C', 'D', 'E'].includes(correct)) {
                errors.push('Answer must be A, B, C, D, or E');
            } else {
                const correctKey = 'Option_' + correct;
                if (!String(row[correctKey] || '').trim()) {
                    errors.push(`Answer is ${correct} but choice ${correct} is empty`);
                }
            }

            return errors;
        }

        function validateImportRows(rows) {
            const invalidRows = [];
            let validCount = 0;

            rows.forEach((row, index) => {
                if (!String(row.Question || '').trim()) {
                    return;
                }

                const errors = validateImportRow(row);
                if (errors.length) {
                    invalidRows.push({ rowNum: index + 1, errors });
                } else {
                    validCount++;
                }
            });

            return { validCount, invalidRows, invalidBlocks: [] };
        }

        function setImportReadyState(rows, validationResult, previewMode) {
            importData = rows;
            importValidationResult = validationResult;
            importPreviewMode = previewMode;
            displayImportValidation(validationResult);
            displayImportPreview(rows, previewMode);

            const canImport = validationResult.validCount > 0
                && validationResult.invalidRows.length === 0
                && validationResult.invalidBlocks.length === 0;
            document.getElementById('importBtn').disabled = !canImport;
        }

        function displayImportValidation(result) {
            const container = document.getElementById('importValidation');
            if (!container) return;

            const { validCount, invalidRows, invalidBlocks } = result;
            let html = '';

            if (validCount > 0) {
                html += `<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle me-2"></i><strong>${validCount}</strong> question${validCount === 1 ? '' : 's'} ready to import</div>`;
            }

            if (invalidBlocks.length > 0) {
                html += `<div class="alert alert-danger py-2 mb-2"><strong>${invalidBlocks.length} question block${invalidBlocks.length === 1 ? '' : 's'} with errors:</strong><ul class="mb-0 mt-2 small">`;
                invalidBlocks.slice(0, 15).forEach(item => {
                    const label = item.preview ? escapeHtml(item.preview.substring(0, 80)) : `Block ${item.blockNum}`;
                    html += `<li><strong>Question ${item.blockNum}:</strong> ${label}${item.preview && item.preview.length > 80 ? '...' : ''}<br><span class="text-muted">${escapeHtml(item.errors.join('; '))}</span></li>`;
                });
                if (invalidBlocks.length > 15) {
                    html += `<li>...and ${invalidBlocks.length - 15} more</li>`;
                }
                html += '</ul></div>';
            }

            if (invalidRows.length > 0) {
                html += `<div class="alert alert-danger py-2 mb-0"><strong>${invalidRows.length} row${invalidRows.length === 1 ? '' : 's'} with errors:</strong><ul class="mb-0 mt-2 small">`;
                invalidRows.slice(0, 15).forEach(item => {
                    html += `<li>Row ${item.rowNum}: ${escapeHtml(item.errors.join('; '))}</li>`;
                });
                if (invalidRows.length > 15) {
                    html += `<li>...and ${invalidRows.length - 15} more</li>`;
                }
                html += '</ul></div>';
            }

            if (!validCount && !invalidRows.length && !invalidBlocks.length) {
                html = '<div class="alert alert-warning py-2 mb-0">No questions found. Upload an Excel file to import.</div>';
            }

            container.innerHTML = html;
            container.style.display = 'block';
        }

        function displayImportPreview(rows, previewMode) {
            const preview = document.getElementById('importPreview');
            const cards = document.getElementById('importPreviewCards');
            const tableWrap = document.getElementById('importPreviewTableWrap');
            const table = document.getElementById('previewTable');

            if (!preview || !rows.length) {
                if (preview) preview.style.display = 'none';
                return;
            }

            if (previewMode === 'cards') {
                tableWrap.classList.add('d-none');
                cards.classList.remove('d-none');
                cards.innerHTML = rows.slice(0, 8).map((row, index) => `
                    <div class="border rounded p-3 mb-2 bg-light">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <strong class="small text-muted">Question ${index + 1}</strong>
                            <span class="badge bg-success">Answer: ${escapeHtml(row.Correct_Answer || '')}</span>
                        </div>
                        <div class="mb-2">${escapeHtml(row.Question || '')}</div>
                        <div class="small text-muted">
                            ${['A', 'B', 'C', 'D', 'E'].map(letter => {
                                const text = row['Option_' + letter];
                                if (!text) return '';
                                const isCorrect = String(row.Correct_Answer).toUpperCase() === letter;
                                return `<div class="${isCorrect ? 'text-success fw-semibold' : ''}">${letter}. ${escapeHtml(text)}</div>`;
                            }).join('')}
                        </div>
                    </div>
                `).join('');
                if (rows.length > 8) {
                    cards.innerHTML += `<p class="small text-muted mb-0">...and ${rows.length - 8} more question${rows.length - 8 === 1 ? '' : 's'}</p>`;
                }
            } else {
                cards.classList.add('d-none');
                tableWrap.classList.remove('d-none');
                const displayColumns = ['Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Option_E', 'Correct_Answer'];
                table.querySelector('thead').innerHTML = `<tr>${displayColumns.map(h => `<th>${h}</th>`).join('')}</tr>`;
                table.querySelector('tbody').innerHTML = rows.slice(0, 5).map(row =>
                    `<tr>${displayColumns.map(h => `<td>${escapeHtml(String(row[h] || ''))}</td>`).join('')}</tr>`
                ).join('');
            }

            preview.style.display = 'block';
        }

        function processImportFileRows(rawRows) {
            if (!ensureImportSubjectSelected()) {
                return;
            }

            const rows = (rawRows || [])
                .map(normalizeImportRow)
                .filter(row => String(row.Question || '').trim());

            if (!rows.length) {
                Swal.fire('Error', 'No question rows found in the file.', 'error');
                resetImportState();
                return;
            }

            const firstRow = rows[0];
            const requiredColumns = ['Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Correct_Answer'];
            const missingColumns = requiredColumns.filter(col => !(col in firstRow) || firstRow[col] === undefined);
            if (missingColumns.length > 0) {
                Swal.fire('Error', `Missing required columns: ${missingColumns.join(', ')}`, 'error');
                resetImportState();
                return;
            }

            const validationResult = validateImportRows(rows);
            setImportReadyState(rows, validationResult, 'table');
        }

        document.getElementById('importQuestionsModal').addEventListener('show.bs.modal', function() {
            resetImportState();
            document.getElementById('importForm')?.reset();
            document.getElementById('importSubject').disabled = true;
            document.getElementById('importSubject').innerHTML = '<option value="">Select subject...</option>';
            if (modulesEnabled) {
                document.getElementById('importModule').disabled = true;
                document.getElementById('importModule').innerHTML = '<option value="">Select module...</option>';
            }

            const filterCourse = document.getElementById('filterCourse').value;
            const filterModule = document.getElementById('filterModule')?.value || '';
            const filterSubject = document.getElementById('filterSubject').value;
            if (filterCourse) {
                document.getElementById('importCourse').value = filterCourse;
                if (modulesEnabled) {
                    populateModuleOptions(document.getElementById('importModule'), filterCourse, 'Select module...', filterModule || '');
                }
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: filterCourse,
                    moduleId: filterModule,
                    placeholder: 'Select subject...',
                    selectedId: filterSubject || '',
                });
            }
        });

        document.getElementById('importCourse').addEventListener('change', function() {
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('importModule'), this.value, 'Select module...');
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: this.value,
                    placeholder: 'Select subject...',
                });
            } else {
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: this.value,
                    placeholder: 'Select subject...',
                });
            }
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('importSubject'), {
                courseId: document.getElementById('importCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importSubject').addEventListener('change', function() {
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!ensureImportSubjectSelected()) {
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(evt) {
                try {
                    const data = new Uint8Array(evt.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheetName = workbook.SheetNames.find(name => name.toLowerCase() === 'questions') || workbook.SheetNames[0];
                    const sheet = workbook.Sheets[sheetName];
                    const jsonData = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                    processImportFileRows(jsonData);
                } catch (error) {
                    console.error('Error reading file:', error);
                    Swal.fire('Error', 'Failed to read file. Please check the format.', 'error');
                    resetImportState();
                }
            };
            reader.readAsArrayBuffer(file);
        });

        document.getElementById('importBtn').addEventListener('click', function() {
            if (importData.length === 0 || importValidationResult.validCount === 0) return;
            if (importValidationResult.invalidRows.length > 0 || importValidationResult.invalidBlocks.length > 0) return;

            Swal.fire({
                title: 'Import Questions?',
                text: `This will import ${importValidationResult.validCount} question${importValidationResult.validCount === 1 ? '' : 's'}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, import',
                confirmButtonColor: '#2563eb'
            }).then((result) => {
                if (result.isConfirmed) {
                    performImport();
                }
            });
        });

        function performImport() {
            const importBtn = document.getElementById('importBtn');
            const subjectId = getSelectedImportSubjectId();
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';

            axios.post('../../api/masterfiles/exam.php?action=import_questions', {
                subject_id: subjectId,
                questions: importData
            })
            .then(response => {
                if (response.data.success) {
                    let errorDetails = '';
                    if (response.data.errors && response.data.errors.length > 0) {
                        errorDetails = '<div class="mt-3 text-start alert alert-danger"><strong>Error details:</strong><ul class="mb-0 mt-2">';
                        response.data.errors.forEach(err => {
                            errorDetails += `<li>${escapeHtml(err)}</li>`;
                        });
                        errorDetails += '</ul></div>';
                    }

                    Swal.fire({
                        title: response.data.failed > 0 ? 'Import Completed with Errors' : 'Success!',
                        html: `
                            <div class="text-start">
                                <p><strong>Imported:</strong> ${response.data.imported || 0} questions</p>
                                ${response.data.failed ? `<p><strong>Failed:</strong> ${response.data.failed} questions</p>` : ''}
                                ${errorDetails}
                            </div>
                        `,
                        icon: response.data.failed > 0 ? 'warning' : 'success',
                        width: '700px'
                    });

                    if (response.data.imported > 0) {
                        bootstrap.Modal.getInstance(document.getElementById('importQuestionsModal')).hide();
                        loadQuestions();
                        document.getElementById('importForm').reset();
                        resetImportState();
                    }
                } else {
                    Swal.fire('Error', response.data.message || 'Import failed', 'error');
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                const errorMsg = error.response?.data?.message || 'Failed to import questions. Please check your file format.';
                Swal.fire('Error', errorMsg, 'error');
            })
            .finally(() => {
                const blocked = importValidationResult.invalidRows.length > 0
                    || importValidationResult.invalidBlocks.length > 0;
                importBtn.disabled = importValidationResult.validCount === 0 || blocked;
                importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';
            });
        }

        function buildImportWorkbook(questionRows, subject) {
            const instructions = [
                { Step: '1', Instruction: 'Select Course and Subject in the Import Questions dialog before uploading.' },
                { Step: '2', Instruction: 'Fill in questions on the Questions sheet only. Do not rename the column headers.' },
                { Step: '3', Instruction: 'Columns: Question, Option_A, Option_B, Option_C, Option_D, Option_E, Correct_Answer.' },
                { Step: '4', Instruction: 'Correct_Answer must be A, B, C, D, or E.' },
                { Step: '5', Instruction: subject ? `This file is for subject: ${subject.Subject_Name}${subject.Subject_Code ? ' (' + subject.Subject_Code + ')' : ''}` : 'Pick a subject in the import dialog before downloading.' }
            ];

            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(instructions), 'Instructions');

            const qWs = XLSX.utils.json_to_sheet(questionRows);
            qWs['!cols'] = [
                { wch: 55 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 16 }
            ];
            XLSX.utils.book_append_sheet(wb, qWs, 'Questions');
            return wb;
        }

        function getSampleQuestionRows(subject) {
            const subjectLabel = subject ? subject.Subject_Name : 'your subject';
            return [
                {
                    Question: `Sample question 1 for ${subjectLabel}?`,
                    Option_A: 'First choice',
                    Option_B: 'Second choice',
                    Option_C: 'Third choice (correct)',
                    Option_D: 'Fourth choice',
                    Option_E: 'Fifth choice',
                    Correct_Answer: 'C'
                },
                {
                    Question: `Sample question 2 for ${subjectLabel}?`,
                    Option_A: 'True statement',
                    Option_B: 'Another option',
                    Option_C: 'Correct choice',
                    Option_D: 'Incorrect choice',
                    Option_E: 'Extra option',
                    Correct_Answer: 'C'
                }
            ];
        }

        function downloadImportTemplate() {
            const subjectId = getSelectedImportSubjectId();
            if (!subjectId) {
                Swal.fire('Select Subject', 'Please select a course and subject first.', 'warning');
                return;
            }

            const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
            const wb = buildImportWorkbook(getSampleQuestionRows(subject), subject);
            const filenameSubject = subject?.Subject_Code || subject?.Subject_Name || 'template';
            XLSX.writeFile(wb, `question_import_${String(filenameSubject).replace(/[^a-z0-9_-]+/gi, '_')}.xlsx`);
        }

        document.getElementById('downloadImportTemplateBtn').addEventListener('click', downloadImportTemplate);
    </script>
</body>
</html>
