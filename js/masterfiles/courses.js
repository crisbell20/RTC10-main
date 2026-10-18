const API_BASE = '../../api/masterfiles/';
const COURSES_API = API_BASE + 'courses.php';
const SECTIONS_API = API_BASE + 'sections.php';
const BATCHES_API = API_BASE + 'batches.php';
const USERS_API = API_BASE + 'users.php';
const EXAMINEE_EMAIL_SUFFIX = '@rtc.examinee.local';
const ENDING_SOON_DAYS = 30;

let addBatchModal = null;
let batchNamePreviewToken = 0;
let addSectionModal = null;
let sectionNameOptionsToken = 0;
let addCourseModal = null;
let allCourses = [];
let searchQuery = '';
let courseFilter = '';
let sectionTrainees = [];
let sectionTraineesError = '';
let sectionTraineesLoadedFor = '';
let sectionTraineesLoadingFor = '';
let coursesTreeLoaded = false;

const nav = {
    courseId: '',
    batchId: '',
    sectionId: '',
};

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
        <button type="button" class="btn btn-sm btn-link text-secondary staff-action-toggle" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" title="More actions">
            <i class="bi bi-three-dots-vertical"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">${menuItems}</ul>
    </div>`;
}

function currentLevel() {
    if (nav.sectionId) return 'trainees';
    if (nav.batchId) return 'sections';
    if (nav.courseId) return 'batches';
    return 'courses';
}

function findCourse(courseId) {
    return allCourses.find(c => String(c.Course_ID) === String(courseId)) || null;
}

function findBatch(batchId, course = null) {
    const courses = course ? [course] : allCourses;
    for (const item of courses) {
        for (const batch of item.batches || []) {
            if (String(batch.Batch_ID) === String(batchId)) {
                return { ...batch, Course_ID: item.Course_ID };
            }
        }
    }
    return null;
}

function findSection(sectionId, batch = null) {
    const batches = batch ? [batch] : allCourses.flatMap(course => (course.batches || []).map(item => ({
        ...item,
        Course_ID: course.Course_ID,
    })));
    for (const item of batches) {
        for (const section of item.sections || []) {
            if (String(section.Section_ID) === String(sectionId)) {
                return {
                    ...section,
                    Batch_ID: item.Batch_ID,
                    Course_ID: item.Course_ID,
                };
            }
        }
    }
    return null;
}

function parseDateOnly(value) {
    if (!value) return null;
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatBatchDate(value) {
    const date = parseDateOnly(value);
    if (!date) {
        return value ? escapeHtml(String(value).slice(0, 10)) : '';
    }
    return date.toLocaleDateString();
}

function isEndingSoon(batch) {
    const ended = parseDateOnly(batch?.Date_Ended);
    if (!ended) return false;
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const limit = new Date(today);
    limit.setDate(limit.getDate() + ENDING_SOON_DAYS);
    return ended >= today && ended <= limit;
}

function hasEndingSoonBatch(course) {
    return (course.batches || []).some(isEndingSoon);
}

function sectionStats(section) {
    const assigned = Number(section.assigned_count || 0);
    const capacity = Number(section.Capacity || 0);
    const available = Math.max(0, capacity - assigned);
    return { assigned, capacity, available };
}

function batchStats(batch) {
    const sections = Array.isArray(batch?.sections) ? batch.sections : [];
    return sections.reduce((acc, section) => {
        if (!section) return acc;
        const stats = sectionStats(section);
        acc.sections += 1;
        acc.assigned += stats.assigned;
        acc.capacity += stats.capacity;
        return acc;
    }, { sections: 0, assigned: 0, capacity: 0 });
}

function courseStats(course) {
    const batches = Array.isArray(course?.batches) ? course.batches : [];
    return batches.reduce((acc, batch) => {
        if (!batch) return acc;
        const stats = batchStats(batch);
        acc.batches += 1;
        acc.sections += stats.sections;
        acc.assigned += stats.assigned;
        acc.capacity += stats.capacity;
        return acc;
    }, { batches: 0, sections: 0, assigned: 0, capacity: 0 });
}

function matchesSearch(haystack) {
    const query = searchQuery.trim().toLowerCase();
    if (!query) return true;
    return String(haystack || '').toLowerCase().includes(query);
}

function displayEmail(email) {
    if (!email || String(email).trim().toLowerCase().endsWith(EXAMINEE_EMAIL_SUFFIX)) {
        return '';
    }
    return String(email);
}

function readNavFromHash() {
    const params = new URLSearchParams((location.hash || '').replace(/^#/, ''));
    nav.courseId = params.get('course') || '';
    nav.batchId = params.get('batch') || '';
    nav.sectionId = params.get('section') || '';
}

function normalizeNav() {
    if (nav.courseId && !findCourse(nav.courseId)) {
        nav.courseId = '';
        nav.batchId = '';
        nav.sectionId = '';
        return;
    }
    if (nav.batchId && !findBatch(nav.batchId, findCourse(nav.courseId))) {
        nav.batchId = '';
        nav.sectionId = '';
        return;
    }
    if (nav.sectionId && !findSection(nav.sectionId, findBatch(nav.batchId, findCourse(nav.courseId)))) {
        nav.sectionId = '';
    }
}

function hashFromNav(nextNav) {
    const params = new URLSearchParams();
    if (nextNav.courseId) params.set('course', nextNav.courseId);
    if (nextNav.batchId) params.set('batch', nextNav.batchId);
    if (nextNav.sectionId) params.set('section', nextNav.sectionId);
    const query = params.toString();
    return query ? `#${query}` : '';
}

function setNav(nextNav) {
    const next = {
        courseId: nextNav.courseId || '',
        batchId: nextNav.batchId || '',
        sectionId: nextNav.sectionId || '',
    };
    const nextHash = hashFromNav(next);
    const currentHash = location.hash || '';
    if (currentHash === nextHash || (nextHash === '' && currentHash === '#')) {
        nav.courseId = next.courseId;
        nav.batchId = next.batchId;
        nav.sectionId = next.sectionId;
        renderCurrentView();
        return;
    }
    location.hash = nextHash;
}

function setCoursesLoadError(message) {
    showCoursesLevel(true);
    const cards = document.getElementById('courseCards');
    if (cards) {
        cards.innerHTML = `<div class="curriculum-empty text-danger">${escapeHtml(message)}</div>`;
    }
}

function showCoursesLevel(isCourses) {
    const cards = document.getElementById('courseCards');
    const tableWrap = document.getElementById('curriculumTableWrap');
    if (cards) cards.hidden = !isCourses;
    if (tableWrap) tableWrap.hidden = isCourses;
}

function emptyCard(icon, message, actionLabel, action) {
    const button = actionLabel
        ? `<div class="mt-3"><button type="button" class="btn btn-sm btn-add-user" data-action="${action}">${escapeHtml(actionLabel)}</button></div>`
        : '';
    return `<div class="curriculum-empty">
        <i class="bi ${icon}"></i>
        <div>${escapeHtml(message)}</div>
        ${button}
    </div>`;
}

function loadingRow(colspan) {
    return `<tr><td colspan="${colspan}" class="text-center text-muted py-4"><i class="bi bi-hourglass-split"></i> Loading...</td></tr>`;
}

function emptyRow(colspan, icon, message, actionLabel, action) {
    const button = actionLabel
        ? `<div class="mt-3"><button type="button" class="btn btn-sm btn-add-user" data-action="${action}">${escapeHtml(actionLabel)}</button></div>`
        : '';
    return `<tr><td colspan="${colspan}" class="curriculum-empty">
        <i class="bi ${icon}"></i>
        <div>${escapeHtml(message)}</div>
        ${button}
    </td></tr>`;
}

function renderBreadcrumb() {
    const el = document.getElementById('curriculumBreadcrumb');
    if (!el) return;

    const course = findCourse(nav.courseId);
    const batch = nav.batchId ? findBatch(nav.batchId, course) : null;
    const section = nav.sectionId ? findSection(nav.sectionId, batch) : null;
    const parts = [];

    const pushSep = () => parts.push('<span class="crumb-sep">/</span>');

    if (!course) {
        el.innerHTML = '';
        return;
    } else {
        parts.push('<button type="button" data-nav="courses">Courses</button>');
        pushSep();
        if (!batch) {
            parts.push(`<span class="crumb-current">${escapeHtml(course.Course_Name)}</span>`);
        } else {
            parts.push(`<button type="button" data-nav="course" data-course-id="${course.Course_ID}">${escapeHtml(course.Course_Name)}</button>`);
            pushSep();
            if (!section) {
                parts.push(`<span class="crumb-current">${escapeHtml(batch.Batch_Name)}</span>`);
            } else {
                parts.push(`<button type="button" data-nav="batch" data-course-id="${course.Course_ID}" data-batch-id="${batch.Batch_ID}">${escapeHtml(batch.Batch_Name)}</button>`);
                pushSep();
                parts.push(`<span class="crumb-current">${escapeHtml(section.Section_Name)}</span>`);
            }
        }
    }

    el.innerHTML = parts.join('');
}

function renderSummary() {
    const el = document.getElementById('curriculumSummary');
    if (el) el.innerHTML = '';
}

function updateToolbar() {
    const level = currentLevel();
    const searchInput = document.getElementById('filterSearch');
    const courseWrap = document.getElementById('courseFilterWrap');
    const addBtn = document.getElementById('primaryAddBtn');
    const addLabel = document.getElementById('primaryAddLabel');

    const placeholders = {
        courses: 'Search courses',
        batches: 'Search batches',
        sections: 'Search sections',
        trainees: 'Search trainees',
    };
    if (searchInput) {
        searchInput.placeholder = placeholders[level] || 'Search';
    }
    if (courseWrap) {
        courseWrap.style.display = level === 'courses' ? '' : 'none';
    }
    populateCourseFilter();

    const labels = {
        courses: 'Add Course',
        batches: 'Add Batch',
        sections: 'Add Section',
        trainees: 'Assign Trainees',
    };
    if (addLabel) addLabel.textContent = labels[level];
    if (addBtn) addBtn.dataset.level = level;
}

function tableHead(level) {
    if (level === 'batches') {
        return `<tr>
            <th>Batch</th>
            <th>Period</th>
            <th>Sections</th>
            <th>Trainees</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
        </tr>`;
    }
    if (level === 'sections') {
        return `<tr>
            <th>Section</th>
            <th>Capacity</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
        </tr>`;
    }
    return `<tr>
        <th style="width: 3.5rem;">No.</th>
        <th>Trainee</th>
        <th>Badge No.</th>
        <th>Rank</th>
        <th class="text-end">Actions</th>
    </tr>`;
}

function populateCourseFilter() {
    const select = document.getElementById('filterCourse');
    if (!select) return;
    const selected = courseFilter;
    select.innerHTML = '<option value="">All courses</option>';
    allCourses.forEach(course => {
        select.add(new Option(course.Course_Name, course.Course_ID));
    });
    if (selected && [...select.options].some(opt => opt.value === String(selected))) {
        select.value = String(selected);
    } else {
        courseFilter = '';
        select.value = '';
    }
}

function renderCoursesCards() {
    let courses = allCourses.filter(course => matchesSearch(course.Course_Name));
    if (courseFilter) {
        courses = courses.filter(course => String(course.Course_ID) === String(courseFilter));
    }
    if (courses.length === 0) {
        const message = allCourses.length === 0 ? 'No courses yet' : 'No courses match your filters';
        const action = allCourses.length === 0 ? ['Add Course', 'add-course'] : null;
        return emptyCard('bi-book', message, action?.[0], action?.[1]);
    }

    return courses.map(course => {
        const stats = courseStats(course);
        const ending = hasEndingSoonBatch(course)
            ? '<span class="badge bg-warning text-dark">Ending soon</span>'
            : '';
        return `<div class="curriculum-course-card" tabindex="0" data-drill="course" data-course-id="${course.Course_ID}">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="flex-grow-1 pe-2">
                    <h5>${escapeHtml(course.Course_Name)}</h5>
                    ${course.Details ? `<div class="text-muted small mb-2">${escapeHtml(course.Details)}</div>` : ''}
                </div>
                <div class="staff-row-actions" data-stop-drill="1">
                    ${buildActionMenu([
                        { action: 'edit-course', attrs: `data-course-id="${course.Course_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-course', attrs: `data-course-id="${course.Course_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </div>
            <div>
                <div class="text-muted small mb-2">DETAILS</div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge bg-light text-dark border">${stats.batches} batch${stats.batches === 1 ? '' : 'es'}</span>
                    <span class="badge bg-light text-dark border">${stats.sections} section${stats.sections === 1 ? '' : 's'}</span>
                    <span class="badge bg-light text-dark border">${stats.assigned} trainee${stats.assigned === 1 ? '' : 's'}</span>
                    ${ending}
                </div>
            </div>
        </div>`;
    }).join('');
}

function renderBatchesRows() {
    const course = findCourse(nav.courseId);
    if (!course) {
        return emptyRow(6, 'bi-collection', 'Course not found');
    }
    const batches = (course.batches || []).filter(batch => matchesSearch(batch.Batch_Name));
    if (batches.length === 0) {
        const none = (course.batches || []).length === 0;
        return emptyRow(
            6,
            'bi-collection',
            none ? 'No batches yet' : 'No batches match your search',
            none ? 'Add Batch' : null,
            none ? 'add-batch' : null
        );
    }

    return batches.map(batch => {
        const stats = batchStats(batch);
        const periodParts = [];
        if (batch.Date_Started) periodParts.push(formatBatchDate(batch.Date_Started));
        if (batch.Date_Ended) periodParts.push(formatBatchDate(batch.Date_Ended));
        const period = periodParts.length ? periodParts.join(' – ') : '—';
        const status = isEndingSoon(batch)
            ? '<span class="badge bg-warning text-dark">Ending soon</span>'
            : '<span class="badge bg-success">Active</span>';

        return `<tr class="curriculum-row" tabindex="0" data-drill="batch" data-course-id="${course.Course_ID}" data-batch-id="${batch.Batch_ID}">
            <td>
                <div class="curriculum-name">${escapeHtml(batch.Batch_Name)}<i class="bi bi-chevron-right"></i></div>
            </td>
            <td>${period}</td>
            <td>${stats.sections}</td>
            <td>${stats.assigned}</td>
            <td>${status}</td>
            <td class="text-end" data-stop-drill="1">
                <div class="staff-row-actions justify-content-end">
                    ${buildActionMenu([
                        { action: 'edit-batch', attrs: `data-batch-id="${batch.Batch_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-batch', attrs: `data-batch-id="${batch.Batch_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderSectionsRows() {
    const course = findCourse(nav.courseId);
    const batch = findBatch(nav.batchId, course);
    if (!batch) {
        return emptyRow(4, 'bi-diagram-3', 'Batch not found');
    }
    const sections = (batch.sections || []).filter(section => matchesSearch(section.Section_Name));
    if (sections.length === 0) {
        const none = (batch.sections || []).length === 0;
        return emptyRow(
            4,
            'bi-diagram-3',
            none ? 'No sections yet' : 'No sections match your search',
            none ? 'Add Section' : null,
            none ? 'add-section' : null
        );
    }

    return sections.map(section => {
        const stats = sectionStats(section);
        const status = stats.capacity > 0 && stats.available === 0
            ? '<span class="badge bg-danger">Full</span>'
            : '<span class="badge bg-success">Open</span>';
        return `<tr class="curriculum-row" tabindex="0" data-drill="section" data-course-id="${course.Course_ID}" data-batch-id="${batch.Batch_ID}" data-section-id="${section.Section_ID}">
            <td>
                <div class="curriculum-name">${escapeHtml(section.Section_Name)}<i class="bi bi-chevron-right"></i></div>
            </td>
            <td>${stats.capacity || '—'}</td>
            <td>${status}</td>
            <td class="text-end" data-stop-drill="1">
                <div class="staff-row-actions justify-content-end">
                    ${buildActionMenu([
                        { action: 'edit-section', attrs: `data-section-id="${section.Section_ID}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                        { action: 'archive-section', attrs: `data-section-id="${section.Section_ID}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
                    ])}
                </div>
            </td>
        </tr>`;
    }).join('');
}

function renderTraineesRows() {
    if (sectionTraineesError) {
        return emptyRow(5, 'bi-exclamation-circle', sectionTraineesError);
    }
    const trainees = sectionTrainees
        .filter(user => {
            const email = displayEmail(user.Email);
            return matchesSearch(`${user.Fullname} ${user.Badge_Number || ''} ${user.Personnel_Rank || ''} ${email}`);
        })
        .slice()
        .sort((a, b) => String(a.Fullname || '').localeCompare(String(b.Fullname || ''), undefined, { sensitivity: 'base' }));
    if (trainees.length === 0) {
        const none = sectionTrainees.length === 0;
        return emptyRow(
            5,
            'bi-people',
            none ? 'No trainees assigned yet' : 'No trainees match your search',
            none ? 'Assign Trainees' : null,
            none ? 'assign-section-users' : null
        );
    }

    return trainees.map((user, index) => {
        const email = displayEmail(user.Email);
        return `<tr>
            <td class="text-muted">${index + 1}</td>
            <td>
                <div class="curriculum-name">${escapeHtml(user.Fullname)}</div>
                ${email ? `<div class="curriculum-muted">${escapeHtml(email)}</div>` : ''}
            </td>
            <td>${escapeHtml(user.Badge_Number || '—')}</td>
            <td>${escapeHtml(user.Personnel_Rank || '—')}</td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-section-user" data-user-id="${user.User_ID}" data-section-id="${nav.sectionId}" title="Remove">
                    <i class="bi bi-x-lg"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

function renderCurrentView() {
    const head = document.getElementById('curriculumTableHead');
    const body = document.getElementById('coursesContainer');
    if (!body) return;

    try {
        normalizeNav();
        const level = currentLevel();

        renderBreadcrumb();
        renderSummary();
        updateToolbar();
        showCoursesLevel(level === 'courses');

        if (level === 'courses') {
            const cards = document.getElementById('courseCards');
            if (cards) cards.innerHTML = renderCoursesCards();
            return;
        }

        if (head) head.innerHTML = tableHead(level);

        if (level === 'batches') body.innerHTML = renderBatchesRows();
        else if (level === 'sections') body.innerHTML = renderSectionsRows();
        else {
            if (sectionTraineesLoadedFor !== String(nav.sectionId)) {
                body.innerHTML = loadingRow(5);
                if (sectionTraineesLoadingFor !== String(nav.sectionId)) {
                    loadSectionTrainees(nav.sectionId);
                }
                return;
            }
            body.innerHTML = renderTraineesRows();
        }
    } catch (error) {
        console.error('Failed to render Manage Courses:', error);
        setCoursesLoadError('Unable to display courses. Please refresh the page.');
    }
}

function loadCourses(options = {}) {
    const { keepTrainees = false } = options;
    axios.get(`${COURSES_API}?action=tree`)
        .then(response => {
            const payload = response.data && typeof response.data === 'object' ? response.data : {};
            if (payload.success) {
                allCourses = Array.isArray(payload.data) ? payload.data : [];
                coursesTreeLoaded = true;
                if (!keepTrainees) {
                    sectionTrainees = [];
                    sectionTraineesError = '';
                    sectionTraineesLoadedFor = '';
                }
                readNavFromHash();
                renderCurrentView();
            } else {
                coursesTreeLoaded = true;
                setCoursesLoadError(payload.message || 'Failed to load courses');
                showAlert(payload.message || 'Failed to load courses', 'error');
            }
        })
        .catch(error => {
            coursesTreeLoaded = true;
            const msg = error.response?.data?.message || 'Error loading courses';
            setCoursesLoadError(msg);
            showAlert(msg, 'error');
            console.error(error);
        });
}

function loadSectionTrainees(sectionId) {
    if (!sectionId) return;
    const requestFor = String(sectionId);
    sectionTraineesError = '';
    sectionTraineesLoadingFor = requestFor;
    axios.get(`${BATCHES_API}?action=users_by_section&section_id=${sectionId}`)
        .then(response => {
            if (sectionTraineesLoadingFor === requestFor) {
                sectionTraineesLoadingFor = '';
            }
            if (String(nav.sectionId) !== requestFor) return;
            if (response.data.success) {
                sectionTrainees = response.data.data || [];
                sectionTraineesError = '';
            } else {
                sectionTrainees = [];
                sectionTraineesError = 'Failed to load assigned trainees';
            }
            sectionTraineesLoadedFor = requestFor;
            renderCurrentView();
        })
        .catch(error => {
            console.error('Error loading section users:', error);
            if (sectionTraineesLoadingFor === requestFor) {
                sectionTraineesLoadingFor = '';
            }
            if (String(nav.sectionId) !== requestFor) return;
            sectionTrainees = [];
            sectionTraineesError = 'Error loading assigned trainees';
            sectionTraineesLoadedFor = requestFor;
            renderCurrentView();
        });
}

function addCourse() {
    resetCourseModal();
    addCourseModal?.show();
}

function addBatch(courseId) {
    const courseIdInput = document.getElementById('batchCourseId');
    if (!courseIdInput) {
        showAlert('Batch form is not available', 'error');
        return;
    }
    resetBatchModal();
    courseIdInput.value = courseId || nav.courseId;
    if (!courseIdInput.value) {
        showAlert('Select a course first', 'error');
        return;
    }
    const nameInput = document.getElementById('batchName');
    const previewToken = ++batchNamePreviewToken;
    if (nameInput) {
        nameInput.readOnly = true;
        nameInput.value = 'Generating...';
    }
    addBatchModal?.show();
    axios.get(`${BATCHES_API}?action=next_batch_name&course_id=${encodeURIComponent(courseIdInput.value)}`)
        .then(response => {
            if (previewToken !== batchNamePreviewToken || !nameInput) {
                return;
            }
            nameInput.value = response.data?.batch_name || '';
        })
        .catch(() => {
            if (previewToken !== batchNamePreviewToken || !nameInput) {
                return;
            }
            nameInput.value = '';
            showAlert('Could not preview the batch name. It will still be assigned when you save.', 'error');
        });
}

function normalizeSectionName(name) {
    return String(name || '').trim().toLowerCase();
}

function setSectionNameOptions(allowedNames, usedNames, currentName) {
    const select = document.getElementById('sectionName');
    if (!select) return;

    const current = String(currentName || '').trim();
    const currentKey = normalizeSectionName(current);
    const used = new Set((usedNames || []).map(normalizeSectionName).filter(Boolean));
    const names = Array.isArray(allowedNames) ? allowedNames.slice() : [];
    if (current && !names.some(name => normalizeSectionName(name) === currentKey)) {
        names.push(current);
    }

    select.replaceChildren();
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select a section';
    select.appendChild(placeholder);

    names.forEach(name => {
        const label = String(name);
        const option = document.createElement('option');
        option.value = label;
        option.textContent = label;
        const key = normalizeSectionName(label);
        if (used.has(key) && key !== currentKey) {
            option.disabled = true;
        }
        select.appendChild(option);
    });

    select.value = current && Array.from(select.options).some(option => option.value === current) ? current : '';
}

function loadSectionNameOptions(batchId, currentName) {
    const token = ++sectionNameOptionsToken;
    setSectionNameOptions([], [], '');
    axios.get(`${SECTIONS_API}?action=name_options&batch_id=${encodeURIComponent(batchId)}`)
        .then(response => {
            if (token !== sectionNameOptionsToken) return;
            if (!response.data?.success || !Array.isArray(response.data.allowed_names)) {
                setSectionNameOptions([], [], currentName);
                showAlert('Could not load section names. Close the form and try again.', 'error');
                return;
            }
            setSectionNameOptions(response.data.allowed_names, response.data.used_names || [], currentName);
        })
        .catch(() => {
            if (token !== sectionNameOptionsToken) return;
            setSectionNameOptions([], [], currentName);
            showAlert(currentName
                ? 'Could not load the full section list. You can save the current name, or close the form and try again.'
                : 'Could not load section names. Close the form and try again.', 'error');
        });
}

function addSection(batchId) {
    const batchIdInput = document.getElementById('sectionBatchId');
    if (!batchIdInput) {
        showAlert('Section form is not available', 'error');
        return;
    }
    resetSectionModal();
    batchIdInput.value = batchId || nav.batchId;
    if (!batchIdInput.value) {
        showAlert('Select a batch first', 'error');
        return;
    }
    loadSectionNameOptions(batchIdInput.value, '');
    addSectionModal?.show();
}

function resetCourseModal() {
    const form = document.getElementById('addCourseForm');
    form?.reset();
    document.getElementById('editCourseId').value = '';
    document.getElementById('courseModalTitle').textContent = 'Add New Course';
    document.getElementById('courseSubmitBtn').textContent = 'Add Course';
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('courseFormAlert', '');
}

function resetBatchModal() {
    batchNamePreviewToken++;
    const form = document.getElementById('addBatchForm');
    form?.reset();
    document.getElementById('editBatchId').value = '';
    const nameInput = document.getElementById('batchName');
    if (nameInput) {
        nameInput.readOnly = true;
        nameInput.value = '';
    }
    document.getElementById('batchModalTitle').textContent = 'Add Batch to Course';
    document.getElementById('batchSubmitBtn').textContent = 'Add Batch';
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('batchFormAlert', '');
}

function resetSectionModal() {
    sectionNameOptionsToken++;
    const form = document.getElementById('addSectionForm');
    form?.reset();
    document.getElementById('editSectionId').value = '';
    setSectionNameOptions([], [], '');
    document.getElementById('sectionModalTitle').textContent = 'Add Section to Batch';
    document.getElementById('sectionSubmitBtn').textContent = 'Add Section';
    if (form) ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('sectionFormAlert', '');
}

function editCourse(courseId) {
    const course = findCourse(courseId);
    if (!course) return;
    document.getElementById('editCourseId').value = course.Course_ID;
    document.getElementById('courseName').value = course.Course_Name || '';
    document.querySelector('#addCourseForm [name=details]').value = course.Details || '';
    document.getElementById('courseModalTitle').textContent = 'Edit Course';
    document.getElementById('courseSubmitBtn').textContent = 'Save Changes';
    addCourseModal?.show();
}

function editBatch(batchId) {
    const batch = findBatch(batchId);
    if (!batch) return;
    batchNamePreviewToken++;
    document.getElementById('editBatchId').value = batch.Batch_ID;
    document.getElementById('batchCourseId').value = batch.Course_ID;
    const nameInput = document.getElementById('batchName');
    if (nameInput) {
        nameInput.readOnly = true;
        nameInput.value = batch.Batch_Name || '';
    }
    document.querySelector('#addBatchForm [name=date_started]').value = batch.Date_Started ? String(batch.Date_Started).slice(0, 10) : '';
    document.querySelector('#addBatchForm [name=date_ended]').value = batch.Date_Ended ? String(batch.Date_Ended).slice(0, 10) : '';
    document.getElementById('batchModalTitle').textContent = 'Edit Batch';
    document.getElementById('batchSubmitBtn').textContent = 'Save Changes';
    addBatchModal?.show();
}

function editSection(sectionId) {
    const section = findSection(sectionId);
    if (!section) return;
    const batchId = section.Batch_ID || '';
    if (!batchId) {
        showAlert('Select a batch first', 'error');
        return;
    }
    sectionNameOptionsToken++;
    document.getElementById('editSectionId').value = section.Section_ID;
    document.getElementById('sectionBatchId').value = batchId;
    document.getElementById('sectionModalTitle').textContent = 'Edit Section';
    document.getElementById('sectionSubmitBtn').textContent = 'Save Changes';
    loadSectionNameOptions(batchId, section.Section_Name || '');
    addSectionModal?.show();
}

function archiveCourse(courseId) {
    Swal.fire({
        title: 'Archive Course?',
        html: 'The course and all its <strong>batches, sections, modules, and subjects</strong> will be archived.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
    }).then(result => {
        if (!result.isConfirmed) return;
        axios.post(COURSES_API, { action: 'archive', course_id: courseId })
            .then(res => {
                showAlert(res.data.message || 'Course archived successfully', 'success');
                if (String(nav.courseId) === String(courseId)) {
                    setNav({});
                }
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
                if (String(nav.batchId) === String(batchId)) {
                    setNav({ courseId: nav.courseId });
                }
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
                if (String(nav.sectionId) === String(sectionId)) {
                    setNav({ courseId: nav.courseId, batchId: nav.batchId });
                }
                loadCourses();
            })
            .catch(error => showAlert(error.response?.data?.message || 'Error archiving section', 'error'));
    });
}

function handlePrimaryAdd() {
    const level = currentLevel();
    if (level === 'courses') addCourse();
    else if (level === 'batches') addBatch(nav.courseId);
    else if (level === 'sections') addSection(nav.batchId);
    else assignUserToSection(nav.sectionId);
}

function drillFromRow(row) {
    const type = row.dataset.drill;
    if (type === 'course') {
        searchQuery = '';
        courseFilter = '';
        syncFilterInputs();
        sectionTrainees = [];
        sectionTraineesLoadedFor = '';
        setNav({ courseId: row.dataset.courseId });
    } else if (type === 'batch') {
        searchQuery = '';
        courseFilter = '';
        syncFilterInputs();
        sectionTrainees = [];
        sectionTraineesLoadedFor = '';
        setNav({ courseId: row.dataset.courseId, batchId: row.dataset.batchId });
    } else if (type === 'section') {
        searchQuery = '';
        syncFilterInputs();
        sectionTrainees = [];
        sectionTraineesError = '';
        sectionTraineesLoadedFor = '';
        setNav({
            courseId: row.dataset.courseId,
            batchId: row.dataset.batchId,
            sectionId: row.dataset.sectionId,
        });
    }
}

function syncFilterInputs() {
    const searchInput = document.getElementById('filterSearch');
    const courseSelect = document.getElementById('filterCourse');
    if (searchInput) searchInput.value = searchQuery;
    if (courseSelect) courseSelect.value = courseFilter;
}

function bindCourseForms() {
    const addCourseForm = document.getElementById('addCourseForm');
    const addBatchForm = document.getElementById('addBatchForm');
    const addSectionForm = document.getElementById('addSectionForm');

    if (!addCourseForm || !addBatchForm || !addSectionForm) {
        console.error('Manage Courses forms are missing from the page');
        return;
    }

    ModalFormValidation.bindModalFieldErrorClear(addCourseForm);
    ModalFormValidation.bindModalFieldErrorClear(addBatchForm);
    ModalFormValidation.bindModalFieldErrorClear(addSectionForm);

    addCourseForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.target;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const courseName = document.getElementById('courseName')?.value.trim() || '';
        const fieldErrors = {};
        if (!courseName) {
            fieldErrors.courseName = 'Course name is required';
        }
        if (Object.keys(fieldErrors).length > 0) {
            ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'courseFormAlert' });
            return;
        }
        ModalFormValidation.clearModalFieldErrors(form);
        ModalFormValidation.setSummaryAlert('courseFormAlert', '');

        const formData = new FormData(this);
        const courseId = formData.get('course_id');
        const data = {
            course_name: formData.get('course_name'),
            details: formData.get('details'),
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

    addBatchForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(this);
        const courseId = formData.get('course_id');
        const batchId = formData.get('batch_id');
        const fieldErrors = {};

        if (!batchId && !courseId) {
            fieldErrors.batchName = 'Course is missing. Close the modal and click Add Batch again.';
        }

        if (Object.keys(fieldErrors).length > 0) {
            ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'batchFormAlert' });
            return;
        }
        ModalFormValidation.clearModalFieldErrors(form);
        ModalFormValidation.setSummaryAlert('batchFormAlert', '');

        const data = {
            course_id: courseId,
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

    addSectionForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(this);
        const batchId = formData.get('batch_id');
        const sectionId = formData.get('section_id');
        const sectionName = formData.get('section_name');
        const selectedOption = document.getElementById('sectionName')?.selectedOptions?.[0];
        const fieldErrors = {};

        if (!batchId) {
            fieldErrors.sectionName = 'Batch is missing. Close the modal and click Add Section again.';
        } else if (!sectionName || !String(sectionName).trim()) {
            fieldErrors.sectionName = 'Section name is required';
        } else if (selectedOption?.disabled) {
            fieldErrors.sectionName = 'That section name is already used in this batch';
        }

        if (Object.keys(fieldErrors).length > 0) {
            ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'sectionFormAlert' });
            return;
        }
        ModalFormValidation.clearModalFieldErrors(form);
        ModalFormValidation.setSummaryAlert('sectionFormAlert', '');

        const data = {
            batch_id: batchId,
            section_name: sectionName,
            action: sectionId ? 'update' : 'add',
        };
        if (!sectionId) {
            data.capacity = 50;
        }
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
    const container = document.getElementById('curriculumList') || document.getElementById('coursesContainer');
    if (!container) return;

    container.addEventListener('click', function (e) {
        const actionBtn = e.target.closest('[data-action]');
        if (actionBtn) {
            e.preventDefault();
            e.stopPropagation();
            const action = actionBtn.dataset.action;
            if (action === 'add-course') addCourse();
            else if (action === 'add-batch') addBatch(actionBtn.dataset.courseId || nav.courseId);
            else if (action === 'add-section') addSection(actionBtn.dataset.batchId || nav.batchId);
            else if (action === 'edit-course') editCourse(actionBtn.dataset.courseId);
            else if (action === 'archive-course') archiveCourse(actionBtn.dataset.courseId);
            else if (action === 'edit-batch') editBatch(actionBtn.dataset.batchId);
            else if (action === 'archive-batch') archiveBatch(actionBtn.dataset.batchId);
            else if (action === 'edit-section') editSection(actionBtn.dataset.sectionId);
            else if (action === 'archive-section') archiveSection(actionBtn.dataset.sectionId);
            else if (action === 'assign-section-users') assignUserToSection(actionBtn.dataset.sectionId || nav.sectionId);
            else if (action === 'remove-section-user') removeFromSection(actionBtn.dataset.userId, actionBtn.dataset.sectionId);
            return;
        }

        if (e.target.closest('[data-stop-drill]')) return;
        const target = e.target.closest('[data-drill]');
        if (target) drillFromRow(target);
    });

    container.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const target = e.target.closest('[data-drill]');
        if (!target) return;
        e.preventDefault();
        drillFromRow(target);
    });
}

function assignUserToSection(sectionId) {
    if (!sectionId) return;
    Promise.all([
        axios.get(`${BATCHES_API}?action=section_capacity&section_id=${sectionId}`),
        axios.get(`${BATCHES_API}?action=enrolled_examinee_ids`),
        axios.get(`${USERS_API}?action=list`)
    ])
        .then(([capacityResponse, enrolledResponse, usersResponse]) => {
            if (!usersResponse.data.success) {
                showAlert('Error loading trainees', 'error');
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
                Swal.fire('No trainees available', 'All trainees are already assigned to a section, or no unassigned trainees exist.', 'info');
                return;
            }

            const checkboxes = examinees.map(e => {
                const extra = e.Badge_Number
                    ? escapeHtml(e.Badge_Number)
                    : escapeHtml(displayEmail(e.Email) || '');
                return `
                <div class="form-check text-start">
                    <input class="form-check-input examinee-checkbox" type="checkbox"
                        value="${e.User_ID}" id="examinee-${e.User_ID}">
                    <label class="form-check-label" for="examinee-${e.User_ID}">
                        ${escapeHtml(e.Fullname)}${extra ? ` (${extra})` : ''}
                    </label>
                </div>`;
            }).join('');

            Swal.fire({
                title: 'Assign Trainees',
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
                        <label class="form-label mb-0">Select trainee(s)</label>
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
                        if (countEl) countEl.textContent = `${count} selected`;
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
                        Swal.showValidationMessage('Please select at least one trainee');
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
                        showAlert(response.data.message || `${count} trainee(s) assigned successfully`, 'success');
                        loadCourses({ keepTrainees: false });
                    })
                    .catch(error => {
                        showAlert(error.response?.data?.message || 'Error assigning trainees', 'error');
                    });
                }
            });
        })
        .catch(error => {
            console.error('Error loading examinees:', error);
            showAlert('Error loading trainees', 'error');
        });
}

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
                loadCourses({ keepTrainees: false });
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Error removing user', 'error');
            });
        }
    });
}

function bootstrapCoursesPage() {
    loadCourses();

    try {
        initModals();
        bindCourseForms();
        bindCourseActions();

        document.getElementById('primaryAddBtn')?.addEventListener('click', handlePrimaryAdd);
        document.getElementById('filterSearch')?.addEventListener('input', function () {
            searchQuery = this.value || '';
            if (coursesTreeLoaded) renderCurrentView();
        });
        document.getElementById('filterCourse')?.addEventListener('change', function () {
            courseFilter = this.value || '';
            if (coursesTreeLoaded) renderCurrentView();
        });
        document.getElementById('curriculumBreadcrumb')?.addEventListener('click', function (e) {
            const button = e.target.closest('[data-nav]');
            if (!button) return;
            searchQuery = '';
            courseFilter = '';
            syncFilterInputs();
            sectionTrainees = [];
            sectionTraineesError = '';
            sectionTraineesLoadedFor = '';
            const type = button.dataset.nav;
            if (type === 'courses') setNav({});
            else if (type === 'course') setNav({ courseId: button.dataset.courseId });
            else if (type === 'batch') setNav({ courseId: button.dataset.courseId, batchId: button.dataset.batchId });
        });

        window.addEventListener('hashchange', function () {
            if (!coursesTreeLoaded) return;
            sectionTrainees = [];
            sectionTraineesError = '';
            sectionTraineesLoadedFor = '';
            readNavFromHash();
            renderCurrentView();
        });
    } catch (error) {
        console.error('Failed to initialize Manage Courses:', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapCoursesPage);
} else {
    bootstrapCoursesPage();
}
