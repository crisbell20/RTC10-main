const API_BASE = '../../api/masterfiles/';
const COURSES_API = API_BASE + 'courses.php';
const MODULES_API = API_BASE + 'modules.php';
const SUBJECTS_API = API_BASE + 'subjects.php';
const EXAMS_API = API_BASE + 'exams.php';
const EXAM_QUESTIONS_API = API_BASE + 'exam-questions.php';
const EXAM_BATCHES_API = API_BASE + 'exam-batches.php';
const EXAM_PARTICIPANTS_API = API_BASE + 'exam-participants.php';
const QUESTION_BANK_API = API_BASE + 'exam.php';
const QUESTION_CHOICE_COUNT = 5;

const EXAM_UI_CONFIG = Object.assign({
    role: 'Admin',
    canCreateExam: true,
    canEditExamDetails: true,
    canEditQuestions: true,
    canManageParticipants: true,
    canViewParticipants: true,
    canViewResults: true,
    responsesPath: 'exam-responses.php',
    pageTitle: 'Manage Exams',
    pageSubtitle: '',
    participantsModalTitle: 'Manage Participants',
    participantsModalSubtitle: 'Select who can access this exam',
}, window.EXAM_UI_CONFIG || {});

/**
 * MySQL / ISO datetime → value for input[type=datetime-local] (YYYY-MM-DDTHH:mm).
 */
function toDatetimeLocalInputValue(stored) {
    if (stored == null || stored === '') {
        return '';
    }
    return String(stored).trim().replace(' ', 'T').slice(0, 16);
}

function syncExamDeadlineMinFromSchedule() {
    const scheduleInput = document.getElementById('examSchedule');
    const deadlineInput = document.getElementById('examDeadline');
    if (!deadlineInput) {
        return;
    }
    const raw = scheduleInput?.value || '';
    deadlineInput.min = raw ? toDatetimeLocalInputValue(raw) : '';
}

let exams = [];
let courses = [];
let modulesEnabled = false;
let curriculumTree = [];
let modulesByCourse = {};
let subjectsByModule = {};
let subjectsByCourse = {};
let allQuestions = [];
let examCourseTree = [];
let currentPickerQuestions = [];
let examQuestionCanvas = [];
let draftQuestionCounter = 0;
let dragCanvasIndex = null;
let currentExamId = null;
let selectedQuestionIds = [];
let selectedExamAssignments = [];
let examQuestionsLoaded = false;
let examAssignmentsLoaded = false;
let examEditHydrating = false;
let editExamLoadToken = 0;
let originalEditSubjectId = null;
let originalEditCourseId = null;
let originalEditModuleId = null;
let preservedQuestionIds = [];
let preservedSectionIds = [];
let questionsDirty = false;
let assignmentsDirty = false;

let participantsModal = null;
let participantsExamId = null;
let participantGroups = [];
let participantSelectedIds = new Set();
let participantSearchTerm = '';
let participantSectionFilter = '';
let participantsViewOnly = false;
let questionPickerContextOverride = null;

let currentCourseFilter = '';
let currentModuleFilter = '';
let currentSubjectFilter = '';
let currentStatusFilter = '';

function isCcmdExamMode() {
    return EXAM_UI_CONFIG.role === 'CCMD';
}

function applyExamPageConfig() {
    const addBtn = document.getElementById('addExamBtn');
    if (addBtn) {
        addBtn.style.display = EXAM_UI_CONFIG.canCreateExam ? '' : 'none';
    }

    const pageTitle = document.getElementById('examPageTitle');
    if (pageTitle && EXAM_UI_CONFIG.pageTitle) {
        pageTitle.textContent = EXAM_UI_CONFIG.pageTitle;
    }

    const pageSubtitle = document.getElementById('examPageSubtitle');
    if (pageSubtitle) {
        if (EXAM_UI_CONFIG.pageSubtitle) {
            pageSubtitle.textContent = EXAM_UI_CONFIG.pageSubtitle;
            pageSubtitle.style.display = '';
        } else {
            pageSubtitle.style.display = 'none';
        }
    }

    const participantsTitle = document.getElementById('participantsModalTitle');
    if (participantsTitle && EXAM_UI_CONFIG.participantsModalTitle) {
        participantsTitle.textContent = EXAM_UI_CONFIG.participantsModalTitle;
    }

    const participantsSubtitle = document.getElementById('participantsModalSubtitle');
    if (participantsSubtitle && EXAM_UI_CONFIG.participantsModalSubtitle) {
        participantsSubtitle.textContent = EXAM_UI_CONFIG.participantsModalSubtitle;
    }
}

function setParticipantsModalMode(viewOnly) {
    participantsViewOnly = viewOnly || !EXAM_UI_CONFIG.canManageParticipants;
    const saveBtn = document.getElementById('saveParticipantsBtn');
    const selectAllBtn = document.getElementById('participantSelectAllBtn');
    const clearAllBtn = document.getElementById('participantClearAllBtn');
    const countBadge = document.getElementById('participantSelectionCount');

    [saveBtn, selectAllBtn, clearAllBtn].forEach(el => {
        if (el) el.classList.toggle('d-none', participantsViewOnly);
    });
    if (countBadge) {
        countBadge.classList.toggle('d-none', participantsViewOnly);
    }
}

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function buildCurriculumMaps(tree) {
    modulesByCourse = {};
    subjectsByModule = {};
    subjectsByCourse = {};

    (tree || []).forEach(course => {
        const courseId = course.Course_ID;
        modulesByCourse[courseId] = course.modules || [];
        subjectsByCourse[courseId] = [];

        (course.modules || []).forEach(mod => {
            const moduleSubjects = (mod.subjects || []).map(s => ({
                ...s,
                Module_ID: mod.Module_ID,
                Module_Name: mod.Module_Name,
                Course_ID: courseId,
            }));
            subjectsByModule[mod.Module_ID] = moduleSubjects;
            subjectsByCourse[courseId].push(...moduleSubjects);
        });
    });
}

function setModuleFieldsVisible(visible) {
    document.getElementById('filterModuleWrap')?.classList.toggle('d-none', !visible);
    document.getElementById('examModuleWrap')?.classList.toggle('d-none', !visible);
    const examModule = document.getElementById('examModule');
    if (examModule) {
        examModule.required = visible;
        if (!visible) examModule.value = '';
    }
}

function populateModuleSelect(selectEl, courseId, placeholder, selectedModuleId) {
    if (!selectEl) return;
    const label = placeholder || 'All modules';
    selectEl.innerHTML = `<option value="">${label}</option>`;

    if (!courseId) {
        selectEl.disabled = true;
        return;
    }

    const modules = modulesByCourse[courseId] || [];
    modules.forEach(mod => {
        const opt = new Option(mod.Module_Name, mod.Module_ID);
        if (selectedModuleId && String(selectedModuleId) === String(mod.Module_ID)) {
            opt.selected = true;
        }
        selectEl.add(opt);
    });
    selectEl.disabled = modules.length === 0;
}

function populateSubjectSelect(selectEl, options = {}) {
    const {
        courseId = '',
        moduleId = '',
        placeholder = 'All subjects',
        selectedSubjectId = '',
    } = options;

    if (!selectEl) return;
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;

    let list = [];
    if (modulesEnabled && moduleId) {
        list = subjectsByModule[moduleId] || [];
    } else if (modulesEnabled && courseId) {
        list = subjectsByCourse[courseId] || [];
    } else if (courseId) {
        list = subjectsByCourse[courseId] || [];
    } else {
        list = Object.values(subjectsByCourse).flat();
    }

    list.forEach(s => {
        const opt = new Option(s.Subject_Name, s.Subject_ID);
        if (selectedSubjectId && String(selectedSubjectId) === String(s.Subject_ID)) {
            opt.selected = true;
        }
        selectEl.add(opt);
    });

    if (selectEl.id === 'examSubject') {
        selectEl.disabled = modulesEnabled ? !moduleId : !courseId;
    }
}

function loadCurriculumData() {
    return axios.get(`${COURSES_API}?action=list`)
        .then(res => {
            if (res.data.success) {
                courses = res.data.data || [];
                const courseSelect = document.getElementById('filterCourse');
                const examCourse = document.getElementById('examCourse');
                if (courseSelect) {
                    courseSelect.innerHTML = '<option value="">All courses</option>';
                }
                if (examCourse) {
                    examCourse.innerHTML = '<option value="">Select course</option>';
                }
                courses.forEach(c => {
                    if (courseSelect) {
                        courseSelect.add(new Option(c.Course_Name, c.Course_ID));
                    }
                    if (examCourse) {
                        examCourse.add(new Option(c.Course_Name, c.Course_ID));
                    }
                });
            }
        })
        .then(() => axios.get(`${MODULES_API}?action=tree`))
        .then(res => {
            if (res.data.success) {
                modulesEnabled = true;
                curriculumTree = res.data.data || [];
                buildCurriculumMaps(curriculumTree);
                setModuleFieldsVisible(true);
            } else {
                return loadSubjectsLegacy();
            }
        })
        .catch(err => {
            if (err.response?.status === 503) {
                return loadSubjectsLegacy();
            }
            console.error('Error loading curriculum:', err);
            return loadSubjectsLegacy();
        })
        .finally(() => {
            populateSubjectFilters();
        });
}

function loadSubjectsLegacy() {
    modulesEnabled = false;
    curriculumTree = [];
    modulesByCourse = {};
    subjectsByModule = {};
    setModuleFieldsVisible(false);

    return axios.get(`${SUBJECTS_API}?action=list`)
        .then(res => {
            if (!res.data.success) return;
            const list = res.data.data || [];
            subjectsByCourse = {};
            list.forEach(s => {
                if (!subjectsByCourse[s.Course_ID]) {
                    subjectsByCourse[s.Course_ID] = [];
                }
                subjectsByCourse[s.Course_ID].push(s);
            });
        });
}

function loadCoursesAndSubjects() {
    return loadCurriculumData();
}

function populateSubjectFilters() {
    const filterModule = document.getElementById('filterModule');
    const filterSubject = document.getElementById('filterSubject');

    if (modulesEnabled) {
        populateModuleSelect(filterModule, currentCourseFilter, 'All modules', currentModuleFilter);
    }

    populateSubjectSelect(filterSubject, {
        courseId: currentCourseFilter,
        moduleId: currentModuleFilter,
        placeholder: 'All subjects',
        selectedSubjectId: currentSubjectFilter,
    });
}

function populateExamModuleSelect(courseId, selectedModuleId) {
    const examModule = document.getElementById('examModule');
    if (!modulesEnabled) return Promise.resolve();
    populateModuleSelect(examModule, courseId, 'Select module...', selectedModuleId);
    return Promise.resolve();
}

function populateExamSubjectSelect(courseId, moduleId, selectedSubjectId) {
    populateSubjectSelect(document.getElementById('examSubject'), {
        courseId,
        moduleId: modulesEnabled ? moduleId : '',
        placeholder: 'Select subject...',
        selectedSubjectId,
    });
}

function loadExams() {
    const params = ['action=list'];
    if (currentCourseFilter) params.push(`course_id=${encodeURIComponent(currentCourseFilter)}`);
    if (currentModuleFilter) params.push(`module_id=${encodeURIComponent(currentModuleFilter)}`);
    if (currentSubjectFilter) params.push(`subject_id=${encodeURIComponent(currentSubjectFilter)}`);
    const qs = '?' + params.join('&');

    return axios.get(`${EXAMS_API}${qs}`)
        .then(res => {
            if (res.data.success) {
                exams = res.data.data || [];
                renderExams();
            }
            return exams;
        })
        .catch(err => {
            showAlert('Error loading exams', 'error');
            throw err;
        });
}

function filterExamsForDisplay() {
    return exams.filter(e => {
        if (String(e.Is_Archived) === '1') {
            return false;
        }
        if (currentStatusFilter) {
            return e.Status === currentStatusFilter;
        }
        return true;
    });
}

function buildExamActionMenu(exam) {
    const isArchived = String(exam.Is_Archived) === '1';
    const items = [];

    if (isCcmdExamMode()) {
        if (!isArchived && EXAM_UI_CONFIG.canEditQuestions) {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="edit-questions" data-exam-id="${exam.Exam_ID}"><i class="bi bi-list-check me-2"></i>Edit questions</button></li>`);
        }
        if (EXAM_UI_CONFIG.canViewParticipants) {
            const participantLabel = formatParticipantCountLabel(exam);
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-participants" data-exam-id="${exam.Exam_ID}"><i class="bi bi-people me-2"></i>View participants${participantLabel ? ` (${participantLabel})` : ''}</button></li>`);
        }
        if (EXAM_UI_CONFIG.canViewResults) {
            const finished = parseInt(exam.Finished_Count, 10) || 0;
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-responses" data-exam-id="${exam.Exam_ID}"><i class="bi bi-bar-chart-line me-2"></i>View results${finished ? ` (${finished})` : ''}</button></li>`);
        }
        return items.join('');
    }

    if (!isArchived) {
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="edit" data-exam-id="${exam.Exam_ID}"><i class="bi bi-pencil me-2"></i>Edit exam</button></li>`);
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="copy-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-clipboard me-2"></i>Copy exam code</button></li>`);
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="reset-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-arrow-clockwise me-2"></i>Reset exam code</button></li>`);
        items.push('<li><hr class="dropdown-divider"></li>');

        const reviewOn = String(exam.Allow_Response_Review) === '1';
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="toggle-review" data-exam-id="${exam.Exam_ID}"><i class="bi ${reviewOn ? 'bi-check-circle-fill text-success' : 'bi-circle'} me-2"></i>Allow response review</button></li>`);

        const participantLabel = formatParticipantCountLabel(exam);
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="manage-participants" data-exam-id="${exam.Exam_ID}"><i class="bi bi-people me-2"></i>Manage participants${participantLabel ? ` (${participantLabel})` : ''}</button></li>`);

        const finished = parseInt(exam.Finished_Count, 10) || 0;
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-responses" data-exam-id="${exam.Exam_ID}"><i class="bi bi-bar-chart-line me-2"></i>View responses${finished ? ` (${finished})` : ''}</button></li>`);
        items.push('<li><hr class="dropdown-divider"></li>');

        if (exam.Status === 'Draft') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="publish" data-exam-id="${exam.Exam_ID}"><i class="bi bi-send me-2"></i>Publish exam</button></li>`);
        }
        if (exam.Status === 'Published') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="close" data-exam-id="${exam.Exam_ID}"><i class="bi bi-lock me-2"></i>Close exam</button></li>`);
        }
        if (exam.Status === 'Closed') {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="reopen" data-exam-id="${exam.Exam_ID}"><i class="bi bi-unlock me-2"></i>Reopen exam</button></li>`);
        }

        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="archive" data-exam-id="${exam.Exam_ID}"><i class="bi bi-archive me-2"></i>Archive</button></li>`);
    } else {
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="restore" data-exam-id="${exam.Exam_ID}"><i class="bi bi-arrow-counterclockwise me-2"></i>Restore</button></li>`);
        const finishedArchived = parseInt(exam.Finished_Count, 10) || 0;
        items.push(`<li><button class="dropdown-item" type="button" data-exam-action="view-responses" data-exam-id="${exam.Exam_ID}"><i class="bi bi-bar-chart-line me-2"></i>View responses${finishedArchived ? ` (${finishedArchived})` : ''}</button></li>`);
        if (exam.Exam_Code) {
            items.push(`<li><button class="dropdown-item" type="button" data-exam-action="copy-code" data-exam-id="${exam.Exam_ID}"><i class="bi bi-clipboard me-2"></i>Copy exam code</button></li>`);
        }
    }

    return items.join('');
}

function formatExamCurriculum(exam) {
    const parts = [exam.Course_Name, exam.Module_Name, exam.Subject_Name].filter(Boolean);
    return parts.map(part => escapeHtml(part)).join(', ');
}

function renderExamQuestionsAndCode(exam) {
    const questionPart = `<span><strong>${exam.Question_Count || 0}</strong> question${(exam.Question_Count || 0) === 1 ? '' : 's'}</span>`;

    if (!exam.Exam_Code) {
        return questionPart;
    }

    const resetBtn = String(exam.Is_Archived) !== '1'
        ? `<button type="button" class="btn btn-sm btn-outline-secondary py-0" data-exam-action="reset-code" data-exam-id="${exam.Exam_ID}" title="Reset exam code">
                <i class="bi bi-arrow-clockwise"></i>
           </button>`
        : '';

    return `
        ${questionPart}
        <span class="text-muted">•</span>
        <span class="text-muted small">Exam code:</span>
        <code class="fw-bold text-primary exam-code-value">${escapeHtml(exam.Exam_Code)}</code>
        <button type="button" class="btn btn-sm btn-outline-primary py-0" data-exam-action="copy-code" data-exam-id="${exam.Exam_ID}" title="Copy exam code">
            <i class="bi bi-clipboard"></i>
        </button>
        ${resetBtn}
    `;
}

function renderExamAssignmentBadges(exam) {
    const assignments = exam.Assignments || [];
    const participantCount = parseInt(exam?.Participant_Count, 10) || 0;
    if (!assignments.length) {
        if (participantCount > 0) {
            return '<span class="badge bg-warning text-dark">Sections missing — re-open exam and save assignments</span>';
        }
        return '<span class="text-muted small">No batches or sections assigned</span>';
    }
    return assignments.map(a =>
        `<span class="badge bg-light text-dark border me-1 mb-1">${escapeHtml(a.label)}</span>`
    ).join('');
}

function formatParticipantCountLabel(exam) {
    const selected = parseInt(exam?.Participant_Count, 10) || 0;
    const eligible = parseInt(exam?.Eligible_Participant_Count, 10) || 0;
    if (!eligible && !selected) return '';
    return `${selected}/${eligible || selected}`;
}

function renderParticipantBadge(exam) {
    const selected = parseInt(exam?.Participant_Count, 10) || 0;
    const eligible = parseInt(exam?.Eligible_Participant_Count, 10) || 0;
    if (!eligible && !selected) return '';
    const label = eligible > 0 ? `${selected}/${eligible} participants` : `${selected} participant${selected === 1 ? '' : 's'}`;
    const tone = selected > 0 ? 'bg-light text-dark border' : 'bg-warning text-dark';
    return `<span class="badge ${tone}">${escapeHtml(label)}</span>`;
}

function updateEditParticipantSummary(examMeta = null) {
    const summary = document.getElementById('examParticipantSummary');
    const button = document.getElementById('manageParticipantsFromEditBtn');
    const examId = document.getElementById('examId')?.value || '';
    const hasSections = selectedExamAssignments.length > 0;

    if (button) {
        button.disabled = !hasSections;
    }

    if (!summary) return;

    if (!hasSections) {
        summary.textContent = 'Participants: assign sections first';
        return;
    }

    if (!examId) {
        summary.textContent = 'Participants: click Manage to save exam and select examinees';
        return;
    }

    if (examMeta) {
        summary.textContent = `Participants: ${formatParticipantCountLabel(examMeta) || '0/0'} selected`;
        return;
    }

    const cached = examId ? exams.find(e => String(e.Exam_ID) === String(examId)) : null;
    if (cached) {
        summary.textContent = `Participants: ${formatParticipantCountLabel(cached) || '0/0'} selected`;
    } else {
        summary.textContent = 'Participants: click Manage to save exam and select examinees';
    }
}

function collectExamFormData() {
    commitPendingExamAssignment();

    return {
        examId: document.getElementById('examId')?.value || '',
        payload: {
            subject_id: document.getElementById('examSubject').value,
            title: document.getElementById('examTitle').value,
            description: document.getElementById('examDescription').value,
            schedule_date: document.getElementById('examSchedule').value || null,
            deadline: document.getElementById('examDeadline').value || null,
            duration: document.getElementById('examDuration').value || null,
            passing_score: document.getElementById('examPassing').value || null,
            status: document.getElementById('examStatus').value,
            is_randomized: document.getElementById('examRandomized').checked ? 1 : 0
        },
        sectionIds: getSelectedExamSectionIds()
    };
}

function buildExamRecordFromForm(examId) {
    const courseSelect = document.getElementById('examCourse');
    const moduleSelect = document.getElementById('examModule');
    const subjectSelect = document.getElementById('examSubject');
    const moduleOption = moduleSelect?.selectedOptions?.[0];
    const moduleName = moduleOption && moduleOption.value ? moduleOption.text : '';

    return {
        Exam_ID: parseInt(examId, 10),
        Subject_ID: subjectSelect?.value || '',
        Title: document.getElementById('examTitle')?.value?.trim() || '',
        Course_ID: courseSelect?.value || '',
        Course_Name: courseSelect?.selectedOptions?.[0]?.text || '',
        Module_Name: moduleName,
        Subject_Name: subjectSelect?.selectedOptions?.[0]?.text || '',
        Status: document.getElementById('examStatus')?.value || 'Draft',
        Question_Count: selectedQuestionIds.length,
        Batch_Count: selectedExamAssignments.length,
        Participant_Count: 0,
        Eligible_Participant_Count: 0,
        Assignments: selectedExamAssignments.map(item => ({
            label: `${item.batch_name} • ${item.section_name}`,
            batch_name: item.batch_name,
            section_name: item.section_name,
        })),
    };
}

function upsertExamInList(examRecord) {
    if (!examRecord?.Exam_ID) return;
    const idx = exams.findIndex(e => String(e.Exam_ID) === String(examRecord.Exam_ID));
    if (idx >= 0) {
        exams[idx] = { ...exams[idx], ...examRecord };
    } else {
        exams.unshift(examRecord);
    }
}

function resolveExamForParticipants(examId, examHint = null) {
    if (examHint?.Exam_ID) {
        return examHint;
    }

    const cached = exams.find(e => String(e.Exam_ID) === String(examId));
    if (cached) {
        return cached;
    }

    const formExamId = document.getElementById('examId')?.value;
    if (formExamId && String(formExamId) === String(examId)) {
        return buildExamRecordFromForm(examId);
    }

    return null;
}

async function saveExamDraftForParticipantAccess() {
    const form = document.getElementById('examForm');
    if (!form?.checkValidity()) {
        form?.reportValidity();
        throw new Error('Complete the required exam fields first');
    }

    const { examId, payload, sectionIds } = collectExamFormData();
    if (examId) {
        const parsedExamId = parseInt(examId, 10);
        if (sectionIds.length > 0 && shouldSyncBatchesOnSave('update', parsedExamId)) {
            await axios.post(EXAM_BATCHES_API, {
                action: 'assign',
                exam_id: parsedExamId,
                section_ids: sectionIds
            });
            await axios.post(EXAM_PARTICIPANTS_API, {
                action: 'sync',
                exam_id: parsedExamId
            });
            preservedSectionIds = [...sectionIds];
            assignmentsDirty = false;
        }
        return {
            examId: parsedExamId,
            exam: resolveExamForParticipants(parsedExamId)
        };
    }

    if (!sectionIds.length) {
        throw new Error('Assign at least one section before managing participants');
    }

    const draftPayload = {
        ...payload,
        status: 'Draft',
        action: 'add'
    };

    const validationErrors = validateExamForm(draftPayload, '', sectionIds);
    if (validationErrors.length > 0) {
        throw new Error(validationErrors[0]);
    }

    const submitBtn = document.getElementById('examSubmitBtn');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving...';
    }

    try {
        const res = await axios.post(EXAMS_API, draftPayload);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to save exam');
        }

        const savedExamId = parseInt(res.data.exam_id, 10);
        const promises = [
            axios.post(EXAM_BATCHES_API, {
                action: 'assign',
                exam_id: savedExamId,
                section_ids: sectionIds
            }).then(() => axios.post(EXAM_PARTICIPANTS_API, {
                action: 'sync',
                exam_id: savedExamId
            }))
        ];

        if (selectedQuestionIds.length > 0) {
            promises.push(axios.post(EXAM_QUESTIONS_API, {
                action: 'assign',
                exam_id: savedExamId,
                question_ids: selectedQuestionIds
            }));
            examQuestionsLoaded = true;
        }

        await Promise.all(promises);

        const examStub = buildExamRecordFromForm(savedExamId);
        if (res.data.exam_code) {
            examStub.Exam_Code = res.data.exam_code;
        }
        upsertExamInList(examStub);

        document.getElementById('examId').value = String(savedExamId);
        document.getElementById('examStatus').value = 'Draft';
        document.getElementById('examModalLabel').textContent = 'Edit Exam';
        if (submitBtn) submitBtn.textContent = 'Save Changes';
        currentExamId = savedExamId;
        examAssignmentsLoaded = true;

        try {
            await loadExams();
        } catch (err) {
            // Keep going with the locally upserted exam record if the refresh fails.
        }

        if (!exams.some(e => String(e.Exam_ID) === String(savedExamId))) {
            upsertExamInList(examStub);
        }

        updateEditParticipantSummary(
            exams.find(e => String(e.Exam_ID) === String(savedExamId)) || examStub
        );

        return {
            examId: savedExamId,
            exam: resolveExamForParticipants(savedExamId, examStub)
        };
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

async function handleManageParticipantsFromEdit() {
    if (!selectedExamAssignments.length) {
        showAlert('Assign at least one section before managing participants', 'error');
        return;
    }

    try {
        const { examId, exam } = await saveExamDraftForParticipantAccess();
        await openManageParticipants(examId, exam);
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Unable to open participant manager', 'error');
    }
}

function ensureParticipantsModal() {
    if (!participantsModal) {
        const el = document.getElementById('participantsModal');
        if (el) participantsModal = new bootstrap.Modal(el);
    }
    return participantsModal;
}

async function openManageParticipants(examId, examHint = null, viewOnly = false) {
    const exam = resolveExamForParticipants(examId, examHint);
    if (!exam) {
        showAlert('Exam not found', 'error');
        return;
    }

    upsertExamInList(exam);
    setParticipantsModalMode(viewOnly);

    participantsExamId = parseInt(examId, 10);
    participantSearchTerm = '';
    participantSectionFilter = '';
    const searchInput = document.getElementById('participantSearchInput');
    if (searchInput) searchInput.value = '';
    const sectionFilter = document.getElementById('participantSectionFilter');
    if (sectionFilter) {
        sectionFilter.value = '';
        sectionFilter.style.display = 'none';
    }

    document.getElementById('participantsModalSubtitle').textContent =
        `${exam.Title} • ${formatExamCurriculum(exam)}`;

    document.getElementById('participantsLoading').style.display = 'block';
    document.getElementById('participantsContent').style.display = 'none';
    if (document.getElementById('saveParticipantsBtn')) {
        document.getElementById('saveParticipantsBtn').disabled = participantsViewOnly;
    }

    ensureParticipantsModal()?.show();
    await loadParticipantsModalData();
}

async function loadParticipantsModalData() {
    if (!participantsExamId) return;

    try {
        const res = await axios.get(`${EXAM_PARTICIPANTS_API}?action=list&exam_id=${participantsExamId}`);
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to load participants');
        }

        const data = res.data.data || {};
        participantGroups = data.groups || [];
        participantSelectedIds = new Set((data.selected_ids || []).map(id => parseInt(id, 10)));

        document.getElementById('participantsLoading').style.display = 'none';
        document.getElementById('participantsContent').style.display = 'block';
        if (document.getElementById('saveParticipantsBtn')) {
            document.getElementById('saveParticipantsBtn').disabled = participantsViewOnly;
        }

        populateParticipantSectionFilter();
        renderParticipantsList();
        updateParticipantSelectionCount();

        const idx = exams.findIndex(e => String(e.Exam_ID) === String(participantsExamId));
        if (idx !== -1) {
            exams[idx].Participant_Count = data.selected_count || 0;
            exams[idx].Eligible_Participant_Count = data.eligible_count || 0;
        }
    } catch (err) {
        document.getElementById('participantsLoading').innerHTML =
            `<div class="alert alert-danger mb-0">${escapeHtml(err.response?.data?.message || err.message || 'Failed to load participants')}</div>`;
    }
}

function getParticipantSectionKey(group) {
    if (!group) return '';
    if (group.section_id) {
        return String(group.section_id);
    }
    return `${group.batch_name || ''}|${group.section_name || ''}`;
}

function populateParticipantSectionFilter() {
    const select = document.getElementById('participantSectionFilter');
    if (!select) return;

    const groups = participantGroups || [];
    if (groups.length < 2) {
        select.style.display = 'none';
        select.innerHTML = '<option value="">All sections</option>';
        participantSectionFilter = '';
        select.value = '';
        return;
    }

    select.style.display = '';
    select.innerHTML = [
        '<option value="">All sections</option>',
        ...groups.map(group => {
            const key = getParticipantSectionKey(group);
            const label = `${group.batch_name || 'Batch'} • ${group.section_name || 'Section'}`;
            return `<option value="${String(key).replace(/"/g, '&quot;')}">${escapeHtml(label)}</option>`;
        })
    ].join('');

    if (participantSectionFilter && groups.some(group => getParticipantSectionKey(group) === participantSectionFilter)) {
        select.value = participantSectionFilter;
    } else {
        participantSectionFilter = '';
        select.value = '';
    }
}

function getFilteredParticipantGroups() {
    let groups = participantGroups;

    if (participantSectionFilter) {
        groups = groups.filter(group => getParticipantSectionKey(group) === participantSectionFilter);
    }

    const term = participantSearchTerm.trim().toLowerCase();
    if (!term) return groups;

    return groups.map(group => ({
        ...group,
        participants: (group.participants || []).filter(p => {
            const name = String(p.fullname || '').toLowerCase();
            const academic = String(p.academic_number || '').toLowerCase();
            return name.includes(term) || academic.includes(term);
        }),
    })).filter(group => (group.participants || []).length > 0);
}

function getVisibleParticipantUsers() {
    const users = [];
    getFilteredParticipantGroups().forEach(group => {
        (group.participants || []).forEach(p => users.push(p));
    });
    return users;
}

function renderParticipantsList() {
    const container = document.getElementById('participantsList');
    const empty = document.getElementById('participantsEmpty');
    const groups = getFilteredParticipantGroups();

    if (!participantGroups.length) {
        container.innerHTML = '';
        if (participantsViewOnly) {
            empty.textContent = 'No participants are selected for this exam.';
        } else if (participantSelectedIds.size > 0) {
            empty.textContent = 'Saved participants could not be matched to the assigned sections. Save the exam with sections assigned, then reopen Manage Participants.';
        } else {
            empty.textContent = 'No examinees are enrolled in the assigned sections. Enroll examinees in Users/Batches, or assign different sections.';
        }
        empty.style.display = 'block';
        return;
    }

    if (participantsViewOnly && participantSelectedIds.size === 0) {
        container.innerHTML = '';
        empty.textContent = 'No participants are selected for this exam.';
        empty.style.display = 'block';
        return;
    }

    if (!groups.length) {
        container.innerHTML = '<p class="text-muted text-center py-3 mb-0">No participants match your search.</p>';
        empty.style.display = 'none';
        return;
    }

    empty.style.display = 'none';
    container.innerHTML = groups.map(group => `
        <div class="border rounded mb-3">
            <div class="px-3 py-2 bg-light border-bottom">
                <strong>${escapeHtml(group.course_name || '')}</strong>
                <div class="small text-muted">${escapeHtml(group.batch_name || '')} • ${escapeHtml(group.section_name || '')}</div>
            </div>
            <div class="list-group list-group-flush">
                ${(group.participants || []).map(p => {
                    const isSelected = participantSelectedIds.has(p.user_id);
                    if (participantsViewOnly) {
                        if (!isSelected) return '';
                        return `
                    <div class="list-group-item d-flex align-items-start gap-2 py-2">
                        <i class="bi bi-check-circle-fill text-success mt-1"></i>
                        <span>
                            <span class="fw-medium">${escapeHtml(p.fullname)}</span>
                            ${p.academic_number ? `<small class="text-muted d-block">${escapeHtml(p.academic_number)}</small>` : ''}
                        </span>
                    </div>`;
                    }
                    return `
                    <label class="list-group-item list-group-item-action d-flex align-items-start gap-2 py-2">
                        <input class="form-check-input mt-1 participant-checkbox" type="checkbox"
                               value="${p.user_id}" ${isSelected ? 'checked' : ''}>
                        <span>
                            <span class="fw-medium">${escapeHtml(p.fullname)}</span>
                            ${p.academic_number ? `<small class="text-muted d-block">${escapeHtml(p.academic_number)}</small>` : ''}
                        </span>
                    </label>`;
                }).join('')}
            </div>
        </div>
    `).join('');

    container.querySelectorAll('.participant-checkbox').forEach(input => {
        input.addEventListener('change', function() {
            const userId = parseInt(this.value, 10);
            if (this.checked) participantSelectedIds.add(userId);
            else participantSelectedIds.delete(userId);
            updateParticipantSelectionCount();
        });
    });
}

function updateParticipantSelectionCount() {
    const eligible = participantGroups.reduce((sum, group) => sum + (group.participants || []).length, 0);
    const selected = participantSelectedIds.size;
    const badge = document.getElementById('participantSelectionCount');
    if (badge) badge.textContent = `${selected} of ${eligible} selected`;
}

function setAllParticipantsSelected(selected) {
    getVisibleParticipantUsers().forEach(p => {
        if (selected) participantSelectedIds.add(p.user_id);
        else participantSelectedIds.delete(p.user_id);
    });
    renderParticipantsList();
    updateParticipantSelectionCount();
}

async function saveParticipants() {
    if (!participantsExamId || participantsViewOnly) return;

    const userIds = Array.from(participantSelectedIds);
    const btn = document.getElementById('saveParticipantsBtn');
    if (btn) btn.disabled = true;

    try {
        const res = await axios.post(EXAM_PARTICIPANTS_API, {
            action: 'save',
            exam_id: participantsExamId,
            user_ids: userIds
        });
        if (!res.data.success) {
            throw new Error(res.data.message || 'Failed to save participants');
        }

        const idx = exams.findIndex(e => String(e.Exam_ID) === String(participantsExamId));
        if (idx !== -1) {
            exams[idx].Participant_Count = res.data.selected_count || userIds.length;
            exams[idx].Eligible_Participant_Count = res.data.eligible_count || exams[idx].Eligible_Participant_Count || 0;
        }

        updateEditParticipantSummary(exams[idx] || null);
        renderExams();
        participantsModal?.hide();
        showAlert(`Participants saved (${res.data.selected_count || userIds.length} selected)`, 'success');
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Failed to save participants', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

function renderExams() {
    const container = document.getElementById('examsContainer');
    if (!container) return;

    const filtered = filterExamsForDisplay();

    if (!filtered.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">No exams found</div>';
        return;
    }

    container.innerHTML = filtered.map(e => `
        <div class="course-card" data-exam-id="${e.Exam_ID}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1">
                    <h5 class="mb-1">${escapeHtml(e.Title)}</h5>
                    <div class="text-muted small mb-2">${formatExamCurriculum(e)}</div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        ${renderExamAssignmentBadges(e)}
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        ${renderExamQuestionsAndCode(e)}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1 ms-2">
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="More actions">
                            <i class="bi bi-three-dots-vertical" style="font-size: 1.2rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            ${buildExamActionMenu(e)}
                        </ul>
                    </div>
                </div>
            </div>

            <div>
                <div class="text-muted small mb-2">DETAILS</div>
                <div class="d-flex gap-2 flex-wrap">
                    ${e.Schedule_Date ? `<span class="badge bg-light text-dark border">${new Date(e.Schedule_Date).toLocaleDateString()}</span>` : ''}
                    ${e.Deadline ? `<span class="badge bg-light text-dark border">Due: ${new Date(e.Deadline).toLocaleDateString()}</span>` : ''}
                    ${e.Duration ? `<span class="badge bg-light text-dark border">${e.Duration} min</span>` : ''}
                    ${e.Passing_Score ? `<span class="badge bg-light text-dark border">${e.Passing_Score}% passing</span>` : ''}
                    ${e.Allow_Response_Review !== undefined ? `<span class="badge ${String(e.Allow_Response_Review) === '1' ? 'bg-info text-white' : 'bg-light text-dark border'}">Review: ${String(e.Allow_Response_Review) === '1' ? 'ON' : 'OFF'}</span>` : ''}
                    ${renderParticipantBadge(e)}
                    <span class="badge ${badgeForStatus(e.Status, e.Is_Archived)}">${escapeHtml(String(e.Is_Archived) === '1' ? 'Archived' : e.Status)}</span>
                </div>
            </div>
        </div>
    `).join('');
}

function copyExamCode(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam || !exam.Exam_Code) {
        showAlert('Exam code is not available', 'error');
        return;
    }

    const code = exam.Exam_Code;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code)
            .then(() => showAlert(`Exam code copied: ${code}`, 'success'))
            .catch(() => fallbackCopyExamCode(code));
    } else {
        fallbackCopyExamCode(code);
    }
}

function fallbackCopyExamCode(code) {
    const temp = document.createElement('textarea');
    temp.value = code;
    document.body.appendChild(temp);
    temp.select();
    try {
        document.execCommand('copy');
        showAlert(`Exam code copied: ${code}`, 'success');
    } catch (err) {
        showAlert('Could not copy exam code', 'error');
    }
    document.body.removeChild(temp);
}

function showExamCodeModal(code, title = 'Exam Code') {
    Swal.fire({
        title,
        html: `
            <p class="text-muted mb-2">Share this code with examinees to start the exam.</p>
            <div class="p-3 rounded" style="background:#f8f9fa;">
                <code style="font-size:1.75rem; font-weight:700; letter-spacing:0.15em;">${escapeHtml(code)}</code>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Copy code',
        showCancelButton: true,
        cancelButtonText: 'Close'
    }).then(result => {
        if (result.isConfirmed) {
            copyExamCodeByValue(code);
        }
    });
}

function copyExamCodeByValue(code) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(code)
            .then(() => showAlert(`Exam code copied: ${code}`, 'success'))
            .catch(() => fallbackCopyExamCode(code));
    } else {
        fallbackCopyExamCode(code);
    }
}

async function resetExamCode(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const result = await Swal.fire({
        title: 'Reset exam code?',
        html: `Generate a new code for <strong>${escapeHtml(exam.Title)}</strong>?<br><small class="text-muted">Students using the old code will no longer be able to start the exam.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Reset code',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'reset_code', exam_id: examId });
        if (res.data.success) {
            const idx = exams.findIndex(e => String(e.Exam_ID) === String(examId));
            if (idx !== -1) {
                exams[idx].Exam_Code = res.data.exam_code;
            }
            renderExams();
            showExamCodeModal(res.data.exam_code, 'New Exam Code');
        } else {
            showAlert(res.data.message || 'Failed to reset exam code', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error resetting exam code', 'error');
    }
}

async function toggleResponseReview(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const currentlyOn = String(exam.Allow_Response_Review) === '1';
    const enabling = !currentlyOn;

    const result = await Swal.fire({
        title: enabling ? 'Enable response review?' : 'Disable response review?',
        html: enabling
            ? `Examinees will be able to view their answers and correct solutions for <strong>${escapeHtml(exam.Title)}</strong> after submission.`
            : `Examinees will only see their score for <strong>${escapeHtml(exam.Title)}</strong>. Question breakdown and correct answers will be hidden.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: enabling ? 'Enable review' : 'Disable review',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'toggle_response_review', exam_id: examId });
        if (res.data.success) {
            const idx = exams.findIndex(e => String(e.Exam_ID) === String(examId));
            if (idx !== -1) {
                exams[idx].Allow_Response_Review = res.data.allow_response_review;
            }
            renderExams();
            showAlert(res.data.message, 'success');
        } else {
            showAlert(res.data.message || 'Failed to update response review setting', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error updating response review setting', 'error');
    }
}

async function archiveExam(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const result = await Swal.fire({
        title: 'Archive exam?',
        text: `"${exam.Title}" will be hidden from active exam management.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'archive', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam archived successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to archive exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error archiving exam', 'error');
    }
}

async function restoreExam(examId) {
    try {
        const res = await axios.post(EXAMS_API, { action: 'restore', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam restored successfully', 'success');
            currentStatusFilter = '';
            const statusFilter = document.getElementById('filterExamStatus');
            if (statusFilter) statusFilter.value = '';
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to restore exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error restoring exam', 'error');
    }
}

async function closeExam(examId) {
    const result = await Swal.fire({
        title: 'Close exam?',
        text: 'Students will no longer be able to start this exam.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Close exam',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, { action: 'close', exam_id: examId });
        if (res.data.success) {
            showAlert('Exam closed successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to close exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error closing exam', 'error');
    }
}

async function publishExamQuick(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) return;

    const coreErrors = validateExamCoreFields({
        deadline: exam.Deadline,
        duration: exam.Duration,
        passing_score: exam.Passing_Score,
        schedule_date: exam.Schedule_Date
    });
    if (coreErrors.length > 0) {
        showAlert(`${coreErrors[0]}. Complete the exam details before publishing.`, 'error');
        editExam(examId);
        return;
    }

    if ((exam.Question_Count || 0) === 0 || (exam.Batch_Count || 0) === 0) {
        showAlert('Add questions and assign sections before publishing. Opening edit form...', 'error');
        editExam(examId);
        return;
    }

    const selectedCount = parseInt(exam.Participant_Count, 10) || 0;
    if (selectedCount === 0) {
        showAlert('Select at least one participant before publishing. Opening participant manager...', 'error');
        openManageParticipants(examId);
        return;
    }

    const result = await Swal.fire({
        title: 'Publish exam?',
        text: `"${exam.Title}" will be available to ${selectedCount} selected participant(s).`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Publish',
        cancelButtonText: 'Cancel'
    });

    if (!result.isConfirmed) return;

    try {
        const res = await axios.post(EXAMS_API, {
            action: 'update',
            exam_id: exam.Exam_ID,
            subject_id: exam.Subject_ID,
            title: exam.Title,
            description: exam.Description,
            schedule_date: exam.Schedule_Date,
            deadline: exam.Deadline,
            duration: exam.Duration,
            passing_score: exam.Passing_Score,
            status: 'Published',
            is_randomized: exam.Is_Randomized
        });
        if (res.data.success) {
            showAlert(res.data.message || 'Exam published successfully', 'success');
            loadExams();
        } else {
            showAlert(res.data.message || 'Failed to publish exam', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || 'Error publishing exam', 'error');
    }
}

function bindExamCardActions() {
    const container = document.getElementById('examsContainer');
    if (!container || container.dataset.actionsBound === 'true') {
        return;
    }

    container.addEventListener('click', function(e) {
        const button = e.target.closest('[data-exam-action]');
        if (!button) return;

        const action = button.dataset.examAction;
        const examId = parseInt(button.dataset.examId, 10);
        if (!examId) return;

        e.preventDefault();

        switch (action) {
            case 'edit':
                editExam(examId);
                break;
            case 'edit-questions':
                openEditQuestions(examId);
                break;
            case 'copy-code':
                copyExamCode(examId);
                break;
            case 'reset-code':
                resetExamCode(examId);
                break;
            case 'toggle-review':
                toggleResponseReview(examId);
                break;
            case 'manage-participants':
                openManageParticipants(examId);
                break;
            case 'view-participants':
                openManageParticipants(examId, null, true);
                break;
            case 'view-responses':
                window.location.href = `${EXAM_UI_CONFIG.responsesPath}?exam_id=${examId}`;
                break;
            case 'archive':
                archiveExam(examId);
                break;
            case 'restore':
                restoreExam(examId);
                break;
            case 'close':
                closeExam(examId);
                break;
            case 'publish':
                publishExamQuick(examId);
                break;
            case 'reopen':
                reopenExam(examId);
                break;
            default:
                break;
        }
    });

    container.dataset.actionsBound = 'true';
}

function manageExamDetails(examId) {
    // This would open a modal or navigate to manage questions and batches
    editExam(examId);
}

async function openEditQuestions(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) {
        showAlert('Exam not found', 'error');
        return;
    }

    currentExamId = examId;
    questionPickerContextOverride = {
        course_id: String(exam.Course_ID || ''),
        module_id: String(exam.Module_ID || ''),
        subject_id: String(exam.Subject_ID || ''),
        course_name: exam.Course_Name || '',
        module_name: exam.Module_Name || '',
        subject_name: exam.Subject_Name || '',
        subject_code: exam.Subject_Code || '',
    };
    questionPickerSubjectId = exam.Subject_ID;

    try {
        const questions = await loadExamQuestions(examId);
        examQuestionCanvas = questions.map(normalizeQuestionRecord);
        syncSelectedIdsFromCanvas();
        preservedQuestionIds = [...selectedQuestionIds];
        examQuestionsLoaded = true;
        questionsDirty = false;
        updateQuestionCountDisplay(selectedQuestionIds.length);

        const searchInput = document.getElementById('questionSearchInput');
        if (searchInput) searchInput.value = '';

        updateQuestionPickerContextLabel();
        await reloadQuestionPickerList();

        const modalEl = document.getElementById('questionSelectionModal');
        if (!modalEl) {
            showAlert('Question editor is not available on this page', 'error');
            return;
        }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Failed to load exam questions', 'error');
    }
}

function badgeForStatus(status, isArchived) {
    if (String(isArchived) === '1') return 'bg-dark text-white';
    if (status === 'Published') return 'bg-success text-white';
    if (status === 'Closed') return 'bg-secondary text-white';
    return 'bg-warning text-dark';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// Reopen a closed exam
async function reopenExam(examId) {
    const exam = exams.find(e => e.Exam_ID === examId);
    if (!exam) return;

    const defaultDeadline = (() => {
        const candidate = new Date();
        candidate.setHours(candidate.getHours() + 2);
        if (exam.Deadline) {
            const existing = new Date(exam.Deadline);
            if (existing > candidate) {
                return existing;
            }
        }
        return candidate;
    })();

    const defaultDeadlineValue = `${defaultDeadline.getFullYear()}-${String(defaultDeadline.getMonth() + 1).padStart(2, '0')}-${String(defaultDeadline.getDate()).padStart(2, '0')}T${String(defaultDeadline.getHours()).padStart(2, '0')}:${String(defaultDeadline.getMinutes()).padStart(2, '0')}`;

    const result = await Swal.fire({
        title: 'Reopen Exam',
        html: `
            <p>Reopen "<strong>${escapeHtml(exam.Title)}</strong>" for students?</p>
            <div class="text-start mt-3">
                <label class="form-label">Deadline <span class="text-danger">*</span></label>
                <input type="datetime-local" id="newDeadline" class="form-control" value="${defaultDeadlineValue}">
                <small class="text-muted">Must be in the future. Examinee timers and empty attempts will be reset.</small>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Reopen',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
        preConfirm: () => {
            const deadline = document.getElementById('newDeadline').value;
            if (!deadline) {
                Swal.showValidationMessage('Deadline is required');
                return false;
            }
            if (new Date(deadline) <= new Date()) {
                Swal.showValidationMessage('Deadline must be in the future');
                return false;
            }
            return { deadline };
        }
    });

    if (!result.isConfirmed) return;

    try {
        const response = await axios.post(EXAMS_API, {
            action: 'reopen',
            exam_id: examId,
            deadline: result.value.deadline
        });

        if (response.data.success) {
            const stats = response.data.session_stats || {};
            Swal.fire({
                icon: 'success',
                title: 'Exam Reopened',
                text: response.data.message || 'The exam is now available to students again',
                footer: stats.active_reset || stats.void_removed
                    ? `${stats.active_reset || 0} in-progress reset, ${stats.void_removed || 0} empty attempt(s) cleared`
                    : '',
                timer: 2500,
                showConfirmButton: false
            });
            loadExams();
        }
    } catch (error) {
        console.error('Error reopening exam:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.response?.data?.message || 'Failed to reopen exam'
        });
    }
}

// Load questions from question bank (optional server-side filters)
function loadQuestions(filters = {}) {
    let url = `${QUESTION_BANK_API}?action=list`;
    if (filters.course_id) url += `&course_id=${encodeURIComponent(filters.course_id)}`;
    if (filters.subject_id) url += `&subject_id=${encodeURIComponent(filters.subject_id)}`;
    if (filters.search) url += `&search=${encodeURIComponent(filters.search)}`;

    return axios.get(url)
        .then(res => {
            if (res.data.success) {
                allQuestions = res.data.questions || [];
                return allQuestions;
            }
            allQuestions = [];
            return [];
        })
        .catch(err => {
            console.error('Error loading questions:', err);
            allQuestions = [];
            return [];
        });
}

let questionPickerSubjectId = null;

function getExamQuestionPickerContext() {
    if (questionPickerContextOverride) {
        return questionPickerContextOverride;
    }

    const courseId = document.getElementById('examCourse')?.value || '';
    const moduleId = document.getElementById('examModule')?.value || '';
    const subjectId = document.getElementById('examSubject')?.value || '';
    const course = courses.find(c => String(c.Course_ID) === String(courseId));
    const module = (modulesByCourse[courseId] || []).find(m => String(m.Module_ID) === String(moduleId));
    const subjectList = modulesEnabled
        ? (subjectsByModule[moduleId] || [])
        : (subjectsByCourse[courseId] || []);
    const subject = subjectList.find(s => String(s.Subject_ID) === String(subjectId));

    return {
        course_id: courseId,
        module_id: moduleId,
        subject_id: subjectId,
        course_name: course?.Course_Name || '',
        module_name: module?.Module_Name || '',
        subject_name: subject?.Subject_Name || '',
        subject_code: subject?.Subject_Code || '',
    };
}

function updateQuestionPickerContextLabel() {
    const label = document.getElementById('questionPickerContextLabel');
    if (!label) return;

    const ctx = getExamQuestionPickerContext();
    if (!ctx.course_id || !ctx.subject_id || (modulesEnabled && !ctx.module_id)) {
        label.textContent = '—';
        return;
    }

    const subjectLabel = ctx.subject_name || '—';
    const modulePart = ctx.module_name ? `${ctx.module_name} → ` : '';
    label.textContent = `${ctx.course_name} → ${modulePart}${subjectLabel}`;
}

function resetExamEditState() {
    examEditHydrating = false;
    originalEditSubjectId = null;
    originalEditCourseId = null;
    originalEditModuleId = null;
    preservedQuestionIds = [];
    preservedSectionIds = [];
    questionsDirty = false;
    assignmentsDirty = false;
}

function setCurriculumSnapshots() {
    const course = document.getElementById('examCourse');
    const module = document.getElementById('examModule');
    const subject = document.getElementById('examSubject');
    if (course) course.dataset.snapshot = course.value || '';
    if (module) module.dataset.snapshot = module.value || '';
    if (subject) subject.dataset.snapshot = subject.value || '';
}

function idsEqualAsSets(a, b) {
    const left = (a || []).map(String);
    const right = new Set((b || []).map(String));
    if (left.length !== right.size) return false;
    return left.every(id => right.has(id));
}

function subjectChangedOnSave(payload, examId) {
    return !!examId && originalEditSubjectId !== null &&
        String(payload.subject_id) !== String(originalEditSubjectId);
}

function shouldSyncQuestionsOnSave(action, examId, payload) {
    if (action === 'add') {
        return selectedQuestionIds.length > 0;
    }
    if (!examId) {
        return false;
    }
    if (subjectChangedOnSave(payload, examId) || questionsDirty) {
        return true;
    }
    return !idsEqualAsSets(selectedQuestionIds, preservedQuestionIds);
}

function shouldSyncBatchesOnSave(action, examId) {
    if (action === 'add') {
        return getSelectedExamSectionIds().length > 0;
    }
    if (!examId) {
        return false;
    }
    if (assignmentsDirty) {
        return true;
    }
    return !idsEqualAsSets(getSelectedExamSectionIds(), preservedSectionIds);
}

async function confirmSubjectChangeWithQuestions() {
    if (selectedQuestionIds.length === 0) {
        return true;
    }

    const result = await Swal.fire({
        title: 'Change subject?',
        html: '<p>Assigned questions belong to the current subject. Changing the subject will <strong>clear</strong> them so you can pick questions for the new subject.</p>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Change subject',
        cancelButtonText: 'Keep current subject'
    });
    return result.isConfirmed;
}

async function confirmCourseChangeInEdit() {
    const hasQuestions = selectedQuestionIds.length > 0;
    const hasAssignments = selectedExamAssignments.length > 0;
    if (!hasQuestions && !hasAssignments) {
        return true;
    }

    let detail = 'Assigned sections will be cleared.';
    if (hasQuestions && hasAssignments) {
        detail = 'Assigned questions and sections will be cleared.';
    } else if (hasQuestions) {
        detail = 'Assigned questions will be cleared.';
    }

    const result = await Swal.fire({
        title: 'Change course?',
        html: `<p>Changing the course will reset exam placement. ${detail}</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Change course',
        cancelButtonText: 'Keep current course'
    });
    return result.isConfirmed;
}

function applySubjectChangeClearQuestions(newSubjectId) {
    clearExamQuestionCanvas();
    updateQuestionCountDisplay(0);
    questionsDirty = true;
    examQuestionsLoaded = true;
    questionPickerSubjectId = newSubjectId || null;
}

function applyCourseChangeClearAll() {
    clearExamQuestionCanvas();
    selectedExamAssignments = [];
    updateQuestionCountDisplay(0);
    renderExamAssignmentList();
    questionsDirty = true;
    assignmentsDirty = true;
    examQuestionsLoaded = true;
    examAssignmentsLoaded = true;
    questionPickerSubjectId = null;
}

function clearQuestionSelectionForAddMode(previousSubjectId, newSubjectId) {
    if (selectedQuestionIds.length === 0) {
        questionPickerSubjectId = newSubjectId || null;
        return;
    }

    if (previousSubjectId && newSubjectId && String(previousSubjectId) !== String(newSubjectId)) {
        clearExamQuestionCanvas();
        updateQuestionCountDisplay(0);
        examQuestionsLoaded = false;
        showAlert('Subject changed — question selection cleared', 'warning');
    }
    questionPickerSubjectId = newSubjectId || null;
}

function clearQuestionSelectionOnCourseChangeForAdd() {
    if (selectedQuestionIds.length > 0) {
        clearExamQuestionCanvas();
        updateQuestionCountDisplay(0);
        examQuestionsLoaded = false;
        showAlert('Course changed — question selection cleared', 'warning');
    }
    questionPickerSubjectId = null;
}

function getQuestionPickerFilters() {
    const ctx = getExamQuestionPickerContext();
    return {
        course_id: ctx.course_id,
        subject_id: ctx.subject_id,
        search: document.getElementById('questionSearchInput')?.value.trim() || ''
    };
}

function updateQuestionPickerSummary(count) {
    const summary = document.getElementById('questionPickerSummary');
    if (!summary) return;
    summary.textContent = `${count} question${count === 1 ? '' : 's'} available in this subject`;
}

function reloadQuestionPickerList() {
    return loadQuestions(getQuestionPickerFilters()).then(questions => {
        currentPickerQuestions = questions || [];
        updateQuestionPickerSummary(currentPickerQuestions.length);
        rebuildCanvasFromSelectedIds();
        renderExamQuestionCanvas();
        renderQuestionBankList(currentPickerQuestions);
    });
}

function normalizeQuestionRecord(q) {
    const choices = q.choices || q.answers || [];
    const normalizedChoices = choices.map(c => ({
        Choice_ID: c.Choice_ID,
        Choice_Text: c.Choice_Text || '',
        Is_Correct: c.Is_Correct
    }));
    return {
        Question_ID: q.Question_ID,
        Question_Text: q.Question_Text || '',
        choices: normalizedChoices,
        correctIndex: q.correctIndex ?? getCorrectIndexFromChoices(normalizedChoices),
        exam_usage_count: q.exam_usage_count,
        isDraft: !!q.isDraft,
        draftId: q.draftId || null,
        isEditing: !!q.isEditing,
        bankDirty: !!q.bankDirty,
        createdInThisSession: !!q.createdInThisSession,
        isPublished: q.isPublished !== undefined ? !!q.isPublished : (q.Is_Published !== undefined ? String(q.Is_Published) === '1' : true)
    };
}

function getCorrectIndexFromChoices(choices) {
    const index = (choices || []).findIndex(choice => isChoiceCorrect(choice));
    return index >= 0 ? index : 0;
}

function getQuestionEditKey(q) {
    return q.isDraft ? q.draftId : `q_${q.Question_ID}`;
}

function isChoiceCorrect(choice) {
    return String(choice.Is_Correct) === '1' || choice.Is_Correct === 1 || choice.Is_Correct === true;
}

function syncSelectedIdsFromCanvas() {
    selectedQuestionIds = examQuestionCanvas
        .filter(q => !q.isDraft && q.Question_ID)
        .map(q => q.Question_ID);
    updateQuestionSelectionCount();
}

function createEmptyDraftChoices(correctIndex = 0) {
    return Array.from({ length: QUESTION_CHOICE_COUNT }, (_, index) => ({
        Choice_Text: '',
        Is_Correct: index === correctIndex ? 1 : 0
    }));
}

function createDraftQuestion() {
    draftQuestionCounter += 1;
    return {
        isDraft: true,
        draftId: `draft_${Date.now()}_${draftQuestionCounter}`,
        Question_ID: null,
        Question_Text: '',
        correctIndex: 0,
        choices: createEmptyDraftChoices(0)
    };
}

function addNewQuestionToCanvas() {
    const ctx = getExamQuestionPickerContext();
    if (!ctx.subject_id) {
        showAlert('Select a subject before creating a new question', 'error');
        return;
    }

    examQuestionCanvas.push(createDraftQuestion());
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();

    const container = document.getElementById('examQuestionCanvas');
    if (container) {
        container.scrollTop = container.scrollHeight;
        const lastDraft = container.querySelector('.exam-question-card-draft:last-of-type .draft-question-text');
        if (lastDraft) {
            lastDraft.focus();
        }
    }
}

function appendImportRowsAsExamDrafts(rows) {
    (rows || []).forEach(row => {
        const correct = String(row.Correct_Answer || '').trim().toUpperCase();
        const correctIndex = Math.max(0, ['A', 'B', 'C', 'D', 'E'].indexOf(correct));
        const choices = ['A', 'B', 'C', 'D', 'E'].map((letter, index) => ({
            Choice_Text: String(row[`Option_${letter}`] || '').trim(),
            Is_Correct: index === correctIndex ? 1 : 0,
        }));

        draftQuestionCounter += 1;
        examQuestionCanvas.push(normalizeQuestionRecord({
            isDraft: true,
            draftId: `import_${Date.now()}_${draftQuestionCounter}`,
            Question_Text: String(row.Question || '').trim(),
            choices,
            correctIndex,
            isPublished: false,
        }));
    });

    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
    updateQuestionSelectionCount();
}

function appendImportedExamQuestionsFromServer(questions) {
    (questions || []).forEach(q => {
        examQuestionCanvas.push(normalizeQuestionRecord({
            Question_ID: q.Question_ID,
            Question_Text: q.Question_Text,
            choices: q.choices || [],
            createdInThisSession: true,
            isPublished: false,
        }));
    });

    syncSelectedIdsFromCanvas();
    preservedQuestionIds = [...selectedQuestionIds];
    examQuestionsLoaded = true;
    questionsDirty = false;
    updateQuestionCountDisplay(selectedQuestionIds.length);
    renderExamQuestionCanvas();
}

function collectEditableDataFromDom() {
    document.querySelectorAll('.exam-question-card-draft, .exam-question-card-editing').forEach(card => {
        const draftId = card.dataset.draftId;
        const questionId = card.dataset.questionId ? parseInt(card.dataset.questionId, 10) : null;
        const question = examQuestionCanvas.find(q =>
            (draftId && q.isDraft && q.draftId === draftId) ||
            (questionId && !q.isDraft && q.Question_ID === questionId)
        );
        if (!question) {
            return;
        }

        const editKey = getQuestionEditKey(question);
        question.Question_Text = card.querySelector('.draft-question-text')?.value.trim() || '';
        const choiceInputs = card.querySelectorAll('.draft-choice-input');
        const correctIndex = parseInt(
            card.querySelector(`input[name="correct_${editKey}"]:checked`)?.value ?? '0',
            10
        );
        question.correctIndex = correctIndex;
        question.choices = Array.from(choiceInputs).map((input, index) => ({
            Choice_Text: input.value.trim(),
            Is_Correct: index === correctIndex ? 1 : 0
        }));

        if (!question.isDraft && question.isEditing) {
            question.bankDirty = true;
        }
    });
}

function validateEditableQuestion(question, position) {
    if (!question.Question_Text) {
        return { ok: false, message: `Question ${position}: enter the question text` };
    }

    if (!question.choices || question.choices.length !== QUESTION_CHOICE_COUNT) {
        return { ok: false, message: `Question ${position}: all ${QUESTION_CHOICE_COUNT} answer options are required` };
    }

    const emptyChoice = question.choices.find(choice => !choice.Choice_Text);
    if (emptyChoice) {
        return { ok: false, message: `Question ${position}: fill in all answer options (A through E)` };
    }

    const hasCorrect = question.choices.some((choice, index) => index === question.correctIndex);
    if (!hasCorrect) {
        return { ok: false, message: `Question ${position}: select the correct answer` };
    }

    return { ok: true };
}

async function persistDraftQuestions() {
    const ctx = getExamQuestionPickerContext();
    const subjectId = ctx.subject_id;
    if (!subjectId) {
        throw new Error('Subject is required to save new questions');
    }

    collectEditableDataFromDom();

    for (let index = 0; index < examQuestionCanvas.length; index += 1) {
        const question = examQuestionCanvas[index];
        if (!question.isDraft) {
            continue;
        }

        const validation = validateEditableQuestion(question, index + 1);
        if (!validation.ok) {
            throw new Error(validation.message);
        }

        const response = await axios.post(`${QUESTION_BANK_API}?action=add_question`, {
            subject_id: subjectId,
            question_text: question.Question_Text,
            question_type: 'Multiple Choice',
            points: 1,
            is_published: 0,
            answers: question.choices.map((choice, choiceIndex) => ({
                answer_text: choice.Choice_Text,
                is_correct: choiceIndex === question.correctIndex
            }))
        });

        if (!response.data.success) {
            throw new Error(response.data.message || `Failed to save question ${index + 1}`);
        }

        const savedQuestion = normalizeQuestionRecord({
            Question_ID: response.data.question_id,
            Question_Text: question.Question_Text,
            choices: question.choices.map((choice, choiceIndex) => ({
                Choice_Text: choice.Choice_Text,
                Is_Correct: choiceIndex === question.correctIndex ? 1 : 0
            })),
            createdInThisSession: true,
            isPublished: false
        });
        examQuestionCanvas[index] = savedQuestion;
    }

    syncSelectedIdsFromCanvas();
}

async function persistEditedBankQuestions() {
    const ctx = getExamQuestionPickerContext();
    const subjectId = ctx.subject_id;
    if (!subjectId) {
        throw new Error('Subject is required to update questions');
    }

    collectEditableDataFromDom();

    for (let index = 0; index < examQuestionCanvas.length; index += 1) {
        const question = examQuestionCanvas[index];
        if (question.isDraft || !question.Question_ID) {
            continue;
        }
        if (!question.isEditing && !question.bankDirty) {
            continue;
        }

        const validation = validateEditableQuestion(question, index + 1);
        if (!validation.ok) {
            throw new Error(validation.message);
        }

        const response = await axios.post(
            `${QUESTION_BANK_API}?action=update_question&question_id=${question.Question_ID}`,
            {
                subject_id: subjectId,
                question_text: question.Question_Text,
                points: 1,
                answers: question.choices.map((choice, choiceIndex) => ({
                    answer_text: choice.Choice_Text,
                    is_correct: choiceIndex === question.correctIndex
                }))
            }
        );

        if (!response.data.success) {
            throw new Error(response.data.message || `Failed to update question ${index + 1}`);
        }

        examQuestionCanvas[index] = normalizeQuestionRecord({
            ...question,
            isEditing: false,
            bankDirty: false,
            choices: question.choices.map((choice, choiceIndex) => ({
                Choice_Text: choice.Choice_Text,
                Is_Correct: choiceIndex === question.correctIndex ? 1 : 0
            }))
        });

        const poolIndex = allQuestions.findIndex(q => q.Question_ID === question.Question_ID);
        const poolRecord = {
            Question_ID: question.Question_ID,
            Question_Text: question.Question_Text,
            answers: examQuestionCanvas[index].choices
        };
        if (poolIndex >= 0) {
            allQuestions[poolIndex] = poolRecord;
        } else {
            allQuestions.push(poolRecord);
        }
    }
}

async function persistAllQuestionChanges() {
    await persistDraftQuestions();
    await persistEditedBankQuestions();
}

function clearExamQuestionCanvas() {
    examQuestionCanvas = [];
    selectedQuestionIds = [];
    updateQuestionSelectionCount();
}

function rebuildCanvasFromSelectedIds() {
    const poolMap = new Map();
    [...currentPickerQuestions, ...allQuestions].forEach(q => {
        poolMap.set(q.Question_ID, q);
    });

    const rebuilt = [];
    const seenIds = new Set();

    examQuestionCanvas.forEach(q => {
        if (q.isDraft || q.isEditing || q.bankDirty) {
            rebuilt.push(q);
            if (!q.isDraft && q.Question_ID) {
                seenIds.add(q.Question_ID);
            }
            return;
        }

        const src = poolMap.get(q.Question_ID) || q;
        rebuilt.push(normalizeQuestionRecord(src));
        seenIds.add(q.Question_ID);
    });

    selectedQuestionIds.forEach(id => {
        if (!seenIds.has(id)) {
            const src = poolMap.get(id);
            if (src) {
                rebuilt.push(normalizeQuestionRecord(src));
                seenIds.add(id);
            }
        }
    });

    examQuestionCanvas = rebuilt;
    syncSelectedIdsFromCanvas();
}

function addQuestionToCanvas(questionId, sourceRecord, opts = {}) {
    const id = parseInt(questionId, 10);
    if (!id || examQuestionCanvas.some(q => q.Question_ID === id)) {
        return;
    }

    let src = sourceRecord;
    if (!src) {
        src = currentPickerQuestions.find(q => q.Question_ID === id) ||
            allQuestions.find(q => q.Question_ID === id);
    }
    if (!src) {
        return;
    }

    examQuestionCanvas.push(normalizeQuestionRecord(src));
    syncSelectedIdsFromCanvas();
    if (currentExamId && !opts.silent) {
        questionsDirty = true;
    }
}

function toggleQuestionEdit(questionId) {
    collectEditableDataFromDom();

    const question = examQuestionCanvas.find(q => q.Question_ID === questionId && !q.isDraft);
    if (!question) {
        return;
    }

    if (question.isEditing) {
        return;
    }

    question.isEditing = true;
    question.bankDirty = false;
    question._editSnapshot = JSON.stringify({
        Question_Text: question.Question_Text,
        choices: question.choices,
        correctIndex: question.correctIndex
    });

    if (currentExamId) {
        questionsDirty = true;
    }

    renderExamQuestionCanvas();

    const card = document.querySelector(`.exam-question-card-editing[data-question-id="${questionId}"] .draft-question-text`);
    if (card) {
        card.focus();
    }
}

function cancelQuestionEdit(questionId) {
    collectEditableDataFromDom();

    const index = examQuestionCanvas.findIndex(q => q.Question_ID === questionId && !q.isDraft);
    if (index < 0) {
        return;
    }

    const question = examQuestionCanvas[index];
    if (question._editSnapshot) {
        try {
            const snapshot = JSON.parse(question._editSnapshot);
            question.Question_Text = snapshot.Question_Text;
            question.choices = snapshot.choices;
            question.correctIndex = snapshot.correctIndex;
        } catch (err) {
            const pool = currentPickerQuestions.find(q => q.Question_ID === questionId) ||
                allQuestions.find(q => q.Question_ID === questionId);
            if (pool) {
                examQuestionCanvas[index] = normalizeQuestionRecord(pool);
                renderExamQuestionCanvas();
                return;
            }
        }
    }

    question.isEditing = false;
    question.bankDirty = false;
    delete question._editSnapshot;
    renderExamQuestionCanvas();
}

function removeQuestionFromCanvas(questionId, draftId = null) {
    if (draftId) {
        examQuestionCanvas = examQuestionCanvas.filter(q => q.draftId !== draftId);
    } else {
        const id = parseInt(questionId, 10);
        examQuestionCanvas = examQuestionCanvas.filter(q => q.Question_ID !== id);
    }
    syncSelectedIdsFromCanvas();
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
    renderQuestionBankList(currentPickerQuestions);
}

function renderDragHandle(index) {
    return `<div class="drag-handle" draggable="true" data-canvas-index="${index}" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>`;
}

function renderCanvasDropEnd() {
    return `<div class="exam-canvas-drop-end" data-drop-end="true">Drop here to move to the end</div>`;
}

function moveCanvasItem(fromIndex, toIndex) {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 ||
        fromIndex >= examQuestionCanvas.length || toIndex >= examQuestionCanvas.length) {
        return;
    }

    if (document.getElementById('examQuestionCanvas')?.querySelector('.exam-question-card-draft, .exam-question-card-editing')) {
        collectEditableDataFromDom();
    }

    const item = examQuestionCanvas.splice(fromIndex, 1)[0];
    examQuestionCanvas.splice(toIndex, 0, item);
    syncSelectedIdsFromCanvas();
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
}

function moveCanvasItemToEnd(fromIndex) {
    if (fromIndex < 0 || fromIndex >= examQuestionCanvas.length - 1) {
        return;
    }

    if (document.getElementById('examQuestionCanvas')?.querySelector('.exam-question-card-draft, .exam-question-card-editing')) {
        collectEditableDataFromDom();
    }

    const item = examQuestionCanvas.splice(fromIndex, 1)[0];
    examQuestionCanvas.push(item);
    syncSelectedIdsFromCanvas();
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
}

function renderChoicesHtml(choices) {
    if (!choices || choices.length === 0) {
        return '<small class="text-muted">No choices available</small>';
    }

    return choices.map((choice, index) => {
        const letter = String.fromCharCode(65 + index);
        const correct = isChoiceCorrect(choice);
        const cls = correct ? 'exam-q-choice correct' : 'exam-q-choice';
        const icon = correct
            ? '<i class="bi bi-check-circle-fill text-success me-1"></i>'
            : `<span class="me-2 text-muted">${letter}.</span>`;
        return `<div class="${cls}">${icon}${escapeHtml(choice.Choice_Text)}</div>`;
    }).join('');
}

function renderEditableChoiceRows(q) {
    const editKey = getQuestionEditKey(q);
    const choices = q.choices || createEmptyDraftChoices(q.correctIndex || 0);
    return choices.map((choice, index) => {
        const letter = String.fromCharCode(65 + index);
        const checked = index === (q.correctIndex || 0) ? 'checked' : '';
        return `
            <div class="draft-choice-row">
                <input class="form-check-input" type="radio" name="correct_${editKey}" value="${index}" ${checked}>
                <span class="draft-choice-label">${letter}</span>
                <input type="text" class="form-control draft-choice-input" placeholder="Option ${letter}" value="${escapeHtml(choice.Choice_Text)}">
            </div>`;
    }).join('');
}

function renderQuestionStatusBadge(q, index) {
    if (q.isDraft) {
        return `<span class="badge bg-primary">Q${index + 1} · New</span>`;
    }
    if (q.isPublished === false) {
        return `<span class="badge bg-secondary">Q${index + 1} · Exam draft</span>`;
    }
    if (q.isEditing) {
        return `<span class="badge bg-warning text-dark">Q${index + 1} · Editing</span>`;
    }
    return `<span class="badge bg-light text-dark border">Q${index + 1}</span>`;
}

function renderEditableQuestionForm(q, options = {}) {
    const footerNote = options.footerNote || '';
    const showCancel = !!options.showCancel;
    const cancelQuestionId = options.cancelQuestionId || '';

    return `
        <label class="form-label small text-muted mb-1">Question text</label>
        <textarea class="form-control draft-question-text mb-3" rows="3"
                  placeholder="Enter your question...">${escapeHtml(q.Question_Text)}</textarea>
        <label class="form-label small text-muted mb-1">Answer options (select the correct one)</label>
        <div class="draft-choice-list">${renderEditableChoiceRows(q)}</div>
        ${footerNote ? `<div class="exam-q-bank-notice mt-2">${footerNote}</div>` : ''}
        ${showCancel ? `
            <div class="text-end mt-2">
                <button type="button" class="btn btn-sm btn-link text-muted btn-cancel-edit-q"
                        data-question-id="${cancelQuestionId}">Cancel editing</button>
            </div>` : ''}`;
}

function renderDraftQuestionCard(q, index) {
    return `
        <div class="exam-question-card exam-question-card-draft"
             data-canvas-index="${index}" data-draft-id="${q.draftId}">
            <div class="d-flex gap-2">
                ${renderDragHandle(index)}
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary">Q${index + 1} · New</span>
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-canvas-q"
                                data-draft-id="${q.draftId}" title="Discard unsaved question">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    ${renderEditableQuestionForm(q, {
                        footerNote: '<small class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Exam draft only — added to the question bank when this exam is published.</small>'
                    })}
                </div>
            </div>
        </div>`;
}

function renderEditingBankQuestionCard(q, index) {
    return `
        <div class="exam-question-card exam-question-card-editing"
             data-canvas-index="${index}" data-question-id="${q.Question_ID}">
            <div class="d-flex gap-2">
                ${renderDragHandle(index)}
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        ${renderQuestionStatusBadge(q, index)}
                        <div class="exam-q-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-canvas-q"
                                    data-question-id="${q.Question_ID}" title="Remove from exam only">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    ${renderEditableQuestionForm(q, {
                        footerNote: q.isPublished === false
                            ? '<small class="text-muted"><i class="bi bi-hourglass-split me-1"></i>This exam draft will be added to the question bank when the exam is published.</small>'
                            : '<small class="text-warning-emphasis"><i class="bi bi-info-circle me-1"></i>Changes update the question bank for all exams using this question.</small>',
                        showCancel: true,
                        cancelQuestionId: q.Question_ID
                    })}
                </div>
            </div>
        </div>`;
}

function renderReadOnlyBankQuestionCard(q, index) {
    return `
        <div class="exam-question-card" data-canvas-index="${index}" data-question-id="${q.Question_ID}">
            <div class="d-flex gap-2">
                ${renderDragHandle(index)}
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        ${renderQuestionStatusBadge(q, index)}
                        <div class="exam-q-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary btn-edit-canvas-q"
                                    data-question-id="${q.Question_ID}" title="Edit question in bank">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-canvas-q"
                                    data-question-id="${q.Question_ID}" title="Remove from exam only">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    <div class="fw-medium mb-2">${escapeHtml(q.Question_Text)}</div>
                    <div class="exam-q-choices">${renderChoicesHtml(q.choices)}</div>
                </div>
            </div>
        </div>`;
}

function renderExamQuestionCard(q, index) {
    if (q.isDraft) {
        return renderDraftQuestionCard(q, index);
    }
    if (q.isEditing) {
        return renderEditingBankQuestionCard(q, index);
    }
    return renderReadOnlyBankQuestionCard(q, index);
}

function renderExamQuestionCanvas() {
    const container = document.getElementById('examQuestionCanvas');
    if (!container) {
        return;
    }

    if (container.querySelector('.exam-question-card-draft, .exam-question-card-editing')) {
        collectEditableDataFromDom();
    }

    if (!examQuestionCanvas.length) {
        container.innerHTML = `
            <div class="exam-question-canvas-empty">
                <i class="bi bi-ui-checks fs-2 d-block mb-2"></i>
                Add questions from the bank or click <strong>New question</strong> to create one
            </div>`;
        return;
    }

    container.innerHTML = examQuestionCanvas.map((q, index) => renderExamQuestionCard(q, index)).join('')
        + (examQuestionCanvas.length > 1 ? renderCanvasDropEnd() : '');
    bindCanvasDragEvents();
}

function bindCanvasDragEvents() {
    const container = document.getElementById('examQuestionCanvas');
    if (!container) {
        return;
    }

    if (!container.dataset.actionsBound) {
        container.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit-canvas-q');
            if (editBtn) {
                toggleQuestionEdit(parseInt(editBtn.dataset.questionId, 10));
                return;
            }

            const cancelBtn = e.target.closest('.btn-cancel-edit-q');
            if (cancelBtn) {
                cancelQuestionEdit(parseInt(cancelBtn.dataset.questionId, 10));
                return;
            }

            const removeBtn = e.target.closest('.btn-remove-canvas-q');
            if (removeBtn) {
                if (removeBtn.dataset.draftId) {
                    removeQuestionFromCanvas(null, removeBtn.dataset.draftId);
                } else {
                    removeQuestionFromCanvas(parseInt(removeBtn.dataset.questionId, 10));
                }
            }
        });
        container.dataset.actionsBound = 'true';
    }

    if (container.dataset.dragBound) {
        return;
    }

    container.addEventListener('dragstart', function(e) {
        const handle = e.target.closest('.drag-handle');
        if (!handle) {
            return;
        }

        const card = handle.closest('.exam-question-card');
        if (!card) {
            return;
        }

        dragCanvasIndex = parseInt(handle.dataset.canvasIndex, 10);
        if (Number.isNaN(dragCanvasIndex)) {
            e.preventDefault();
            return;
        }

        card.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', String(dragCanvasIndex));
    });

    container.addEventListener('dragend', function(e) {
        const handle = e.target.closest('.drag-handle');
        const card = handle?.closest('.exam-question-card');
        if (card) {
            card.classList.remove('dragging');
        }
        document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
            .forEach(el => el.classList.remove('drag-over'));
        dragCanvasIndex = null;
        if (window.DragAutoScroll) {
            DragAutoScroll.stop();
        }
    });

    container.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (window.DragAutoScroll) {
            DragAutoScroll.update(e.clientY, container);
        }

        const dropEnd = e.target.closest('.exam-canvas-drop-end');
        if (dropEnd) {
            document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                .forEach(el => el.classList.remove('drag-over'));
            dropEnd.classList.add('drag-over');
            return;
        }

        const card = e.target.closest('.exam-question-card');
        if (!card) {
            return;
        }
        document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
            .forEach(el => el.classList.remove('drag-over'));
        card.classList.add('drag-over');
    });

    container.addEventListener('drop', function(e) {
        e.preventDefault();
        if (window.DragAutoScroll) {
            DragAutoScroll.stop();
        }

        if (dragCanvasIndex === null) {
            return;
        }

        const dropEnd = e.target.closest('.exam-canvas-drop-end');
        if (dropEnd) {
            moveCanvasItemToEnd(dragCanvasIndex);
            document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                .forEach(el => el.classList.remove('drag-over'));
            return;
        }

        const card = e.target.closest('.exam-question-card');
        if (card) {
            const toIndex = parseInt(card.dataset.canvasIndex, 10);
            moveCanvasItem(dragCanvasIndex, toIndex);
        }
        document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
            .forEach(el => el.classList.remove('drag-over'));
    });

    container.dataset.dragBound = 'true';
}

function renderQuestionBankItem(q) {
    const isOnExam = selectedQuestionIds.includes(q.Question_ID);
    const usageCount = parseInt(q.exam_usage_count, 10) || 0;
    const usageLabel = usageCount > 0
        ? `Used in ${usageCount} exam${usageCount === 1 ? '' : 's'}`
        : 'Not used yet';

    return `
        <div class="question-bank-item border rounded p-2 mb-2">
            <div class="small fw-medium mb-1">${escapeHtml(q.Question_Text)}</div>
            <div class="d-flex justify-content-between align-items-center gap-2">
                <small class="text-muted">${usageLabel}</small>
                ${isOnExam
                    ? '<span class="badge bg-success">Added</span>'
                    : `<button type="button" class="btn btn-sm btn-outline-primary btn-add-to-canvas" data-question-id="${q.Question_ID}">Add</button>`}
            </div>
        </div>`;
}

function renderQuestionBankList(questions) {
    const container = document.getElementById('questionBankListContainer');
    if (!container) {
        return;
    }

    if (!questions || questions.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No questions found for this subject</div>';
        updateQuestionSelectionCount();
        return;
    }

    const ctx = getExamQuestionPickerContext();
    const useFlatList = !!ctx.subject_id;

    if (useFlatList) {
        container.innerHTML = questions.map(q => renderQuestionBankItem(q)).join('');
    } else {
        const grouped = {};
        questions.forEach(q => {
            const groupKey = `${q.Course_Name || 'Unknown Course'} → ${q.Subject_Name || 'Unknown Subject'}`;
            if (!grouped[groupKey]) {
                grouped[groupKey] = [];
            }
            grouped[groupKey].push(q);
        });

        let html = '';
        Object.keys(grouped).sort().forEach(groupKey => {
            html += `<div class="mb-3"><h6 class="text-primary mb-2 small">${escapeHtml(groupKey)}</h6>`;
            html += grouped[groupKey].map(q => renderQuestionBankItem(q)).join('');
            html += '</div>';
        });
        container.innerHTML = html;
    }

    if (!container.dataset.actionsBound) {
        container.addEventListener('click', function(e) {
            const addBtn = e.target.closest('.btn-add-to-canvas');
            if (!addBtn) {
                return;
            }
            const questionId = parseInt(addBtn.dataset.questionId, 10);
            const source = currentPickerQuestions.find(q => q.Question_ID === questionId);
            addQuestionToCanvas(questionId, source);
            renderExamQuestionCanvas();
            renderQuestionBankList(currentPickerQuestions);
        });
        container.dataset.actionsBound = 'true';
    }

    updateQuestionSelectionCount();
}

function selectAllVisibleQuestions() {
    currentPickerQuestions.forEach(q => {
        addQuestionToCanvas(q.Question_ID, q, { silent: true });
    });
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
    renderQuestionBankList(currentPickerQuestions);
}

function clearAllQuestionSelection() {
    clearExamQuestionCanvas();
    if (currentExamId) {
        questionsDirty = true;
    }
    renderExamQuestionCanvas();
    renderQuestionBankList(currentPickerQuestions);
}

function loadExamCourseTree() {
    return axios.get(`${COURSES_API}?action=tree`)
        .then(res => {
            if (res.data.success) {
                examCourseTree = res.data.data || [];
            } else {
                examCourseTree = [];
            }
            return examCourseTree;
        })
        .catch(err => {
            console.error('Error loading course tree:', err);
            examCourseTree = [];
            return [];
        });
}

function ensureExamCourseTree() {
    if (examCourseTree.length > 0) {
        return Promise.resolve(examCourseTree);
    }
    return loadExamCourseTree();
}

function resetAssignExamSelects() {
    const batchSelect = document.getElementById('assignExamBatchSelect');
    const sectionSelect = document.getElementById('assignExamSectionSelect');
    if (batchSelect) {
        batchSelect.innerHTML = '<option value="">Select exam course first</option>';
        batchSelect.disabled = true;
    }
    if (sectionSelect) {
        sectionSelect.innerHTML = '<option value="">Select a batch first</option>';
        sectionSelect.disabled = true;
    }
}

function syncAssignExamFromExamCourse() {
    const examCourseId = document.getElementById('examCourse')?.value || '';
    resetAssignExamSelects();
    if (!examCourseId) {
        return Promise.resolve();
    }
    return ensureExamCourseTree().then(() => {
        populateAssignExamBatches(examCourseId);
    });
}

function populateAssignExamCourses() {
    return syncAssignExamFromExamCourse();
}

function populateAssignExamBatches(courseId) {
    const batchSelect = document.getElementById('assignExamBatchSelect');
    const sectionSelect = document.getElementById('assignExamSectionSelect');
    if (!batchSelect || !sectionSelect) return;

    batchSelect.innerHTML = '<option value="">Select a batch</option>';
    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    sectionSelect.disabled = true;

    if (!courseId) {
        batchSelect.disabled = true;
        return;
    }

    const course = examCourseTree.find(c => String(c.Course_ID) === String(courseId));
    const batches = course && course.batches ? course.batches : [];

    batches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.Batch_ID;
        option.textContent = batch.Batch_Name;
        batchSelect.appendChild(option);
    });

    if (batches.length === 0) {
        batchSelect.innerHTML = '<option value="">No batches for this course</option>';
    }

    batchSelect.disabled = batches.length === 0;
}

function getAssignedSectionIds() {
    return new Set(selectedExamAssignments.map(a => String(a.section_id)));
}

function refreshAssignExamSectionSelect() {
    const batchId = document.getElementById('assignExamBatchSelect')?.value || '';
    populateAssignExamSections(batchId);
}

function populateAssignExamSections(batchId) {
    const sectionSelect = document.getElementById('assignExamSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">Select a section</option>';

    if (!batchId) {
        sectionSelect.disabled = true;
        return;
    }

    let sections = [];
    examCourseTree.forEach(course => {
        if (course.batches) {
            const batch = course.batches.find(b => String(b.Batch_ID) === String(batchId));
            if (batch && batch.sections) {
                sections = batch.sections;
            }
        }
    });

    const assignedIds = getAssignedSectionIds();
    const available = sections.filter(s => !assignedIds.has(String(s.Section_ID)));

    if (sections.length === 0) {
        sectionSelect.innerHTML = '<option value="">No sections for this batch</option>';
        sectionSelect.disabled = true;
        return;
    }

    if (available.length === 0) {
        sectionSelect.innerHTML = '<option value="">All sections in this batch are assigned</option>';
        sectionSelect.disabled = true;
        return;
    }

    available.forEach(section => {
        const option = document.createElement('option');
        option.value = section.Section_ID;
        option.textContent = section.Section_Name;
        sectionSelect.appendChild(option);
    });

    sectionSelect.disabled = false;
}

function renderExamAssignmentList() {
    const container = document.getElementById('examAssignmentList');
    const emptyEl = document.getElementById('examAssignmentEmpty');
    const countDisplay = document.getElementById('batchCount');
    if (!container) return;

    if (!selectedExamAssignments.length) {
        container.innerHTML = '<p class="text-muted text-center small mb-0 py-2" id="examAssignmentEmpty">No sections assigned</p>';
        if (countDisplay) countDisplay.textContent = '0';
        refreshAssignExamSectionSelect();
        return;
    }

    container.innerHTML = selectedExamAssignments.map((item, index) => `
        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
            <div class="small">
                <strong>${escapeHtml(item.course_name)}</strong><br>
                Batch: ${escapeHtml(item.batch_name)} • Section: ${escapeHtml(item.section_name)}
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-assignment-index="${index}" title="Remove">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('');

    container.querySelectorAll('[data-assignment-index]').forEach(btn => {
        btn.addEventListener('click', function() {
            const idx = parseInt(this.dataset.assignmentIndex, 10);
            selectedExamAssignments.splice(idx, 1);
            examAssignmentsLoaded = true;
            assignmentsDirty = true;
            renderExamAssignmentList();
        });
    });

    if (countDisplay) countDisplay.textContent = String(selectedExamAssignments.length);
    refreshAssignExamSectionSelect();
    updateEditParticipantSummary();
}

function tryAddExamAssignmentFromSelects(options = {}) {
    const silent = options.silent === true;
    const batchSelect = document.getElementById('assignExamBatchSelect');
    const sectionSelect = document.getElementById('assignExamSectionSelect');

    const courseId = document.getElementById('examCourse')?.value;
    const batchId = batchSelect?.value;
    const sectionId = sectionSelect?.value;

    if (!courseId) {
        if (!silent) showAlert('Please select a course for the exam first', 'error');
        return false;
    }
    if (!batchId || !sectionId) {
        if (!silent) showAlert('Please select a batch and section', 'error');
        return false;
    }

    if (selectedExamAssignments.some(a => String(a.section_id) === String(sectionId))) {
        if (!silent) showAlert('This section is already assigned to the exam', 'error');
        else if (sectionSelect) sectionSelect.value = '';
        return false;
    }

    const course = examCourseTree.find(c => String(c.Course_ID) === String(courseId));
    const batch = course?.batches?.find(b => String(b.Batch_ID) === String(batchId));
    const section = batch?.sections?.find(s => String(s.Section_ID) === String(sectionId));

    selectedExamAssignments.push({
        section_id: parseInt(sectionId, 10),
        course_name: course?.Course_Name || 'Unknown Course',
        batch_name: batch?.Batch_Name || 'Unknown Batch',
        section_name: section?.Section_Name || 'Unknown Section'
    });

    examAssignmentsLoaded = true;
    assignmentsDirty = true;
    renderExamAssignmentList();
    return true;
}

function commitPendingExamAssignment() {
    tryAddExamAssignmentFromSelects({ silent: true });
}

function setExamAssignmentsFromApiRows(rows) {
    const apiRows = rows || [];
    selectedExamAssignments = apiRows
        .filter(row => row.Section_ID)
        .map(row => ({
            section_id: parseInt(row.Section_ID, 10),
            course_name: row.Course_Name || 'Unknown Course',
            batch_name: row.Batch_Name || 'Unknown Batch',
            section_name: row.Section_Name || row.Batch_Name || 'Unknown Section'
        }));

    const seen = new Set();
    selectedExamAssignments = selectedExamAssignments.filter(item => {
        const key = String(item.section_id);
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });

    renderExamAssignmentList();

    // Legacy batch rows without a section cannot be edited in the UI; preserve them on save.
    if (apiRows.length > 0 && selectedExamAssignments.length === 0) {
        return false;
    }
    return true;
}

function getSelectedExamSectionIds() {
    return selectedExamAssignments.map(a => a.section_id);
}

// Render question bank in selection modal
function renderQuestionList(questions) {
    renderQuestionBankList(questions);
}

// Update question selection count
function updateQuestionSelectionCount() {
    const savedCount = examQuestionCanvas.filter(q => !q.isDraft).length;
    const draftCount = examQuestionCanvas.filter(q => q.isDraft).length;
    const editingCount = examQuestionCanvas.filter(q => q.isEditing).length;
    const countDisplay = document.getElementById('selectedQuestionCount');
    const shown = currentPickerQuestions.length;
    if (countDisplay) {
        let label = `${savedCount} on exam`;
        if (draftCount > 0) {
            label += ` (+${draftCount} new)`;
        }
        if (editingCount > 0) {
            label += ` (${editingCount} editing)`;
        }
        if (shown > 0) {
            label += ` · ${shown} in bank`;
        }
        countDisplay.textContent = label;
    }
}

// Filter questions in picker modal (server-side reload)
function filterQuestionsBySubject() {
    reloadQuestionPickerList();
}

function searchQuestions() {
    reloadQuestionPickerList();
}

// Render batch checkboxes — removed; use Assign Exam section UI
function renderBatchSelection() {
    renderExamAssignmentList();
}

// Load assigned questions for an exam
function loadExamQuestions(examId) {
    return axios.get(`${EXAM_QUESTIONS_API}?action=get&exam_id=${examId}`)
        .then(res => {
            if (res.data.success) {
                return res.data.data || [];
            }
            return [];
        })
        .catch(err => {
            console.error('Error loading exam questions:', err);
            return [];
        });
}

// Load assigned batches for an exam
function loadExamBatches(examId) {
    return axios.get(`${EXAM_BATCHES_API}?action=get&exam_id=${examId}`)
        .then(res => {
            if (res.data.success) {
                return res.data.data || [];
            }
            return [];
        })
        .catch(err => {
            console.error('Error loading exam batches:', err);
            return [];
        });
}

// Update question count display
function updateQuestionCountDisplay(count) {
    const countDisplay = document.getElementById('questionCount');
    if (countDisplay) {
        countDisplay.textContent = `${count} question${count !== 1 ? 's' : ''} assigned`;
        countDisplay.className = count > 0 ? 'badge bg-success text-white' : 'badge bg-warning text-dark';
    }
}

function validateExamCoreFields(payload) {
    const errors = [];
    if (!payload.deadline) {
        errors.push('Deadline is required');
    }
    const duration = Number(payload.duration);
    if (payload.duration === null || payload.duration === '' || isNaN(duration) || duration <= 0) {
        errors.push('Duration must be greater than 0 minutes');
    }
    const passing = Number(payload.passing_score);
    if (payload.passing_score === null || payload.passing_score === '' || isNaN(passing)) {
        errors.push('Passing score is required');
    } else if (passing < 0 || passing > 100) {
        errors.push('Passing score must be between 0 and 100');
    }
    if (payload.schedule_date && payload.deadline) {
        const schedule = new Date(payload.schedule_date);
        const deadline = new Date(payload.deadline);
        if (!isNaN(schedule.getTime()) && !isNaN(deadline.getTime()) && deadline < schedule) {
            errors.push('Due date must not be earlier than the schedule date');
        }
    }
    return errors;
}

function getEffectivePublishCounts(examId, sectionIds) {
    const existingExam = examId ? exams.find(e => String(e.Exam_ID) === String(examId)) : null;
    let questionCount = selectedQuestionIds.length;
    let batchCount = sectionIds.length;
    if (existingExam) {
        if (questionCount === 0) {
            questionCount = existingExam.Question_Count || 0;
        }
        if (batchCount === 0) {
            batchCount = existingExam.Batch_Count || 0;
        }
    }
    return { questionCount, batchCount };
}

function validateExamForm(payload, examId, sectionIds) {
    const errors = [];
    if (!payload.subject_id) {
        errors.push('Subject is required');
    }
    if (!payload.title || !payload.title.trim()) {
        errors.push('Title is required');
    }
    errors.push(...validateExamCoreFields(payload));
    if (payload.status === 'Published') {
        const { questionCount, batchCount } = getEffectivePublishCounts(examId, sectionIds);
        if (batchCount === 0) {
            errors.push('Assign at least one section before publishing. Select a batch, then choose a section from the dropdown.');
        }
        if (questionCount === 0) {
            errors.push('Assign at least one question before publishing');
        }
        const existingExam = examId ? exams.find(e => String(e.Exam_ID) === String(examId)) : null;
        const participantCount = existingExam ? (parseInt(existingExam.Participant_Count, 10) || 0) : 0;
        if (existingExam && participantCount === 0 && batchCount > 0) {
            errors.push('Select at least one participant before publishing. Use Manage Participants.');
        }
    }
    return errors;
}

// Exam form submit
const examFormEl = document.getElementById('examForm');
if (examFormEl) {
examFormEl.addEventListener('submit', function (e) {
    e.preventDefault();
    commitPendingExamAssignment();

    const form = e.target;
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const examId = document.getElementById('examId').value;
    const payload = {
        subject_id: document.getElementById('examSubject').value,
        title: document.getElementById('examTitle').value,
        description: document.getElementById('examDescription').value,
        schedule_date: document.getElementById('examSchedule').value || null,
        deadline: document.getElementById('examDeadline').value || null,
        duration: document.getElementById('examDuration').value || null,
        passing_score: document.getElementById('examPassing').value || null,
        status: document.getElementById('examStatus').value,
        is_randomized: document.getElementById('examRandomized').checked ? 1 : 0
    };

    const sectionIds = getSelectedExamSectionIds();

    const validationErrors = validateExamForm(payload, examId, sectionIds);
    if (validationErrors.length > 0) {
        showAlert(validationErrors[0], 'error');
        return;
    }

    let action = 'add';
    if (examId) {
        action = 'update';
        payload.exam_id = examId;
    }
    payload.action = action;

    axios.post(EXAMS_API, payload)
        .then(res => {
            if (res.data.success) {
                const savedExamId = examId || res.data.exam_id;
                if (savedExamId) {
                    upsertExamInList({
                        Exam_ID: parseInt(savedExamId, 10),
                        Title: payload.title,
                    });
                }
                
                // Create promises for batch and question assignments
                const promises = [];
                
                // Sync section assignments only when something actually changed
                const shouldSyncBatches = shouldSyncBatchesOnSave(action, savedExamId);
                if (savedExamId && shouldSyncBatches) {
                    promises.push(
                        axios.post(EXAM_BATCHES_API, {
                            action: 'assign',
                            exam_id: savedExamId,
                            section_ids: sectionIds
                        }).then(() => axios.post(EXAM_PARTICIPANTS_API, {
                            action: 'sync',
                            exam_id: savedExamId
                        }))
                    );
                }

                // Sync questions only when selection or subject actually changed
                const shouldSyncQuestions = shouldSyncQuestionsOnSave(action, savedExamId, payload);
                if (savedExamId && shouldSyncQuestions) {
                    promises.push(
                        axios.post(EXAM_QUESTIONS_API, {
                            action: 'assign',
                            exam_id: savedExamId,
                            question_ids: selectedQuestionIds
                        })
                    );
                }
                
                // Wait for all assignments to complete
                if (promises.length > 0) {
                    return Promise.all(promises).then(() => {
                        if (action === 'add' && res.data.exam_code) {
                            showExamCodeModal(res.data.exam_code, 'Exam Created');
                        } else {
                            showAlert(action === 'add' ? 'Exam added successfully' : 'Exam updated successfully', 'success');
                        }
                        bootstrap.Modal.getInstance(document.getElementById('examModal')).hide();
                        document.getElementById('examForm').reset();
                        document.getElementById('examId').value = '';
                        resetExamEditState();
                        loadExams();
                    }).catch(err => {
                        console.error('Error assigning batches/questions:', err);
                        const errorMsg = err.response?.data?.message || 'Failed to assign batches or questions';
                        showAlert(errorMsg, 'error');
                    });
                } else {
                    if (action === 'add' && res.data.exam_code) {
                        showExamCodeModal(res.data.exam_code, 'Exam Created');
                    } else {
                        showAlert(action === 'add' ? 'Exam added successfully' : 'Exam updated successfully', 'success');
                    }
                    bootstrap.Modal.getInstance(document.getElementById('examModal')).hide();
                    document.getElementById('examForm').reset();
                    document.getElementById('examId').value = '';
                    resetExamEditState();
                    loadExams();
                }
            } else {
                showAlert(res.data.message || 'Operation failed', 'error');
            }
        })
        .catch(err => {
            showAlert(err.response?.data?.message || 'Error saving exam', 'error');
        });
});
}

async function ensureExamSubjectForEdit(exam) {
    const courseId = exam.Course_ID;
    if (!courseId) {
        return;
    }

    const examCourse = document.getElementById('examCourse');
    if (examCourse) {
        examCourse.value = courseId;
    }

    if (modulesEnabled) {
        if (!modulesByCourse[courseId] || modulesByCourse[courseId].length === 0) {
            await loadCurriculumData();
        }
        const moduleId = exam.Module_ID || findModuleIdForSubject(exam.Subject_ID, courseId);
        await populateExamModuleSelect(courseId, moduleId);
        populateExamSubjectSelect(courseId, moduleId, exam.Subject_ID);
        return;
    }

    if (!subjectsByCourse[courseId] || subjectsByCourse[courseId].length === 0) {
        const res = await axios.get(`${SUBJECTS_API}?action=list&course_id=${courseId}`);
        if (res.data.success) {
            subjectsByCourse[courseId] = res.data.data || [];
        }
    }

    populateExamSubjectSelect(courseId, '', exam.Subject_ID);

    const examSubject = document.getElementById('examSubject');
    if (examSubject && exam.Subject_ID && !examSubject.value) {
        examSubject.value = exam.Subject_ID;
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

async function editExam(examId) {
    const exam = exams.find(e => String(e.Exam_ID) === String(examId));
    if (!exam) {
        showAlert('Exam not found in list', 'error');
        return;
    }

    const modal = document.getElementById('examModal');
    const submitBtn = document.getElementById('examSubmitBtn');
    modal.dataset.editMode = 'true';

    const loadToken = ++editExamLoadToken;
    resetExamEditState();
    examEditHydrating = true;

    currentExamId = examId;
    examQuestionsLoaded = false;
    examAssignmentsLoaded = false;
    clearExamQuestionCanvas();
    selectedExamAssignments = [];

    document.getElementById('examId').value = exam.Exam_ID;
    document.getElementById('examTitle').value = exam.Title || '';
    document.getElementById('examDescription').value = exam.Description || '';
    document.getElementById('examSchedule').value = toDatetimeLocalInputValue(exam.Schedule_Date);
    document.getElementById('examDeadline').value = toDatetimeLocalInputValue(exam.Deadline);
    syncExamDeadlineMinFromSchedule();
    document.getElementById('examDuration').value = exam.Duration || '';
    document.getElementById('examPassing').value = exam.Passing_Score || '';
    document.getElementById('examStatus').value = exam.Status || 'Draft';
    document.getElementById('examRandomized').checked = !!exam.Is_Randomized;

    document.getElementById('examModalLabel').textContent = 'Edit Exam';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Loading...';
    }

    try {
        await ensureExamSubjectForEdit(exam);

        if (loadToken !== editExamLoadToken) {
            return;
        }

        await ensureExamCourseTree();
        if (exam.Course_ID) {
            populateAssignExamBatches(exam.Course_ID);
        }

        const [questions, batches] = await Promise.all([
            loadExamQuestions(examId),
            loadExamBatches(examId)
        ]);

        if (loadToken !== editExamLoadToken) {
            return;
        }

        examQuestionCanvas = questions.map(normalizeQuestionRecord);
        syncSelectedIdsFromCanvas();
        preservedQuestionIds = [...selectedQuestionIds];

        questionPickerSubjectId = exam.Subject_ID;
        originalEditSubjectId = String(exam.Subject_ID);
        originalEditCourseId = String(exam.Course_ID || '');
        originalEditModuleId = String(exam.Module_ID || findModuleIdForSubject(exam.Subject_ID, exam.Course_ID) || '');

        examQuestionsLoaded = true;
        examAssignmentsLoaded = setExamAssignmentsFromApiRows(batches);
        preservedSectionIds = [...getSelectedExamSectionIds()];
        questionsDirty = false;
        assignmentsDirty = false;

        updateQuestionCountDisplay(questions.length);
        updateEditParticipantSummary(exam);
        setCurriculumSnapshots();

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }

        examEditHydrating = false;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    } catch (err) {
        examEditHydrating = false;
        console.error('Error loading exam for edit:', err);
        delete modal.dataset.editMode;
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }
        showAlert('Failed to load exam details for editing', 'error');
    }
}

// Filters
document.getElementById('filterCourse')?.addEventListener('change', function () {
    currentCourseFilter = this.value || '';
    currentModuleFilter = '';
    currentSubjectFilter = '';
    populateSubjectFilters();
    loadExams();
});

document.getElementById('filterModule')?.addEventListener('change', function () {
    currentModuleFilter = this.value || '';
    currentSubjectFilter = '';
    populateSubjectSelect(document.getElementById('filterSubject'), {
        courseId: currentCourseFilter,
        moduleId: currentModuleFilter,
        placeholder: 'All subjects',
    });
    loadExams();
});

document.getElementById('filterSubject')?.addEventListener('change', function () {
    currentSubjectFilter = this.value || '';
    loadExams();
});

document.getElementById('filterExamStatus')?.addEventListener('change', function () {
    currentStatusFilter = this.value || '';
    renderExams();
});

// When course in exam modal changes, update module and subject selects
document.getElementById('examCourse')?.addEventListener('change', async function () {
    const courseSelect = this;
    const previousCourseId = courseSelect.dataset.snapshot || '';
    const courseId = courseSelect.value;

    if (currentExamId && !examEditHydrating) {
        if (previousCourseId && courseId !== previousCourseId) {
            const confirmed = await confirmCourseChangeInEdit();
            if (!confirmed) {
                courseSelect.value = previousCourseId;
                return;
            }
            applyCourseChangeClearAll();
            showAlert('Course changed — re-select subject, questions, and sections', 'warning');
        }
    } else if (!currentExamId) {
        clearQuestionSelectionOnCourseChangeForAdd();
    }

    if (modulesEnabled) {
        populateExamModuleSelect(courseId);
        populateExamSubjectSelect(courseId, '', '');
    } else if (courseId) {
        if (!subjectsByCourse[courseId] || subjectsByCourse[courseId].length === 0) {
            try {
                const res = await axios.get(`${SUBJECTS_API}?action=list&course_id=${courseId}`);
                if (res.data.success) {
                    subjectsByCourse[courseId] = res.data.data || [];
                    populateExamSubjectSelect(courseId, '', '');
                }
            } catch (err) {
                console.error('Error loading subjects:', err);
                showAlert('Failed to load subjects', 'error');
            }
        } else {
            populateExamSubjectSelect(courseId, '', '');
        }
    } else {
        populateExamSubjectSelect('', '', '');
    }

    setCurriculumSnapshots();
    populateAssignExamCourses();
});

document.getElementById('examModule')?.addEventListener('change', async function () {
    const moduleSelect = this;
    const previousModuleId = moduleSelect.dataset.snapshot || '';
    const courseId = document.getElementById('examCourse')?.value || '';
    const subjectSelect = document.getElementById('examSubject');
    const previousSubjectId = subjectSelect?.dataset.snapshot || '';

    if (currentExamId && !examEditHydrating) {
        populateExamSubjectSelect(courseId, moduleSelect.value, previousSubjectId);
        const newSubjectId = subjectSelect?.value || '';

        if (previousSubjectId && newSubjectId && String(previousSubjectId) !== String(newSubjectId)) {
            const confirmed = await confirmSubjectChangeWithQuestions();
            if (!confirmed) {
                moduleSelect.value = previousModuleId;
                populateExamSubjectSelect(courseId, previousModuleId, previousSubjectId);
                return;
            }
            applySubjectChangeClearQuestions(newSubjectId);
            showAlert('Module changed — re-select questions for the current subject', 'warning');
        } else if (previousSubjectId && !newSubjectId) {
            const confirmed = await confirmSubjectChangeWithQuestions();
            if (!confirmed) {
                moduleSelect.value = previousModuleId;
                populateExamSubjectSelect(courseId, previousModuleId, previousSubjectId);
                return;
            }
            applySubjectChangeClearQuestions('');
        }

        setCurriculumSnapshots();
        return;
    }

    populateExamSubjectSelect(courseId, moduleSelect.value, '');
    setCurriculumSnapshots();
});

document.getElementById('examSubject')?.addEventListener('change', async function () {
    const subjectSelect = this;
    const previousSubjectId = subjectSelect.dataset.snapshot || '';
    const newSubjectId = subjectSelect.value;

    if (examEditHydrating) {
        subjectSelect.dataset.snapshot = newSubjectId;
        questionPickerSubjectId = newSubjectId || null;
        return;
    }

    if (currentExamId && previousSubjectId && newSubjectId && String(previousSubjectId) !== String(newSubjectId)) {
        const confirmed = await confirmSubjectChangeWithQuestions();
        if (!confirmed) {
            subjectSelect.value = previousSubjectId;
            return;
        }
        applySubjectChangeClearQuestions(newSubjectId);
        showAlert('Subject changed — re-select questions for the new subject', 'warning');
    } else if (!currentExamId) {
        clearQuestionSelectionForAddMode(previousSubjectId, newSubjectId);
    }

    subjectSelect.dataset.snapshot = newSubjectId;
    questionPickerSubjectId = newSubjectId || questionPickerSubjectId;
});

// Auto-calculate deadline when schedule date changes
document.getElementById('examSchedule')?.addEventListener('change', function() {
    const scheduleDate = this.value;
    const deadlineInput = document.getElementById('examDeadline');

    syncExamDeadlineMinFromSchedule();
    
    // Only auto-fill if deadline is empty
    if (scheduleDate && deadlineInput && !deadlineInput.value) {
        const schedule = new Date(scheduleDate);
        // Default: 24 hours after schedule date
        schedule.setHours(schedule.getHours() + 24);
        
        // Format for datetime-local input
        const year = schedule.getFullYear();
        const month = String(schedule.getMonth() + 1).padStart(2, '0');
        const day = String(schedule.getDate()).padStart(2, '0');
        const hours = String(schedule.getHours()).padStart(2, '0');
        const minutes = String(schedule.getMinutes()).padStart(2, '0');
        
        deadlineInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
    }
});

// Validate deadline is not before schedule date
document.getElementById('examDeadline')?.addEventListener('change', function() {
    const scheduleDate = document.getElementById('examSchedule').value;
    const deadline = this.value;
    
    if (scheduleDate && deadline) {
        const schedule = new Date(scheduleDate);
        const deadlineDate = new Date(deadline);
        
        if (deadlineDate < schedule) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Deadline',
                text: 'Due date must not be earlier than the schedule date',
                confirmButtonColor: '#2563eb'
            });
            this.value = '';
        }
    }
});

// Modal show: reset to Add mode (only if not in edit mode)
const examModalEl = document.getElementById('examModal');
if (examModalEl) {
examModalEl.addEventListener('show.bs.modal', function (e) {
    const modal = e.target;
    
    // Skip reset if opening for edit
    if (modal.dataset.editMode === 'true') {
        delete modal.dataset.editMode;
        return;
    }
    
    // Reset for add mode
    document.getElementById('examForm').reset();
    document.getElementById('examId').value = '';
    syncExamDeadlineMinFromSchedule();
    document.getElementById('examModalLabel').textContent = 'Add New Exam';
    document.getElementById('examSubmitBtn').textContent = 'Add Exam';
    
    currentExamId = null;
    clearExamQuestionCanvas();
    questionPickerSubjectId = null;
    selectedExamAssignments = [];
    examQuestionsLoaded = false;
    examAssignmentsLoaded = false;
    resetExamEditState();

    updateQuestionCountDisplay(0);
    populateAssignExamCourses();
    renderExamAssignmentList();
});

examModalEl.addEventListener('shown.bs.modal', function() {
    ensureExamCourseTree().then(() => syncAssignExamFromExamCourse());
});
}

document.getElementById('manageQuestionsBtn')?.addEventListener('click', function() {
    const ctx = getExamQuestionPickerContext();
    if (!ctx.course_id || !ctx.subject_id || (modulesEnabled && !ctx.module_id)) {
        showAlert('Select course, module, and subject before managing questions', 'error');
        return;
    }

    const searchInput = document.getElementById('questionSearchInput');
    if (searchInput) searchInput.value = '';

    questionPickerSubjectId = ctx.subject_id;
    updateQuestionPickerContextLabel();

    reloadQuestionPickerList().then(() => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('questionSelectionModal')).show();
    });
});

// Question search input handler
document.getElementById('questionSearchInput')?.addEventListener('input', debounce(function() {
    reloadQuestionPickerList();
}, 400));

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Save Question Selection button handler
document.getElementById('saveQuestionSelectionBtn')?.addEventListener('click', async function() {
    const saveBtn = this;
    saveBtn.disabled = true;

    try {
        await persistAllQuestionChanges();
        syncSelectedIdsFromCanvas();
        const questionIds = [...selectedQuestionIds];

        if (!currentExamId) {
            selectedQuestionIds = questionIds;
            questionPickerSubjectId = getExamQuestionPickerContext().subject_id;
            updateQuestionCountDisplay(questionIds.length);
            await reloadQuestionPickerList();
            bootstrap.Modal.getInstance(document.getElementById('questionSelectionModal')).hide();
            return;
        }

        const response = await axios.post(EXAM_QUESTIONS_API, {
            action: 'assign',
            exam_id: currentExamId,
            question_ids: questionIds
        });

        if (response.data.success) {
            selectedQuestionIds = questionIds;
            preservedQuestionIds = [...questionIds];
            examQuestionsLoaded = true;
            questionsDirty = false;
            updateQuestionCountDisplay(questionIds.length);
            await reloadQuestionPickerList();
            bootstrap.Modal.getInstance(document.getElementById('questionSelectionModal')).hide();
            showAlert('Questions saved. New questions are exam drafts until this exam is published.', 'success');
            loadExams();
        } else {
            showAlert(response.data.message || 'Failed to assign questions', 'error');
        }
    } catch (err) {
        showAlert(err.response?.data?.message || err.message || 'Error saving questions', 'error');
    } finally {
        saveBtn.disabled = false;
    }
});

// Dropdown toggle handler for Masterfiles menu
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const submenu = this.nextElementSibling;
        const isActive = this.classList.toggle('active');
        if (isActive) {
            submenu.classList.add('show');
        } else {
            submenu.classList.remove('show');
        }
    });
});

// Handle submenu link clicks - prevent dropdown from closing
document.querySelectorAll('.submenu a[href]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.stopPropagation();
        const href = this.getAttribute('href');
        window.location.href = href;
    });
});

// Init
applyExamPageConfig();
setParticipantsModalMode(false);
loadCoursesAndSubjects();
bindExamCardActions();
loadExams();
if (EXAM_UI_CONFIG.canEditExamDetails) {
    loadExamCourseTree();
}

document.getElementById('questionSelectionModal')?.addEventListener('hidden.bs.modal', function() {
    if (isCcmdExamMode()) {
        questionPickerContextOverride = null;
    }
});

document.getElementById('assignExamBatchSelect')?.addEventListener('change', function() {
    populateAssignExamSections(this.value);
});

document.getElementById('assignExamSectionSelect')?.addEventListener('change', function() {
    if (this.value) {
        tryAddExamAssignmentFromSelects({ silent: true });
    }
});

document.getElementById('selectAllQuestionsBtn')?.addEventListener('click', selectAllVisibleQuestions);
document.getElementById('clearAllQuestionsBtn')?.addEventListener('click', clearAllQuestionSelection);
document.getElementById('addNewQuestionBtn')?.addEventListener('click', addNewQuestionToCanvas);

function openExamQuestionImportModal() {
    const ctx = getExamQuestionPickerContext();
    if (!ctx.course_id || !ctx.subject_id || (modulesEnabled && !ctx.module_id)) {
        Swal.fire('Select curriculum', 'Select course, module, and subject before importing questions.', 'warning');
        return false;
    }
    const modalEl = document.getElementById('examImportQuestionsModal');
    if (!modalEl) {
        showAlert('Import is not available on this page', 'error');
        return false;
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
    return true;
}

document.getElementById('examImportQuestionsBtn')?.addEventListener('click', openExamQuestionImportModal);

if (typeof QuestionImport !== 'undefined' && document.getElementById('examImportQuestionsModal')) {
    QuestionImport.init({
        mode: 'exam',
        modalId: 'examImportQuestionsModal',
        fileInputId: 'examImportFile',
        importBtnId: 'examImportBtn',
        validationId: 'examImportValidation',
        previewId: 'examImportPreview',
        previewCardsId: 'examImportPreviewCards',
        previewTableWrapId: 'examImportPreviewTableWrap',
        previewTableId: 'examImportPreviewTable',
        downloadTemplateBtnId: 'downloadExamImportTemplateBtn',
        subjectNoteId: 'examImportSubjectNote',
        getSubjectId: () => {
            const ctx = getExamQuestionPickerContext();
            return ctx.subject_id || questionPickerSubjectId || null;
        },
        getExamId: () => currentExamId || null,
        getSubjectLabel: () => {
            const ctx = getExamQuestionPickerContext();
            if (!ctx.subject_name) return '';
            const modulePart = ctx.module_name ? `${ctx.module_name} → ` : '';
            return `${ctx.course_name} → ${modulePart}${ctx.subject_name}`;
        },
        onImportToCanvas: (rows) => {
            appendImportRowsAsExamDrafts(rows);
        },
        onSuccess: async (data) => {
            if (data.questions && data.questions.length) {
                appendImportedExamQuestionsFromServer(data.questions);
            }
            await reloadQuestionPickerList();
            const container = document.getElementById('examQuestionCanvas');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },
    });
}

document.getElementById('manageParticipantsFromEditBtn')?.addEventListener('click', handleManageParticipantsFromEdit);

document.getElementById('participantsModal')?.addEventListener('hidden.bs.modal', () => {
    const examModalOpen = document.getElementById('examModal')?.classList.contains('show');
    if (examModalOpen) {
        document.getElementById('manageParticipantsFromEditBtn')?.focus();
    }
});

document.getElementById('participantSearchInput')?.addEventListener('input', function() {
    participantSearchTerm = this.value || '';
    renderParticipantsList();
});

document.getElementById('participantSectionFilter')?.addEventListener('change', function() {
    participantSectionFilter = this.value || '';
    renderParticipantsList();
});

document.getElementById('participantSelectAllBtn')?.addEventListener('click', () => setAllParticipantsSelected(true));
document.getElementById('participantClearAllBtn')?.addEventListener('click', () => setAllParticipantsSelected(false));
document.getElementById('saveParticipantsBtn')?.addEventListener('click', saveParticipants);

