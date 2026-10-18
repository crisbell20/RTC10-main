const MODULES_API = '../../api/masterfiles/modules.php';
const SUBJECTS_API = '../../api/masterfiles/subjects.php';

let modulesEnabled = true;
let courseTree = [];
let searchQuery = '';
let filterCourseId = '';
let filterModuleId = '';
let treeLoaded = false;
let addModuleModal = null;
let addSubjectModal = null;
let legacySubjects = [];

function initModals() {
    const moduleEl = document.getElementById('addModuleModal');
    const subjectEl = document.getElementById('addSubjectModal');
    if (moduleEl) addModuleModal = bootstrap.Modal.getOrCreateInstance(moduleEl);
    if (subjectEl) addSubjectModal = bootstrap.Modal.getOrCreateInstance(subjectEl);
}

function hideModal(modal) {
    if (modal) modal.hide();
}

function bootstrapAlertClass(type) {
    if (type === 'error' || type === 'danger') return 'danger';
    if (type === 'warning') return 'warning';
    if (type === 'info') return 'info';
    return 'success';
}

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    if (!alertDiv) return;
    alertDiv.innerHTML = `<div class="alert alert-${bootstrapAlertClass(type)} alert-dismissible fade show">
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
}

function buildActionMenu(items) {
    if (typeof window.buildStaffActionMenu === 'function') {
        return window.buildStaffActionMenu(items);
    }
    const menuItems = items.map(item =>
        `<li><button class="dropdown-item" type="button" data-action="${item.action}" ${item.attrs || ''}>${item.label}</button></li>`
    ).join('');
    return `<div class="dropdown staff-action-menu">
        <button type="button" class="btn btn-sm btn-link text-secondary staff-action-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="More actions">
            <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">${menuItems}</ul>
    </div>`;
}

function findCourse(courseId) {
    return courseTree.find(c => String(c.Course_ID) === String(courseId)) || null;
}

function findModule(moduleId, course = null) {
    const courses = course ? [course] : courseTree;
    for (const item of courses) {
        for (const mod of item.modules || []) {
            if (String(mod.Module_ID) === String(moduleId)) {
                return { ...mod, Course_ID: item.Course_ID, Course_Name: item.Course_Name };
            }
        }
    }
    return null;
}

function flattenSubjects() {
    if (!modulesEnabled) {
        return (legacySubjects || []).map(s => ({
            Subject_ID: s.Subject_ID,
            Subject_Name: s.Subject_Name,
            Description: s.Description,
            Module_ID: s.Module_ID || '',
            Module_Name: s.Module_Name || '',
            Course_ID: s.Course_ID || '',
            Course_Name: s.Course_Name || '',
        }));
    }

    const rows = [];
    courseTree.forEach(course => {
        (course.modules || []).forEach(mod => {
            (mod.subjects || []).forEach(subject => {
                rows.push({
                    ...subject,
                    Module_ID: mod.Module_ID,
                    Module_Name: mod.Module_Name,
                    Course_ID: course.Course_ID,
                    Course_Name: course.Course_Name,
                });
            });
        });
    });
    rows.sort((a, b) => {
        const courseCmp = String(a.Course_Name || '').localeCompare(String(b.Course_Name || ''));
        if (courseCmp) return courseCmp;
        const moduleCmp = String(a.Module_Name || '').localeCompare(String(b.Module_Name || ''));
        if (moduleCmp) return moduleCmp;
        return String(a.Subject_Name || '').localeCompare(String(b.Subject_Name || ''));
    });
    return rows;
}

function flattenModules() {
    const rows = [];
    courseTree.forEach(course => {
        (course.modules || []).forEach(mod => {
            rows.push({
                Module_ID: mod.Module_ID,
                Module_Name: mod.Module_Name,
                Description: mod.Description,
                Course_ID: course.Course_ID,
                Course_Name: course.Course_Name,
                subjects: (mod.subjects || []).slice(),
            });
        });
    });
    rows.sort((a, b) => {
        const courseCmp = String(a.Course_Name || '').localeCompare(String(b.Course_Name || ''));
        if (courseCmp) return courseCmp;
        return String(a.Module_Name || '').localeCompare(String(b.Module_Name || ''));
    });
    return rows;
}

function textMatches(query, ...values) {
    return values.some(value => String(value || '').toLowerCase().includes(query));
}

function matchesSearch(row) {
    const query = searchQuery.trim().toLowerCase();
    if (!query) return true;
    return textMatches(query, row.Subject_Name, row.Description, row.Module_Name, row.Course_Name);
}

function moduleSearchMatch(mod) {
    const query = searchQuery.trim().toLowerCase();
    if (!query) {
        return { show: true, subjects: mod.subjects || [] };
    }
    if (textMatches(query, mod.Module_Name, mod.Description, mod.Course_Name)) {
        return { show: true, subjects: mod.subjects || [] };
    }
    const subjects = (mod.subjects || []).filter(subject =>
        textMatches(query, subject.Subject_Name, subject.Description)
    );
    return { show: subjects.length > 0, subjects };
}

function filteredSubjects() {
    return flattenSubjects().filter(row => {
        if (filterCourseId && String(row.Course_ID) !== String(filterCourseId)) return false;
        if (filterModuleId && String(row.Module_ID) !== String(filterModuleId)) return false;
        return matchesSearch(row);
    });
}

function filteredModules() {
    return flattenModules()
        .filter(mod => {
            if (filterCourseId && String(mod.Course_ID) !== String(filterCourseId)) return false;
            if (filterModuleId && String(mod.Module_ID) !== String(filterModuleId)) return false;
            return moduleSearchMatch(mod).show;
        })
        .map(mod => ({
            ...mod,
            displaySubjects: moduleSearchMatch(mod).subjects,
        }));
}

function loadingHtml() {
    return `<div class="curriculum-empty text-muted">
        <i class="bi bi-hourglass-split"></i>
        <div>Loading...</div>
    </div>`;
}

function emptyCard(message, actionLabel, action) {
    const button = actionLabel
        ? `<div class="mt-3"><button type="button" class="btn btn-sm btn-add-user" data-action="${action}">${escapeHtml(actionLabel)}</button></div>`
        : '';
    return `<div class="curriculum-empty">
        <i class="bi bi-journal-text"></i>
        <div>${escapeHtml(message)}</div>
        ${button}
    </div>`;
}

function setLoadError(message) {
    const body = document.getElementById('subjectsContainer');
    if (body) {
        body.innerHTML = `<div class="curriculum-empty text-danger">${escapeHtml(message)}</div>`;
    }
}

function formatCount(n, singular) {
    return `${n} ${singular}${n === 1 ? '' : 's'}`;
}

function renderLegacyCards(body) {
    const rows = filteredSubjects();
    const all = flattenSubjects();
    if (rows.length === 0) {
        body.innerHTML = all.length === 0
            ? emptyCard('No subjects yet', 'Add Subject', 'add-subject')
            : emptyCard('No subjects match your filters');
        return;
    }

    body.innerHTML = rows.map(row => {
        const path = [row.Course_Name, row.Module_Name].filter(Boolean).join(', ');
        return `<div class="curriculum-course-card curriculum-subject-card">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1 pe-2">
                    <h5>${escapeHtml(row.Subject_Name)}</h5>
                    ${path ? `<div class="text-muted small">${escapeHtml(path)}</div>` : ''}
                </div>
                <div class="staff-row-actions">
                    ${buildActionMenu([
                        { action: 'edit-subject', attrs: `data-subject-id="${row.Subject_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-subject', attrs: `data-subject-id="${row.Subject_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
        </div>`;
    }).join('');
}

function renderSubjectRows(subjects) {
    if (!subjects.length) {
        return '<div class="module-empty-subjects">No subjects yet</div>';
    }
    return subjects.map((subject, index) => `
        <div class="module-subject-row">
            <span class="module-subject-num">${index + 1}</span>
            <div class="module-subject-name">${escapeHtml(subject.Subject_Name)}</div>
            <div class="staff-row-actions">
                ${buildActionMenu([
                    { action: 'edit-subject', attrs: `data-subject-id="${subject.Subject_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                    { action: 'archive-subject', attrs: `data-subject-id="${subject.Subject_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                ])}
            </div>
        </div>
    `).join('');
}

function renderModuleCard(mod) {
    const total = (mod.subjects || []).length;
    const courseLine = [
        mod.Course_Name ? `<span class="text-muted">${escapeHtml(mod.Course_Name)}</span>` : '',
        `<span class="curriculum-course-card-meta">${formatCount(total, 'subject')}</span>`,
    ].filter(Boolean).join('');
    return `<div class="curriculum-course-card curriculum-module-card">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <h5 class="mb-0">${escapeHtml(mod.Module_Name)}</h5>
            <div class="staff-row-actions">
                <button type="button" class="btn btn-sm btn-add-user" data-action="add-subject" data-course-id="${mod.Course_ID}" data-module-id="${mod.Module_ID}">
                    <i class="bi bi-plus-lg me-1"></i>Add Subject
                </button>
                ${buildActionMenu([
                    { action: 'edit-module', attrs: `data-module-id="${mod.Module_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                    { action: 'archive-module', attrs: `data-module-id="${mod.Module_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                ])}
            </div>
        </div>
        <div class="module-card-meta">${courseLine}</div>
        <div class="module-subject-list">
            ${renderSubjectRows(mod.displaySubjects || [])}
        </div>
    </div>`;
}

function renderTable() {
    const body = document.getElementById('subjectsContainer');
    if (!body) return;

    if (!modulesEnabled) {
        renderLegacyCards(body);
        return;
    }

    const rows = filteredModules();
    const all = flattenModules();
    if (rows.length === 0) {
        if (all.length === 0) {
            body.innerHTML = emptyCard('No modules yet', 'Add Module', 'add-module');
            return;
        }
        const courseHasNoModules = filterCourseId && !searchQuery.trim() && modulesForCourse(filterCourseId).length === 0;
        if (courseHasNoModules) {
            body.innerHTML = emptyCard('This course has no modules yet', 'Add Module', 'add-module');
            return;
        }
        body.innerHTML = emptyCard('No modules match your filters');
        return;
    }

    body.innerHTML = rows.map(renderModuleCard).join('');
}

function populateCourseOptions(selectEl, selectedId, includeAllLabel) {
    if (!selectEl) return;
    const current = selectedId ?? selectEl.value;
    selectEl.innerHTML = includeAllLabel
        ? `<option value="">${escapeHtml(includeAllLabel)}</option>`
        : '<option value="">Select course...</option>';
    courseTree.forEach(course => {
        selectEl.add(new Option(course.Course_Name, course.Course_ID));
    });
    if (current && [...selectEl.options].some(opt => opt.value === String(current))) {
        selectEl.value = String(current);
    }
}

function modulesForCourse(courseId) {
    if (!courseId) {
        return courseTree.flatMap(course => (course.modules || []).map(mod => ({
            ...mod,
            Course_ID: course.Course_ID,
            Course_Name: course.Course_Name,
        })));
    }
    const course = findCourse(courseId);
    return (course?.modules || []).map(mod => ({
        ...mod,
        Course_ID: course.Course_ID,
        Course_Name: course.Course_Name,
    }));
}

function populateModuleOptions(selectEl, courseId, selectedId, includeAllLabel) {
    if (!selectEl) return;
    const modules = modulesForCourse(courseId);
    selectEl.innerHTML = includeAllLabel
        ? `<option value="">${escapeHtml(includeAllLabel)}</option>`
        : '<option value="">Select module...</option>';
    modules.forEach(mod => {
        const label = courseId ? mod.Module_Name : `${mod.Course_Name} / ${mod.Module_Name}`;
        selectEl.add(new Option(label, mod.Module_ID));
    });
    if (selectedId && [...selectEl.options].some(opt => opt.value === String(selectedId))) {
        selectEl.value = String(selectedId);
    }
}

function syncFilterDropdowns() {
    populateCourseOptions(document.getElementById('filterCourse'), filterCourseId, 'All courses');
    const moduleSelect = document.getElementById('filterModule');
    if (!moduleSelect) return;

    if (!filterCourseId) {
        filterModuleId = '';
        moduleSelect.innerHTML = '<option value="">Select a course first</option>';
        moduleSelect.value = '';
        moduleSelect.disabled = true;
        return;
    }

    populateModuleOptions(moduleSelect, filterCourseId, filterModuleId, 'All modules');
    moduleSelect.disabled = false;
    if (filterModuleId && !modulesForCourse(filterCourseId).some(m => String(m.Module_ID) === String(filterModuleId))) {
        filterModuleId = '';
        moduleSelect.value = '';
    }
}

function applyCourseFilter(courseId, moduleId = '') {
    filterCourseId = courseId || '';
    filterModuleId = moduleId || '';
    syncFilterDropdowns();
    renderTable();
}

function loadCourseTree() {
    const body = document.getElementById('subjectsContainer');
    if (body && !treeLoaded) {
        body.innerHTML = loadingHtml();
    }

    return axios.get(`${MODULES_API}?action=tree`)
        .then(res => {
            if (!res.data.success) {
                modulesEnabled = false;
                return loadSubjectsLegacy();
            }
            modulesEnabled = true;
            treeLoaded = true;
            document.getElementById('migrationWarning')?.classList.add('d-none');
            document.getElementById('addModuleBtn')?.classList.remove('d-none');
            courseTree = res.data.data || [];
            syncFilterDropdowns();
            renderTable();
        })
        .catch(err => {
            if (err.response?.status === 503) {
                modulesEnabled = false;
                return loadSubjectsLegacy();
            }
            const msg = err.response?.data?.message || 'Failed to load subjects';
            setLoadError(msg);
            showAlert(msg, 'error');
        });
}

function loadSubjectsLegacy() {
    document.getElementById('migrationWarning')?.classList.remove('d-none');
    document.getElementById('addModuleBtn')?.classList.add('d-none');
    document.getElementById('filterModuleWrap')?.classList.add('d-none');
    return axios.get(`${SUBJECTS_API}?action=list`)
        .then(res => {
            treeLoaded = true;
            legacySubjects = res.data.success ? (res.data.data || []) : [];
            const seen = new Set();
            courseTree = [];
            legacySubjects.forEach(s => {
                const id = String(s.Course_ID || '');
                if (!id || seen.has(id)) return;
                seen.add(id);
                courseTree.push({ Course_ID: s.Course_ID, Course_Name: s.Course_Name || `Course ${s.Course_ID}`, modules: [] });
            });
            syncFilterDropdowns();
            renderTable();
        })
        .catch(err => {
            const msg = err.response?.data?.message || 'Failed to load subjects';
            setLoadError(msg);
            showAlert(msg, 'error');
        });
}

function resetModuleModal() {
    const form = document.getElementById('moduleForm');
    form?.reset();
    document.getElementById('moduleId').value = '';
    document.getElementById('moduleModalLabel').textContent = 'Add Module';
    document.getElementById('moduleSubmitBtn').textContent = 'Add Module';
    const courseSelect = document.getElementById('moduleCourse');
    if (courseSelect) courseSelect.disabled = false;
    populateCourseOptions(courseSelect, filterCourseId, '');
    if (filterCourseId && courseSelect) courseSelect.value = filterCourseId;
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('moduleFormAlert', '');
}

function openModuleModal() {
    if (!modulesEnabled) {
        showAlert('Module schema is not installed.', 'error');
        return;
    }
    resetModuleModal();
    addModuleModal?.show();
}

function fillModuleForm(mod) {
    document.getElementById('moduleId').value = mod.Module_ID;
    const courseSelect = document.getElementById('moduleCourse');
    populateCourseOptions(courseSelect, mod.Course_ID, '');
    if (courseSelect) {
        courseSelect.value = String(mod.Course_ID || '');
        courseSelect.disabled = true;
    }
    document.getElementById('moduleName').value = mod.Module_Name || '';
    document.getElementById('moduleModalLabel').textContent = 'Edit Module';
    document.getElementById('moduleSubmitBtn').textContent = 'Update Module';
    const form = document.getElementById('moduleForm');
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('moduleFormAlert', '');
    addModuleModal?.show();
}

function editModule(moduleId) {
    const mod = findModule(moduleId);
    if (mod) fillModuleForm(mod);
}

function archiveModule(moduleId) {
    const mod = findModule(moduleId);
    const name = mod?.Module_Name ? ` “${mod.Module_Name}”` : '';
    Swal.fire({
        title: 'Archive module?',
        text: `This will also archive subjects and exams in${name || ' this module'}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(MODULES_API, { action: 'archive', module_id: moduleId })
            .then(res => {
                if (res.data.success) {
                    showAlert(res.data.message || 'Module archived', 'success');
                    loadCourseTree();
                } else {
                    showAlert(res.data.message || 'Failed to archive module', 'error');
                }
            })
            .catch(err => showAlert(err.response?.data?.message || 'Failed to archive module', 'error'));
    });
}

function saveModule(e) {
    e.preventDefault();
    const form = e.target;
    const moduleId = document.getElementById('moduleId').value;
    const fieldErrors = {};
    if (!moduleId && !document.getElementById('moduleCourse').value) {
        fieldErrors.moduleCourse = 'Course is required';
    }
    if (!document.getElementById('moduleName').value.trim()) {
        fieldErrors.moduleName = 'Module name is required';
    }
    if (Object.keys(fieldErrors).length > 0) {
        ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'moduleFormAlert' });
        return;
    }
    ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('moduleFormAlert', '');

    const payload = {
        module_name: document.getElementById('moduleName').value,
        action: moduleId ? 'update' : 'add',
    };
    if (moduleId) {
        payload.module_id = moduleId;
    } else {
        payload.course_id = document.getElementById('moduleCourse').value;
    }

    axios.post(MODULES_API, payload)
        .then(res => {
            if (res.data.success) {
                showAlert(res.data.message || (moduleId ? 'Module updated' : 'Module added'), 'success');
                hideModal(addModuleModal);
                if (!moduleId && payload.course_id) filterCourseId = payload.course_id;
                loadCourseTree();
            } else {
                showAlert(res.data.message || 'Failed to save module', 'error');
            }
        })
        .catch(err => showAlert(err.response?.data?.message || 'Failed to save module', 'error'));
}

let extraSubjectSeq = 0;

function clearExtraSubjectRows() {
    document.querySelectorAll('#subjectEntryList .subject-entry-extra').forEach(row => row.remove());
}

function setAddAnotherSubjectVisible(visible) {
    document.getElementById('addAnotherSubjectWrap')?.classList.toggle('d-none', !visible);
}

function addExtraSubjectRow() {
    extraSubjectSeq += 1;
    const row = document.createElement('div');
    row.className = 'subject-entry subject-entry-extra border-top pt-3 mt-1';
    row.innerHTML = `
        <div class="d-flex justify-content-end mb-2">
            <button type="button" class="btn btn-sm btn-outline-danger remove-extra-subject" aria-label="Remove subject">&times;</button>
        </div>
        <div class="mb-3">
            <label class="form-label">Subject Name *</label>
            <input type="text" class="form-control extra-subject-name" id="extraSubjectName-${extraSubjectSeq}" placeholder="e.g., Mathematics">
        </div>
    `;
    document.getElementById('subjectEntryList')?.appendChild(row);
    row.querySelector('.extra-subject-name')?.focus();
}

function resetSubjectModal() {
    const form = document.getElementById('subjectForm');
    clearExtraSubjectRows();
    form?.reset();
    document.getElementById('subjectId').value = '';
    document.getElementById('subjectCourse').value = '';
    document.getElementById('subjectModule').value = '';
    document.getElementById('subjectModalLabel').textContent = 'Add New Subject';
    document.getElementById('subjectSubmitBtn').textContent = 'Add Subject';
    document.getElementById('subjectSubmitBtn').disabled = false;
    setAddAnotherSubjectVisible(true);
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('subjectFormAlert', '');
}

function setSubjectContext(courseId, moduleId) {
    document.getElementById('subjectCourse').value = courseId ? String(courseId) : '';
    document.getElementById('subjectModule').value = moduleId ? String(moduleId) : '';
}

function openSubjectModal(courseId, moduleId) {
    resetSubjectModal();
    const cid = courseId || filterCourseId;
    const mid = moduleId || filterModuleId;
    setSubjectContext(cid, mid);
    if (modulesEnabled && cid && modulesForCourse(cid).length === 0) {
        showAlert('This course has no modules yet. Add a module first.', 'warning');
        openModuleModal();
        return;
    }
    addSubjectModal?.show();
}

function fillSubjectForm(subject) {
    clearExtraSubjectRows();
    setAddAnotherSubjectVisible(false);
    document.getElementById('subjectId').value = subject.Subject_ID;
    setSubjectContext(subject.Course_ID, subject.Module_ID);
    document.getElementById('subjectName').value = subject.Subject_Name || '';
    document.getElementById('subjectModalLabel').textContent = 'Edit Subject';
    document.getElementById('subjectSubmitBtn').textContent = 'Update Subject';
    addSubjectModal?.show();
}

function findSubject(subjectId) {
    return flattenSubjects().find(s => String(s.Subject_ID) === String(subjectId)) || null;
}

function editSubject(subjectId) {
    const subject = findSubject(subjectId);
    if (subject) {
        fillSubjectForm(subject);
        return;
    }
    axios.get(`${SUBJECTS_API}?action=list`).then(res => {
        const row = (res.data.data || []).find(s => String(s.Subject_ID) === String(subjectId));
        if (row) fillSubjectForm(row);
    });
}

function refreshSubjectList(courseId, moduleId) {
    if (courseId) filterCourseId = courseId;
    if (moduleId) filterModuleId = moduleId;
    if (modulesEnabled) loadCourseTree();
    else loadSubjectsLegacy();
}

function collectSubjectEntries() {
    const entries = [{
        nameEl: document.getElementById('subjectName'),
    }];
    document.querySelectorAll('#subjectEntryList .subject-entry-extra').forEach(row => {
        entries.push({
            nameEl: row.querySelector('.extra-subject-name'),
            row,
        });
    });
    return entries;
}

function subjectEntriesToSave(entries) {
    const fieldErrors = {};
    const toSave = [];
    const seen = new Map();

    entries.forEach((entry, index) => {
        const name = entry.nameEl?.value.trim() || '';
        const isExtra = index > 0;
        if (isExtra && name === '') {
            return;
        }
        if (name === '') {
            if (entry.nameEl?.id) fieldErrors[entry.nameEl.id] = 'Subject name is required';
            return;
        }
        const key = name.toLowerCase();
        if (seen.has(key)) {
            if (entry.nameEl?.id) fieldErrors[entry.nameEl.id] = 'This subject name is already in the list';
            const first = seen.get(key);
            if (first.nameEl?.id) fieldErrors[first.nameEl.id] = 'This subject name is already in the list';
            return;
        }
        seen.set(key, entry);
        toSave.push(entry);
    });

    return { fieldErrors, toSave };
}

function dropSavedSubjectEntry(entry, pending) {
    if (entry.row) {
        entry.row.remove();
        return;
    }

    const nameInput = document.getElementById('subjectName');
    const next = pending[0];
    if (next?.row && next.nameEl) {
        nameInput.value = next.nameEl.value;
        next.row.remove();
        pending[0] = { nameEl: nameInput };
        return;
    }

    nameInput.value = '';
}

async function addSubjectEntries(form, entries) {
    const courseId = document.getElementById('subjectCourse').value;
    const moduleId = document.getElementById('subjectModule').value;
    const submitBtn = document.getElementById('subjectSubmitBtn');
    const pending = entries.slice();
    let added = 0;
    submitBtn.disabled = true;

    try {
        while (pending.length) {
            const entry = pending[0];
            const payload = {
                course_id: courseId,
                module_id: moduleId,
                subject_name: entry.nameEl.value.trim(),
            };
            try {
                const res = await axios.post(`${SUBJECTS_API}?action=add`, payload);
                if (!res.data.success) {
                    throw new Error(res.data.message || 'Failed to save subject');
                }
            } catch (err) {
                const message = err.response?.data?.message || err.message || 'Failed to save subject';
                if (entry.nameEl?.id) {
                    ModalFormValidation.applyModalFieldErrors(form, {
                        [entry.nameEl.id]: message,
                    }, { summaryAlertId: 'subjectFormAlert' });
                }
                if (added > 0) refreshSubjectList(courseId, moduleId);
                return;
            }

            added += 1;
            pending.shift();
            dropSavedSubjectEntry(entry, pending);
        }
    } finally {
        submitBtn.disabled = false;
    }

    showAlert(added === 1 ? 'Subject added' : `${added} subjects added`, 'success');
    hideModal(addSubjectModal);
    refreshSubjectList(courseId, moduleId);
}

function saveSubject(e) {
    e.preventDefault();
    const form = e.target;
    const fieldErrors = {};
    if (modulesEnabled && !document.getElementById('subjectCourse').value) {
        fieldErrors.subjectCourse = 'Course is required';
    }
    if (modulesEnabled && !document.getElementById('subjectModule').value) {
        fieldErrors.subjectModule = 'Module is required';
    }

    const subjectId = document.getElementById('subjectId').value;
    const entries = collectSubjectEntries();
    const prepared = subjectId ? null : subjectEntriesToSave(entries);
    if (subjectId && !document.getElementById('subjectName').value.trim()) {
        fieldErrors.subjectName = 'Subject name is required';
    }
    Object.assign(fieldErrors, prepared?.fieldErrors || {});

    if (Object.keys(fieldErrors).length > 0) {
        ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'subjectFormAlert' });
        return;
    }
    ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('subjectFormAlert', '');

    if (!subjectId) {
        addSubjectEntries(form, prepared.toSave);
        return;
    }

    const payload = {
        course_id: document.getElementById('subjectCourse').value,
        module_id: document.getElementById('subjectModule').value,
        subject_name: document.getElementById('subjectName').value,
        subject_id: subjectId,
    };

    axios.post(`${SUBJECTS_API}?action=update`, payload)
        .then(res => {
            if (res.data.success) {
                showAlert(res.data.message || 'Subject updated', 'success');
                hideModal(addSubjectModal);
                refreshSubjectList(payload.course_id, payload.module_id);
            } else {
                showAlert(res.data.message || 'Failed to save subject', 'error');
            }
        })
        .catch(err => showAlert(err.response?.data?.message || 'Failed to save subject', 'error'));
}

function archiveSubject(subjectId) {
    Swal.fire({
        title: 'Archive subject?',
        text: 'The subject will be hidden from Manage Subjects.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(`${SUBJECTS_API}?action=archive`, { subject_id: subjectId })
            .then(res => {
                if (res.data.success) {
                    showAlert(res.data.message || 'Subject archived', 'success');
                    if (modulesEnabled) loadCourseTree();
                    else loadSubjectsLegacy();
                } else {
                    showAlert(res.data.message || 'Failed to archive subject', 'error');
                }
            })
            .catch(err => showAlert(err.response?.data?.message || 'Failed to archive subject', 'error'));
    });
}

function bindSubjectPageEvents() {
    const moduleForm = document.getElementById('moduleForm');
    const subjectForm = document.getElementById('subjectForm');
    moduleForm?.addEventListener('submit', saveModule);
    subjectForm?.addEventListener('submit', saveSubject);
    ModalFormValidation.bindModalFieldErrorClear(moduleForm);
    ModalFormValidation.bindModalFieldErrorClear(subjectForm);
    document.getElementById('addSubjectModal')?.addEventListener('hidden.bs.modal', resetSubjectModal);
    document.getElementById('addModuleModal')?.addEventListener('hidden.bs.modal', resetModuleModal);
    document.getElementById('addAnotherSubjectBtn')?.addEventListener('click', addExtraSubjectRow);
    document.getElementById('subjectEntryList')?.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-extra-subject');
        if (!removeBtn) return;
        removeBtn.closest('.subject-entry-extra')?.remove();
    });

    document.getElementById('addModuleBtn')?.addEventListener('click', openModuleModal);

    document.getElementById('filterCourse')?.addEventListener('change', function () {
        applyCourseFilter(this.value, '');
    });
    document.getElementById('filterModule')?.addEventListener('change', function () {
        filterModuleId = this.value || '';
        renderTable();
    });
    document.getElementById('filterSearch')?.addEventListener('input', function () {
        searchQuery = this.value || '';
        if (treeLoaded) renderTable();
    });

    document.getElementById('subjectsContainer')?.addEventListener('click', function (e) {
        const actionBtn = e.target.closest('[data-action]');
        if (!actionBtn) return;
        e.preventDefault();
        const action = actionBtn.dataset.action;
        if (action === 'add-module') openModuleModal();
        else if (action === 'add-subject') openSubjectModal(actionBtn.dataset.courseId, actionBtn.dataset.moduleId);
        else if (action === 'edit-subject') editSubject(actionBtn.dataset.subjectId);
        else if (action === 'archive-subject') archiveSubject(actionBtn.dataset.subjectId);
        else if (action === 'edit-module') editModule(actionBtn.dataset.moduleId);
        else if (action === 'archive-module') archiveModule(actionBtn.dataset.moduleId);
    });
}

function bootstrapSubjectsPage() {
    initModals();
    bindSubjectPageEvents();
    loadCourseTree();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapSubjectsPage);
} else {
    bootstrapSubjectsPage();
}
