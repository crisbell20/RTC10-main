const COURSES_API = '../../api/masterfiles/courses.php';
const MODULES_API = '../../api/masterfiles/modules.php';
const SUBJECTS_API = '../../api/masterfiles/subjects.php';

let modulesEnabled = true;
let courseTree = [];
let currentCourseFilter = '';

document.addEventListener('DOMContentLoaded', () => {
    bindSubjectPageEvents();
    loadCourseTree();
});

function bindSubjectPageEvents() {
    document.getElementById('filterCourse')?.addEventListener('change', function () {
        currentCourseFilter = this.value || '';
        loadCourseTree();
    });
    document.getElementById('subjectCourse')?.addEventListener('change', populateModuleSelect);
    document.getElementById('subjectForm')?.addEventListener('submit', saveSubject);
    document.getElementById('moduleForm')?.addEventListener('submit', saveModule);
    document.getElementById('addSubjectModal')?.addEventListener('hidden.bs.modal', resetSubjectModal);
    document.getElementById('addModuleModal')?.addEventListener('hidden.bs.modal', resetModuleModal);
    document.getElementById('subjectsContainer')?.addEventListener('click', onSubjectsContainerClick);
}

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    if (!alertDiv) return;
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function loadCourseTree() {
    const container = document.getElementById('subjectsContainer');
    const courseFilter = currentCourseFilter;

    if (container) {
        container.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Loading...</div>';
    }

    const params = new URLSearchParams({ action: 'tree' });
    if (courseFilter) params.append('course_id', courseFilter);

    return axios.get(`${MODULES_API}?${params.toString()}`)
        .then(res => {
            if (!res.data.success) {
                modulesEnabled = false;
                return loadSubjectsLegacy();
            }
            modulesEnabled = true;
            document.getElementById('migrationWarning')?.classList.add('d-none');
            courseTree = res.data.data || [];
            renderCourseTree(getFilteredCourses());
            return populateCourseFilter();
        })
        .catch(err => {
            if (err.response?.status === 503) {
                modulesEnabled = false;
                return loadSubjectsLegacy();
            }
            if (container) {
                container.innerHTML = '<div class="text-center text-danger py-4">Failed to load curriculum</div>';
            }
        });
}

function populateCourseFilter() {
    return axios.get(`${COURSES_API}?action=list`).then(res => {
        if (!res.data.success) return;
        const courses = res.data.data || [];
        const filterSelect = document.getElementById('filterCourse');
        const subjectCourse = document.getElementById('subjectCourse');
        const moduleCourse = document.getElementById('moduleCourse');

        if (filterSelect) {
            filterSelect.innerHTML = '<option value="">All courses</option>';
            courses.forEach(c => filterSelect.add(new Option(c.Course_Name, c.Course_ID)));
            filterSelect.value = currentCourseFilter;
        }
        if (subjectCourse) {
            const current = subjectCourse.value;
            subjectCourse.innerHTML = '<option value="">Select course...</option>';
            courses.forEach(c => subjectCourse.add(new Option(c.Course_Name, c.Course_ID)));
            subjectCourse.value = current;
        }
        if (moduleCourse) {
            const current = moduleCourse.value;
            moduleCourse.innerHTML = '<option value="">Select course...</option>';
            courses.forEach(c => moduleCourse.add(new Option(c.Course_Name, c.Course_ID)));
            moduleCourse.value = current;
        }
    });
}

function getFilteredCourses() {
    if (!currentCourseFilter) {
        return courseTree;
    }
    return courseTree.filter(c => String(c.Course_ID) === String(currentCourseFilter));
}

function loadSubjectsLegacy() {
    const courseFilter = currentCourseFilter;
    let url = `${SUBJECTS_API}?action=list`;
    if (courseFilter) url += `&course_id=${courseFilter}`;

    document.getElementById('migrationWarning')?.classList.remove('d-none');

    return axios.get(url).then(res => {
        const container = document.getElementById('subjectsContainer');
        if (!res.data.success || !res.data.data.length) {
            container.innerHTML = '<div class="text-center text-muted py-4">No subjects found</div>';
            return populateCourseFilter();
        }
        const grouped = {};
        res.data.data.forEach(s => {
            const key = s.Course_Name || `Course ${s.Course_ID}`;
            if (!grouped[key]) grouped[key] = { name: key, subjects: [] };
            grouped[key].subjects.push(s);
        });
        container.innerHTML = Object.values(grouped).map(course => `
            <div class="course-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5>${escapeHtml(course.name)}</h5>
                    </div>
                </div>
                <div class="mt-3">
                    ${course.subjects.map(renderSubjectItem).join('')}
                </div>
            </div>
        `).join('');
        return populateCourseFilter();
    });
}

function buildActionMenu(items) {
    const menuItems = items.map(item =>
        `<li><button class="dropdown-item" type="button" data-action="${item.action}" ${item.attrs || ''}>${item.label}</button></li>`
    ).join('');
    return `<div class="dropdown">
        <button type="button" class="btn btn-sm btn-link text-secondary p-0" data-bs-toggle="dropdown" aria-expanded="false" title="More actions">
            <i class="bi bi-three-dots-vertical" style="font-size: 1.2rem;"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">${menuItems}</ul>
    </div>`;
}

function renderCourseTree(courses) {
    const container = document.getElementById('subjectsContainer');
    if (!container) return;

    if (!courses.length) {
        container.innerHTML = '<div class="text-center text-muted py-4">No courses found</div>';
        return;
    }

    container.innerHTML = courses.map(course => {
        const modules = course.modules || [];
        const modulesHtml = modules.length
            ? modules.map(mod => renderModuleItem(mod)).join('')
            : '<div class="text-muted small ms-3">No modules yet</div>';

        return `
        <div class="course-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5>${escapeHtml(course.Course_Name)}</h5>
                </div>
            </div>
            <div class="mt-3" id="modules-${course.Course_ID}">
                ${modulesHtml}
            </div>
        </div>`;
    }).join('');
}

function renderModuleItem(mod) {
    const subjects = mod.subjects || [];
    return `
        <div class="section-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(mod.Module_Name)}</strong>
                    ${mod.Description ? `<div class="text-muted small">${escapeHtml(mod.Description)}</div>` : ''}
                </div>
                <div>
                    ${buildActionMenu([
                        { action: 'edit-module', attrs: `data-module-id="${mod.Module_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-module', attrs: `data-module-id="${mod.Module_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
            <div class="mt-2" id="subjects-${mod.Module_ID}">
                ${renderSubjects(subjects)}
            </div>
        </div>`;
}

function renderSubjects(subjects) {
    if (!subjects || subjects.length === 0) {
        return '<div class="text-muted small ms-3">No subjects yet</div>';
    }
    return subjects.map(renderSubjectItem).join('');
}

function renderSubjectItem(s) {
    return `
        <div class="batch-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(s.Subject_Name)}</strong>
                    ${s.Description ? `<div class="text-muted small">${escapeHtml(s.Description)}</div>` : ''}
                </div>
                <div>
                    ${buildActionMenu([
                        { action: 'edit-subject', attrs: `data-subject-id="${s.Subject_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-subject', attrs: `data-subject-id="${s.Subject_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
        </div>`;
}

function onSubjectsContainerClick(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;

    const action = btn.dataset.action;
    switch (action) {
        case 'edit-module':
            editModule(btn.dataset.moduleId);
            break;
        case 'archive-module':
            archiveModule(btn.dataset.moduleId);
            break;
        case 'edit-subject':
            editSubject(btn.dataset.subjectId);
            break;
        case 'archive-subject':
            archiveSubject(btn.dataset.subjectId);
            break;
    }
}

function populateModuleSelect(selectedModuleId, courseIdOverride) {
    const courseId = courseIdOverride || document.getElementById('subjectCourse')?.value || '';
    const select = document.getElementById('subjectModule');
    if (!select) return Promise.resolve();

    select.innerHTML = '<option value="">Select module...</option>';
    if (!courseId) {
        select.disabled = true;
        return Promise.resolve();
    }

    return axios.get(`${MODULES_API}?action=list&course_id=${courseId}`).then(res => {
        if (!res.data.success) return;
        (res.data.data || []).forEach(m => {
            const opt = new Option(m.Module_Name, m.Module_ID);
            if (selectedModuleId && String(selectedModuleId) === String(m.Module_ID)) {
                opt.selected = true;
            }
            select.add(opt);
        });
        select.disabled = false;
    });
}

function openModuleModal(courseId) {
    document.getElementById('moduleForm').reset();
    document.getElementById('moduleId').value = '';
    document.getElementById('moduleModalLabel').textContent = 'Add Module';
    document.getElementById('moduleSubmitBtn').textContent = 'Add Module';
    if (courseId) document.getElementById('moduleCourse').value = courseId;
    new bootstrap.Modal(document.getElementById('addModuleModal')).show();
}

function editModule(moduleId) {
    const mod = findModule(moduleId);
    if (!mod) return;
    document.getElementById('moduleId').value = mod.Module_ID;
    document.getElementById('moduleCourse').value = mod.Course_ID;
    document.getElementById('moduleName').value = mod.Module_Name || '';
    document.getElementById('moduleDescription').value = mod.Description || '';
    document.getElementById('moduleModalLabel').textContent = 'Edit Module';
    document.getElementById('moduleSubmitBtn').textContent = 'Save Module';
    new bootstrap.Modal(document.getElementById('addModuleModal')).show();
}

function findModule(moduleId) {
    for (const course of courseTree) {
        for (const mod of course.modules || []) {
            if (String(mod.Module_ID) === String(moduleId)) {
                return { ...mod, Course_ID: course.Course_ID };
            }
        }
    }
    return null;
}

function findSubject(subjectId) {
    for (const course of courseTree) {
        for (const mod of course.modules || []) {
            for (const s of mod.subjects || []) {
                if (String(s.Subject_ID) === String(subjectId)) {
                    return { ...s, Module_ID: mod.Module_ID, Course_ID: course.Course_ID };
                }
            }
        }
    }
    return null;
}

function saveModule(e) {
    e.preventDefault();
    const moduleId = document.getElementById('moduleId').value;
    const payload = {
        course_id: document.getElementById('moduleCourse').value,
        module_name: document.getElementById('moduleName').value,
        description: document.getElementById('moduleDescription').value,
        action: moduleId ? 'update' : 'add',
    };
    if (moduleId) payload.module_id = moduleId;

    axios.post(MODULES_API, payload)
        .then(res => {
            if (res.data.success) {
                Swal.fire('Success', res.data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addModuleModal')).hide();
                loadCourseTree();
            } else {
                Swal.fire('Error', res.data.message, 'error');
            }
        })
        .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed to save module', 'error'));
}

function archiveModule(moduleId) {
    Swal.fire({
        title: 'Archive module?',
        html: 'The module and all its <strong>subjects</strong> will be archived.<br><br>You can restore individual subjects from Archive later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(MODULES_API, { action: 'archive', module_id: moduleId })
            .then(res => {
                if (res.data.success) {
                    Swal.fire('Archived', res.data.message, 'success');
                    loadCourseTree();
                } else {
                    Swal.fire('Error', res.data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed to archive module', 'error'));
    });
}

function openSubjectModal(courseId, moduleId) {
    resetSubjectModal();
    if (courseId) document.getElementById('subjectCourse').value = courseId;
    populateModuleSelect(moduleId, courseId).then(() => {
        if (moduleId) document.getElementById('subjectModule').value = moduleId;
        new bootstrap.Modal(document.getElementById('addSubjectModal')).show();
    });
}

function editSubject(subjectId) {
    const subject = findSubject(subjectId);
    if (!subject) {
        axios.get(`${SUBJECTS_API}?action=list`).then(res => {
            const row = (res.data.data || []).find(s => String(s.Subject_ID) === String(subjectId));
            if (row) fillSubjectForm(row);
        });
        return;
    }
    fillSubjectForm(subject);
}

function fillSubjectForm(subject) {
    document.getElementById('subjectId').value = subject.Subject_ID;
    document.getElementById('subjectCourse').value = subject.Course_ID;
    document.getElementById('subjectName').value = subject.Subject_Name;
    document.getElementById('subjectDescription').value = subject.Description || '';
    document.getElementById('subjectModalLabel').textContent = 'Edit Subject';
    document.getElementById('subjectSubmitBtn').textContent = 'Update Subject';
    populateModuleSelect(subject.Module_ID, subject.Course_ID).then(() => {
        new bootstrap.Modal(document.getElementById('addSubjectModal')).show();
    });
}

function saveSubject(e) {
    e.preventDefault();
    const subjectId = document.getElementById('subjectId').value;
    const payload = {
        course_id: document.getElementById('subjectCourse').value,
        module_id: document.getElementById('subjectModule').value,
        subject_name: document.getElementById('subjectName').value,
        description: document.getElementById('subjectDescription').value,
    };
    const action = subjectId ? 'update' : 'add';
    if (subjectId) payload.subject_id = subjectId;

    axios.post(`${SUBJECTS_API}?action=${action}`, payload)
        .then(res => {
            if (res.data.success) {
                Swal.fire('Success', res.data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('addSubjectModal')).hide();
                loadCourseTree();
            } else {
                Swal.fire('Error', res.data.message, 'error');
            }
        })
        .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed to save subject', 'error'));
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
                    Swal.fire('Archived', res.data.message, 'success');
                    loadCourseTree();
                } else {
                    Swal.fire('Error', res.data.message, 'error');
                }
            })
            .catch(err => Swal.fire('Error', err.response?.data?.message || 'Failed to archive subject', 'error'));
    });
}

function resetSubjectModal() {
    document.getElementById('subjectForm').reset();
    document.getElementById('subjectId').value = '';
    document.getElementById('subjectModalLabel').textContent = 'Add New Subject';
    document.getElementById('subjectSubmitBtn').textContent = 'Add Subject';
    const mod = document.getElementById('subjectModule');
    if (mod) {
        mod.innerHTML = '<option value="">Select module...</option>';
        mod.disabled = true;
    }
}

function resetModuleModal() {
    document.getElementById('moduleForm').reset();
    document.getElementById('moduleId').value = '';
    document.getElementById('moduleModalLabel').textContent = 'Add Module';
    document.getElementById('moduleSubmitBtn').textContent = 'Add Module';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

window.openSubjectModalFromHeader = function() {
    openSubjectModal(currentCourseFilter || '', '');
};

window.openModuleModalFromHeader = function() {
    openModuleModal(currentCourseFilter || '');
};
