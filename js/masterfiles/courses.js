const API_BASE = '../../api/masterfiles/';
const COURSES_API = API_BASE + 'courses.php';
const SECTIONS_API = API_BASE + 'sections.php';
const BATCHES_API = API_BASE + 'batches.php';
const USERS_API = API_BASE + 'users.php';

let addBatchModal = null;
let addSectionModal = null;
let addCourseModal = null;
let allCourses = [];
let currentCourseFilter = '';

function initModals() {
    const batchModalEl = document.getElementById('addBatchModal');
    const sectionModalEl = document.getElementById('addSectionModal');
    const courseModalEl = document.getElementById('addCourseModal');

    if (batchModalEl) {
        addBatchModal = bootstrap.Modal.getOrCreateInstance(batchModalEl);
    }
    if (sectionModalEl) {
        addSectionModal = bootstrap.Modal.getOrCreateInstance(sectionModalEl);
    }
    if (courseModalEl) {
        addCourseModal = bootstrap.Modal.getOrCreateInstance(courseModalEl);
    }
}

function hideModal(modal) {
    if (modal) {
        modal.hide();
    }
}

function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function loadCourses() {
    axios.get(`${COURSES_API}?action=tree`)
        .then(response => {
            if (response.data.success) {
                allCourses = response.data.data || [];
                populateCourseFilter();
                renderFilteredCourses();
            } else {
                setCoursesLoadError('Failed to load courses');
                showAlert(response.data.message || 'Failed to load courses', 'error');
            }
        })
        .catch(error => {
            const msg = error.response?.data?.message || 'Error loading courses';
            setCoursesLoadError(msg);
            showAlert(msg, 'error');
            console.error(error);
        });
}

function populateCourseFilter() {
    const select = document.getElementById('filterCourse');
    if (!select) return;

    const current = select.value;
    select.innerHTML = '<option value="">All courses</option>';
    allCourses.forEach(course => {
        select.add(new Option(course.Course_Name, course.Course_ID));
    });
    select.value = currentCourseFilter || current;
}

function getFilteredCourses() {
    if (!currentCourseFilter) {
        return allCourses;
    }
    return allCourses.filter(c => String(c.Course_ID) === String(currentCourseFilter));
}

function renderFilteredCourses() {
    renderCourses(getFilteredCourses());
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

function findCourse(courseId) {
    return allCourses.find(c => String(c.Course_ID) === String(courseId));
}

function findBatch(batchId) {
    for (const course of allCourses) {
        for (const batch of course.batches || []) {
            if (String(batch.Batch_ID) === String(batchId)) {
                return { ...batch, Course_ID: course.Course_ID };
            }
        }
    }
    return null;
}

function findSection(sectionId) {
    for (const course of allCourses) {
        for (const batch of course.batches || []) {
            for (const section of batch.sections || []) {
                if (String(section.Section_ID) === String(sectionId)) {
                    return { ...section, Batch_ID: batch.Batch_ID, Course_ID: course.Course_ID };
                }
            }
        }
    }
    return null;
}

function setCoursesLoadError(message) {
    const container = document.getElementById('coursesContainer');
    if (container) {
        container.innerHTML = `<div class="text-center text-danger py-4">${escapeHtml(message)}</div>`;
    }
}

function renderCourses(courses) {
    const container = document.getElementById('coursesContainer');

    if (courses.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">No courses found</div>';
        return;
    }

    container.innerHTML = courses.map(course => `
        <div class="course-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h5>${escapeHtml(course.Course_Name)}</h5>
                    ${course.Details ? `<p class="text-muted mb-1">${escapeHtml(course.Details)}</p>` : ''}
                    ${course.Duration ? `<small class="text-muted">Duration: ${escapeHtml(course.Duration)}</small>` : ''}
                </div>
                <div class="d-flex gap-2 align-items-start">
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-batch" data-course-id="${course.Course_ID}">
                        <i class="bi bi-plus"></i> Add Batch
                    </button>
                    ${buildActionMenu([
                        { action: 'edit-course', attrs: `data-course-id="${course.Course_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-course', attrs: `data-course-id="${course.Course_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
            <div class="mt-3" id="batches-${course.Course_ID}">
                ${renderBatches(course.batches || [], course.Course_ID)}
            </div>
        </div>
    `).join('');
}

function renderBatches(batches, courseId) {
    if (!batches || batches.length === 0) {
        return '<div class="text-muted small ms-3">No batches yet</div>';
    }

    return batches.map(batch => `
        <div class="section-item">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>${escapeHtml(batch.Batch_Name)}</strong>
                    ${batch.Date_Started ? `<small class="text-muted ms-2">Start: ${new Date(batch.Date_Started).toLocaleDateString()}</small>` : ''}
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-info" data-action="add-section" data-batch-id="${batch.Batch_ID}">
                        <i class="bi bi-plus"></i> Add Section
                    </button>
                    ${buildActionMenu([
                        { action: 'edit-batch', attrs: `data-batch-id="${batch.Batch_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-batch', attrs: `data-batch-id="${batch.Batch_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
            <div class="mt-2" id="sections-${batch.Batch_ID}">
                ${renderSections(batch.sections || [], courseId)}
            </div>
        </div>
    `).join('');
}

function renderSections(sections, courseId) {
    if (!sections || sections.length === 0) {
        return '<div class="text-muted small ms-3">No sections yet</div>';
    }

    return sections.map(section => `
        <div class="batch-item">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>${escapeHtml(section.Section_Name)}</strong>
                    <small class="text-muted ms-2">Capacity: ${section.Capacity}</small>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-info" data-action="view-section-users" data-section-id="${section.Section_ID}" title="View/Assign Users" aria-expanded="false">
                        <i class="bi bi-people"></i>
                    </button>
                    ${buildActionMenu([
                        { action: 'edit-section', attrs: `data-section-id="${section.Section_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-section', attrs: `data-section-id="${section.Section_ID}" data-course-id="${courseId}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
            <div id="section-users-${section.Section_ID}" class="batch-users ms-3"></div>
        </div>
    `).join('');
}

function addBatch(courseId) {
    const courseIdInput = document.getElementById('batchCourseId');
    if (!courseIdInput) {
        showAlert('Batch form is not available', 'error');
        return;
    }
    resetBatchModal();
    courseIdInput.value = courseId;
    if (addBatchModal) {
        addBatchModal.show();
    }
}

function addSection(batchId) {
    const batchIdInput = document.getElementById('sectionBatchId');
    if (!batchIdInput) {
        showAlert('Section form is not available', 'error');
        return;
    }
    resetSectionModal();
    batchIdInput.value = batchId;
    if (addSectionModal) {
        addSectionModal.show();
    }
}

function resetCourseModal() {
    const form = document.getElementById('addCourseForm');
    form?.reset();
    document.getElementById('editCourseId').value = '';
    document.getElementById('courseModalTitle').textContent = 'Add New Course';
    document.getElementById('courseSubmitBtn').textContent = 'Add Course';
}

function resetBatchModal() {
    const form = document.getElementById('addBatchForm');
    form?.reset();
    document.getElementById('editBatchId').value = '';
    document.getElementById('batchModalTitle').textContent = 'Add Batch to Course';
    document.getElementById('batchSubmitBtn').textContent = 'Add Batch';
}

function resetSectionModal() {
    const form = document.getElementById('addSectionForm');
    form?.reset();
    document.getElementById('editSectionId').value = '';
    document.getElementById('sectionModalTitle').textContent = 'Add Section to Batch';
    document.getElementById('sectionSubmitBtn').textContent = 'Add Section';
}

function editCourse(courseId) {
    const course = findCourse(courseId);
    if (!course) return;
    resetCourseModal();
    document.getElementById('editCourseId').value = course.Course_ID;
    document.querySelector('#addCourseForm [name=course_name]').value = course.Course_Name || '';
    document.querySelector('#addCourseForm [name=details]').value = course.Details || '';
    document.querySelector('#addCourseForm [name=duration]').value = course.Duration || '';
    document.getElementById('courseModalTitle').textContent = 'Edit Course';
    document.getElementById('courseSubmitBtn').textContent = 'Save Changes';
    addCourseModal?.show();
}

function editBatch(batchId) {
    const batch = findBatch(batchId);
    if (!batch) return;
    resetBatchModal();
    document.getElementById('editBatchId').value = batch.Batch_ID;
    document.getElementById('batchCourseId').value = batch.Course_ID;
    document.querySelector('#addBatchForm [name=batch_name]').value = batch.Batch_Name || '';
    document.querySelector('#addBatchForm [name=date_started]').value = batch.Date_Started ? String(batch.Date_Started).slice(0, 10) : '';
    document.querySelector('#addBatchForm [name=date_ended]').value = batch.Date_Ended ? String(batch.Date_Ended).slice(0, 10) : '';
    document.getElementById('batchModalTitle').textContent = 'Edit Batch';
    document.getElementById('batchSubmitBtn').textContent = 'Save Changes';
    addBatchModal?.show();
}

function editSection(sectionId) {
    const section = findSection(sectionId);
    if (!section) return;
    resetSectionModal();
    document.getElementById('editSectionId').value = section.Section_ID;
    document.getElementById('sectionBatchId').value = section.Batch_ID;
    document.querySelector('#addSectionForm [name=section_name]').value = section.Section_Name || '';
    document.querySelector('#addSectionForm [name=capacity]').value = section.Capacity || 40;
    document.getElementById('sectionModalTitle').textContent = 'Edit Section';
    document.getElementById('sectionSubmitBtn').textContent = 'Save Changes';
    addSectionModal?.show();
}

function archiveCourse(courseId) {
    Swal.fire({
        title: 'Archive Course?',
        html: 'The course and all its <strong>batches, sections, modules, and subjects</strong> will be archived.<br><br>You can restore children individually from Archive later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(COURSES_API, { action: 'archive', course_id: courseId })
            .then(res => {
                showAlert(res.data.message || 'Course archived successfully', 'success');
                loadCourses();
            })
            .catch(error => showAlert(error.response?.data?.message || 'Error archiving course', 'error'));
    });
}

function archiveBatch(batchId) {
    Swal.fire({
        title: 'Archive Batch?',
        html: 'The batch and all its <strong>sections</strong> will be archived.<br><br>You can restore individual sections from Archive later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(BATCHES_API, { action: 'archive', batch_id: batchId })
            .then(res => {
                showAlert(res.data.message || 'Batch archived successfully', 'success');
                loadCourses();
            })
            .catch(error => showAlert(error.response?.data?.message || 'Error archiving batch', 'error'));
    });
}

function archiveSection(sectionId) {
    Swal.fire({
        title: 'Archive Section?',
        text: 'The section will be hidden from Manage Courses.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(SECTIONS_API, { action: 'archive', section_id: sectionId })
            .then(() => {
                showAlert('Section archived successfully', 'success');
                loadCourses();
            })
            .catch(error => showAlert(error.response?.data?.message || 'Error archiving section', 'error'));
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Form handlers
function bindCourseForms() {
    const addCourseForm = document.getElementById('addCourseForm');
    const addBatchForm = document.getElementById('addBatchForm');
    const addSectionForm = document.getElementById('addSectionForm');

    if (!addCourseForm || !addBatchForm || !addSectionForm) {
        console.error('Manage Courses forms are missing from the page');
        return;
    }

    addCourseForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const courseId = formData.get('course_id');
        const data = {
            course_name: formData.get('course_name'),
            details: formData.get('details'),
            duration: formData.get('duration'),
            action: courseId ? 'update' : 'add',
        };
        if (courseId) data.course_id = courseId;

        axios.post(COURSES_API, data)
            .then(response => {
                if (response.data.success) {
                    showAlert(courseId ? 'Course updated successfully' : 'Course added successfully', 'success');
                    resetCourseModal();
                    hideModal(addCourseModal);
                    loadCourses();
                } else {
                    showAlert(response.data.message || 'Failed to save course', 'error');
                }
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Error saving course', 'error');
            });
    });

    document.getElementById('addCourseModal')?.addEventListener('hidden.bs.modal', resetCourseModal);

    addBatchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const courseId = formData.get('course_id');
        const batchId = formData.get('batch_id');
        const batchName = formData.get('batch_name');

        if (!courseId) {
            showAlert('Course is missing. Close the modal and click Add Batch again.', 'error');
            return;
        }
        if (!batchName || !String(batchName).trim()) {
            showAlert('Batch name is required', 'error');
            return;
        }

        const data = {
            course_id: courseId,
            batch_name: batchName,
            date_started: formData.get('date_started') || null,
            date_ended: formData.get('date_ended') || null,
            action: batchId ? 'update' : 'add',
        };
        if (batchId) data.batch_id = batchId;

        axios.post(BATCHES_API, data)
            .then(response => {
                if (response.data.success) {
                    showAlert(response.data.message || (batchId ? 'Batch updated successfully' : 'Batch added successfully'), 'success');
                    resetBatchModal();
                    hideModal(addBatchModal);
                    loadCourses();
                } else {
                    showAlert(response.data.message || 'Failed to save batch', 'error');
                }
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Error saving batch', 'error');
            });
    });

    document.getElementById('addBatchModal')?.addEventListener('hidden.bs.modal', resetBatchModal);

    addSectionForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const batchId = formData.get('batch_id');
        const sectionId = formData.get('section_id');
        const sectionName = formData.get('section_name');

        if (!batchId) {
            showAlert('Batch is missing. Close the modal and click Add Section again.', 'error');
            return;
        }
        if (!sectionName || !String(sectionName).trim()) {
            showAlert('Section name is required', 'error');
            return;
        }

        const data = {
            batch_id: batchId,
            section_name: sectionName,
            capacity: formData.get('capacity') || 40,
            action: sectionId ? 'update' : 'add',
        };
        if (sectionId) data.section_id = sectionId;

        axios.post(SECTIONS_API, data)
            .then(response => {
                if (response.data.success) {
                    showAlert(response.data.message || (sectionId ? 'Section updated successfully' : 'Section added successfully'), 'success');
                    resetSectionModal();
                    hideModal(addSectionModal);
                    loadCourses();
                } else {
                    showAlert(response.data.message || 'Failed to save section', 'error');
                }
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Error saving section', 'error');
            });
    });

    document.getElementById('addSectionModal')?.addEventListener('hidden.bs.modal', resetSectionModal);
}

function bindCourseActions() {
    const container = document.getElementById('coursesContainer');
    if (!container) {
        return;
    }

    container.addEventListener('click', function(e) {
        const button = e.target.closest('[data-action]');
        if (!button) {
            return;
        }

        const action = button.dataset.action;

        if (action === 'add-batch') {
            e.preventDefault();
            addBatch(button.dataset.courseId);
            return;
        }

        if (action === 'add-section') {
            e.preventDefault();
            addSection(button.dataset.batchId);
            return;
        }

        if (action === 'edit-course') {
            e.preventDefault();
            editCourse(button.dataset.courseId);
            return;
        }

        if (action === 'archive-course') {
            e.preventDefault();
            archiveCourse(button.dataset.courseId);
            return;
        }

        if (action === 'edit-batch') {
            e.preventDefault();
            editBatch(button.dataset.batchId);
            return;
        }

        if (action === 'archive-batch') {
            e.preventDefault();
            archiveBatch(button.dataset.batchId);
            return;
        }

        if (action === 'edit-section') {
            e.preventDefault();
            editSection(button.dataset.sectionId);
            return;
        }

        if (action === 'archive-section') {
            e.preventDefault();
            archiveSection(button.dataset.sectionId);
            return;
        }

        if (action === 'view-section-users') {
            e.preventDefault();
            viewSectionUsers(button.dataset.sectionId);
            return;
        }
    });
}

// Dropdown handlers
document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const submenu = this.nextElementSibling;
        this.classList.toggle('active');
        submenu.classList.toggle('show');
    });
});

// View/assign users to section
let openSectionUsersId = null;

function getSectionUsersToggleButton(sectionId) {
    return document.querySelector(`[data-action="view-section-users"][data-section-id="${sectionId}"]`);
}

function updateSectionUsersToggleButton(sectionId, isOpen) {
    const button = getSectionUsersToggleButton(sectionId);
    if (!button) {
        return;
    }

    const icon = button.querySelector('i');
    if (icon) {
        icon.className = isOpen ? 'bi bi-chevron-up' : 'bi bi-people';
    }

    button.title = isOpen ? 'Hide assigned users' : 'View/Assign Users';
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function collapseSectionUsers(sectionId) {
    const container = document.getElementById(`section-users-${sectionId}`);
    if (container) {
        container.innerHTML = '';
        container.dataset.open = '0';
    }

    updateSectionUsersToggleButton(sectionId, false);

    if (openSectionUsersId === String(sectionId)) {
        openSectionUsersId = null;
    }
}

function bindSectionUsersPanelEvents(sectionId, container) {
    container.querySelector('[data-action="assign-section-users"]')?.addEventListener('click', () => {
        assignUserToSection(sectionId);
    });

    container.querySelectorAll('[data-action="remove-section-user"]').forEach(btn => {
        btn.addEventListener('click', () => {
            removeFromSection(btn.dataset.userId, btn.dataset.sectionId);
        });
    });
}

function renderSectionUsersPanel(sectionId, users, options = {}) {
    const { errorMessage = '' } = options;
    const countLabel = users.length
        ? `Assigned Trainees (${users.length})`
        : 'Assigned Trainees';

    let bodyHtml = '';
    if (errorMessage) {
        bodyHtml = `<div class="text-danger small">${escapeHtml(errorMessage)}</div>`;
    } else if (users.length === 0) {
        bodyHtml = '<div class="text-muted small">No users assigned</div>';
    } else {
        bodyHtml = users.map(user => `
            <div class="small mb-1 d-flex align-items-center flex-wrap gap-1">
                <span>${escapeHtml(user.Fullname)} (${escapeHtml(user.Email)})</span>
                <button class="btn btn-sm btn-outline-danger" data-action="remove-section-user" data-user-id="${user.User_ID}" data-section-id="${sectionId}" title="Remove">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
    }

    return `
        <div class="section-users-panel border rounded p-2 mt-1">
            <div class="mb-2">
                <span class="small fw-semibold">${countLabel}</span>
            </div>
            <div class="section-users-list mb-2">${bodyHtml}</div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-action="assign-section-users" data-section-id="${sectionId}">
                Assign Section
            </button>
        </div>
    `;
}

function viewSectionUsers(sectionId, options = {}) {
    const { reload = false } = options;
    const container = document.getElementById(`section-users-${sectionId}`);
    if (!container) {
        return;
    }

    const isOpen = container.dataset.open === '1';
    if (isOpen && !reload) {
        collapseSectionUsers(sectionId);
        return;
    }

    if (openSectionUsersId && openSectionUsersId !== String(sectionId)) {
        collapseSectionUsers(openSectionUsersId);
    }

    container.dataset.open = '1';
    openSectionUsersId = String(sectionId);
    updateSectionUsersToggleButton(sectionId, true);
    container.innerHTML = '<div class="text-muted small ms-1">Loading...</div>';

    axios.get(`${BATCHES_API}?action=users_by_section&section_id=${sectionId}`)
        .then(response => {
            if (response.data.success) {
                const users = response.data.data || [];
                container.innerHTML = renderSectionUsersPanel(sectionId, users);
            } else {
                container.innerHTML = renderSectionUsersPanel(sectionId, [], {
                    errorMessage: 'Failed to load assigned users',
                });
            }

            bindSectionUsersPanelEvents(sectionId, container);
        })
        .catch(error => {
            console.error('Error loading section users:', error);
            container.innerHTML = renderSectionUsersPanel(sectionId, [], {
                errorMessage: 'Error loading assigned users',
            });
            bindSectionUsersPanelEvents(sectionId, container);
        });
}

// Assign users to section (multi-select with checkboxes via SweetAlert)
function assignUserToSection(sectionId) {
    Promise.all([
        axios.get(`${BATCHES_API}?action=section_capacity&section_id=${sectionId}`),
        axios.get(`${BATCHES_API}?action=enrolled_examinee_ids`),
        axios.get(`${USERS_API}?action=list`)
    ])
        .then(([capacityResponse, enrolledResponse, usersResponse]) => {
            if (!usersResponse.data.success) {
                showAlert('Error loading examinees', 'error');
                return;
            }

            const capacityInfo = capacityResponse.data.success ? capacityResponse.data.data : null;
            if (!capacityInfo) {
                showAlert('Unable to load section capacity information', 'error');
                return;
            }

            if (capacityInfo.available_slots === 0) {
                Swal.fire(
                    'Section Full',
                    `Section capacity exceeded. Maximum: ${capacityInfo.capacity}, Currently Assigned: ${capacityInfo.assigned_count}.`,
                    'warning'
                );
                return;
            }

            const enrolledIds = new Set(
                (enrolledResponse.data.success ? enrolledResponse.data.data || [] : [])
                    .map(id => String(id))
            );

            const sectionUserIds = new Set(
                (capacityInfo.assigned_user_ids || []).map(id => String(id))
            );

            const examinees = usersResponse.data.data
                .filter(u => u.Role_Name === 'Examinee' && !enrolledIds.has(String(u.User_ID)));

            if (examinees.length === 0) {
                Swal.fire('No examinees available', 'All examinees are already assigned to a section, or no unassigned examinees exist.', 'info');
                return;
            }

            const checkboxes = examinees.map(e => `
                <div class="form-check text-start">
                    <input class="form-check-input examinee-checkbox" type="checkbox"
                        value="${e.User_ID}" id="examinee-${e.User_ID}">
                    <label class="form-check-label" for="examinee-${e.User_ID}">
                        ${escapeHtml(e.Fullname)} (${escapeHtml(e.Email)})
                    </label>
                </div>
            `).join('');

            Swal.fire({
                title: 'Assign Subject',
                html: `
                    <div class="text-start mb-2">
                        <small class="text-muted">
                            Section: <strong>${escapeHtml(capacityInfo.section_name)}</strong><br>
                            Capacity: <strong>${capacityInfo.capacity}</strong> |
                            Assigned: <strong>${capacityInfo.assigned_count}</strong> |
                            Available slots: <strong>${capacityInfo.available_slots}</strong>
                        </small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label mb-0">Select examinee(s)</label>
                        <div>
                            <button type="button" class="btn btn-sm btn-link p-0 me-2" id="selectAllExaminees">Select All</button>
                            <button type="button" class="btn btn-sm btn-link p-0" id="clearAllExaminees">Clear</button>
                        </div>
                    </div>
                    <div id="examineeCheckboxList" class="border rounded p-2 text-start"
                        style="max-height: 240px; overflow-y: auto;">
                        ${checkboxes}
                    </div>
                    <small class="text-muted d-block mt-2 text-start" id="examineeSelectionCount">0 selected</small>
                `,
                showCancelButton: true,
                confirmButtonText: 'Assign Selected',
                width: '34rem',
                didOpen: () => {
                    const updateCount = () => {
                        const count = document.querySelectorAll('.examinee-checkbox:checked').length;
                        const countEl = document.getElementById('examineeSelectionCount');
                        if (countEl) {
                            countEl.textContent = `${count} selected`;
                        }
                    };

                    document.querySelectorAll('.examinee-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateCount);
                    });

                    document.getElementById('selectAllExaminees')?.addEventListener('click', (e) => {
                        e.preventDefault();
                        document.querySelectorAll('.examinee-checkbox').forEach(cb => { cb.checked = true; });
                        updateCount();
                    });

                    document.getElementById('clearAllExaminees')?.addEventListener('click', (e) => {
                        e.preventDefault();
                        document.querySelectorAll('.examinee-checkbox').forEach(cb => { cb.checked = false; });
                        updateCount();
                    });
                },
                preConfirm: () => {
                    const selected = Array.from(document.querySelectorAll('.examinee-checkbox:checked'))
                        .map(cb => cb.value);
                    if (selected.length === 0) {
                        Swal.showValidationMessage('Please select at least one examinee');
                        return false;
                    }

                    const newAssignments = selected.filter(id => !sectionUserIds.has(String(id))).length;
                    if (newAssignments > capacityInfo.available_slots) {
                        Swal.showValidationMessage(
                            `Section capacity exceeded. Maximum: ${capacityInfo.capacity}, Currently Assigned: ${capacityInfo.assigned_count}.`
                        );
                        return false;
                    }

                    return selected;
                }
            }).then(result => {
                if (result.isConfirmed) {
                    axios.post(`${BATCHES_API}`, {
                        action: 'assign_users',
                        section_id: sectionId,
                        user_ids: result.value,
                        status: 'Active'
                    })
                    .then(response => {
                        const count = response.data.assigned_count ?? result.value.length;
                        showAlert(response.data.message || `${count} examinee(s) assigned successfully`, 'success');
                        viewSectionUsers(sectionId, { reload: true });
                    })
                    .catch(error => {
                        showAlert(error.response?.data?.message || 'Error assigning examinees', 'error');
                    });
                }
            });
        })
        .catch(error => {
            console.error('Error loading examinees:', error);
            showAlert('Error loading examinees', 'error');
        });
}

// Remove user from section
function removeFromSection(userId, sectionId) {
    Swal.fire({
        title: 'Remove from Section?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (result.isConfirmed) {
            axios.post(`${BATCHES_API}`, {
                action: 'remove_user',
                user_id: userId,
                section_id: sectionId
            })
            .then(() => {
                showAlert('User removed from section', 'success');
                viewSectionUsers(sectionId, { reload: true });
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Error removing user', 'error');
            });
        }
    });
}

// Load courses on page load
function bootstrapCoursesPage() {
    initModals();
    bindCourseForms();
    bindCourseActions();
    document.getElementById('filterCourse')?.addEventListener('change', function () {
        currentCourseFilter = this.value || '';
        renderFilteredCourses();
    });
    loadCourses();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapCoursesPage);
} else {
    bootstrapCoursesPage();
}
