const API_BASE = '../../api/masterfiles/users.php';
const BATCHES_API = '../../api/masterfiles/batches.php';
const COURSES_API = '../../api/masterfiles/courses.php';
const EXAMINEE_EMAIL_SUFFIX = '@rtc.examinee.local';
const BADGE_NUMBER_PATTERN = /^[0-9]{6,}$/;
const BADGE_NUMBER_FORMAT_MESSAGE = 'Badge number must be at least 6 digits';
let addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
let bulkImportModal = null;
let assignBatchModal = null;
let personnelRankOptions = [];

// Keep a copy of all users for filtering
let allUsers = [];
let currentRoleFilter = 'All';
let currentStatusFilter = 'All';
let currentSearch = '';
let assignCourseTree = [];
let importCourseTree = [];
let parsedImportRows = [];
let importPreviewData = null;
let adminPasswordPolicyChecklistEl = null;

function initAdminPasswordPolicyValidation() {
    adminPasswordPolicyChecklistEl = mountPasswordPolicyChecklist('adminPasswordPolicyChecklist', 'adminPwdPolicyChecklist');
    const passwordInput = document.getElementById('password');
    bindPasswordFieldValidation(passwordInput, adminPasswordPolicyChecklistEl);
}

function formatEmailForDisplay(email) {
    if (!email || String(email).trim().toLowerCase().endsWith(EXAMINEE_EMAIL_SUFFIX)) {
        return '<small class="text-muted">—</small>';
    }
    return escapeHtml(email);
}

function getSelectedRoleName() {
    const roleSelect = document.getElementById('role_id');
    if (!roleSelect || !roleSelect.value) return '';
    const selected = roleSelect.options[roleSelect.selectedIndex];
    return (selected?.dataset.roleName || selected?.textContent || '').trim();
}

function updateUserFormByRole(roleName) {
    const isExaminee = roleName === 'Examinee';
    const emailInput = document.getElementById('email');
    const badgeInput = document.getElementById('badge_number');
    const emailRequiredMark = document.getElementById('emailRequiredMark');
    const badgeRequiredMark = document.getElementById('badgeRequiredMark');
    const editUserId = document.getElementById('editUserId')?.value;
    const enrollmentSection = document.getElementById('addUserEnrollmentSection');
    const sectionSelect = document.getElementById('addUserSectionSelect');

    if (badgeInput) {
        badgeInput.required = isExaminee;
    }
    if (emailInput) {
        emailInput.required = !isExaminee;
    }
    if (emailRequiredMark) {
        emailRequiredMark.style.display = isExaminee ? 'none' : '';
    }
    if (badgeRequiredMark) {
        badgeRequiredMark.style.display = isExaminee ? '' : 'none';
    }

    if (enrollmentSection) {
        const showEnrollment = isExaminee && !editUserId;
        enrollmentSection.style.display = showEnrollment ? '' : 'none';
        if (showEnrollment && assignCourseTree.length === 0) {
            loadAddUserCourseTree();
        } else if (showEnrollment) {
            populateAddUserCourses();
        }
    }
    if (sectionSelect) {
        sectionSelect.required = isExaminee && !editUserId;
    }

    const passwordInput = document.getElementById('password');
    if (passwordInput && !editUserId) {
        if (isExaminee) {
            passwordInput.value = '';
            passwordInput.readOnly = true;
            passwordInput.required = false;
        } else {
            passwordInput.readOnly = false;
            passwordInput.required = true;
            passwordInput.value = '';
        }
        refreshPasswordFieldValidation(passwordInput, adminPasswordPolicyChecklistEl);
    }
}


// Load personnel rank options
function loadPersonnelRanks() {
    return axios.get(`${API_BASE}?action=personnel_ranks`)
        .then(response => {
            if (response.data.success) {
                personnelRankOptions = response.data.data || [];
                populatePersonnelRankSelect();
            }
        })
        .catch(error => {
            console.error('Error loading personnel ranks:', error);
        });
}

function populatePersonnelRankSelect(selectedValue) {
    const select = document.getElementById('personnel_rank');
    if (!select) return;

    const current = selectedValue !== undefined ? selectedValue : select.value;
    select.innerHTML = '<option value="">— None —</option>';
    personnelRankOptions.forEach(rank => {
        const option = document.createElement('option');
        option.value = rank;
        option.textContent = rank;
        select.appendChild(option);
    });
    if (current && !personnelRankOptions.includes(current)) {
        const custom = document.createElement('option');
        custom.value = current;
        custom.textContent = current;
        select.appendChild(custom);
    }
    select.value = current || '';
}

// Load users data on page load
function loadUsers() {
    axios.get(`${API_BASE}?action=list`)
        .then(response => {
            if (response.data.success) {
                allUsers = response.data.data || [];
                applyUserFilters();
            }
        })
        .catch(error => {
            showAlert('Error loading users', 'error');
            console.error(error);
        });
}

// Load roles
function loadRoles() {
    axios.get(`${API_BASE}?action=roles`)
        .then(response => {
            if (response.data.success && response.data.data && response.data.data.length > 0) {
                const roleSelect = document.getElementById('role_id');
                roleSelect.innerHTML = '<option value="">Select a role</option>';
                response.data.data.forEach(role => {
                    const option = document.createElement('option');
                    option.value = role.Role_ID;
                    option.textContent = role.Role_Name;
                    option.dataset.roleName = role.Role_Name;
                    roleSelect.appendChild(option);
                });
            } else {
                console.error('Roles data is empty or success is false', response.data);
            }
        })
        .catch(error => {
            console.error('Error loading roles:', error);
            console.error('Error response:', error.response?.data);
        });
}

// Render users table
function renderUsersTable(users) {
    const tbody = document.getElementById('usersTableBody');
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No users found</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => {
        const batchInfo = user.Role_Name === 'Examinee' && user.batches && user.batches.length > 0
            ? (() => {
                const activeBatches = user.batches.filter(b => b.Status === 'Active');
                const batches = activeBatches.length > 0 ? activeBatches : user.batches;
                const primary = batches[0];
                const extra = batches.length > 1
                    ? `<small class="text-warning d-block">+${batches.length - 1} more assignment(s) — remove extras manually</small>`
                    : '';
                return `<small class="d-block">${escapeHtml(primary.Course_Name)} → ${escapeHtml(primary.Section_Name || '—')} → ${escapeHtml(primary.Batch_Name)}</small>${extra}`;
            })()
            : user.Role_Name === 'Examinee' 
                ? '<small class="text-muted">Not assigned</small>'
                : '<small class="text-muted">—</small>';

        const rankDisplay = user.Personnel_Rank
            ? escapeHtml(user.Personnel_Rank)
            : '<small class="text-muted">—</small>';
        
        return `
        <tr>
            <td><strong>${escapeHtml(user.Fullname)}</strong></td>
            <td>${formatEmailForDisplay(user.Email)}</td>
            <td>${user.Badge_Number ? escapeHtml(user.Badge_Number) : '<small class="text-muted">—</small>'}</td>
            <td>${rankDisplay}</td>
            <td><span class="badge badge-info">${escapeHtml(user.Role_Name)}</span></td>
            <td>${batchInfo}</td>
            <td>
                <span class="status-badge ${user.Status === 'Active' ? 'bg-success text-white' : 'bg-warning'}">
                    ${escapeHtml(user.Status)}
                </span>
            </td>
            <td>${new Date(user.Date_Created).toLocaleDateString()}</td>
            <td>
                <div class="dropdown staff-action-menu">
                    <button class="btn btn-sm btn-link text-secondary staff-action-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More actions">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        ${user.Role_Name === 'Examinee' ? `
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); assignToBatch(${user.User_ID})">
                                <i class="bi bi-person-plus me-2"></i>Assign
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        ` : ''}
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); editUser(${user.User_ID})">
                                <i class="bi bi-pencil me-2"></i>Edit User
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); archiveUser(${user.User_ID})">
                                <i class="bi bi-archive me-2"></i>Archive User
                            </a>
                        </li>
                    </ul>
                </div>
            </td>
        </tr>
    `;
    }).join('');
}

// Apply filters to allUsers and re-render
function applyUserFilters() {
    let filtered = allUsers.slice();

    if (currentRoleFilter !== 'All') {
        filtered = filtered.filter(u => u.Role_Name === currentRoleFilter);
    }

    if (currentStatusFilter !== 'All') {
        filtered = filtered.filter(u => u.Status === currentStatusFilter);
    }

    if (currentSearch.trim() !== '') {
        const term = currentSearch.trim().toLowerCase();
        filtered = filtered.filter(u =>
            (u.Fullname && u.Fullname.toLowerCase().includes(term)) ||
            (u.Email && u.Email.toLowerCase().includes(term)) ||
            (u.Badge_Number && u.Badge_Number.toLowerCase().includes(term))
        );
    }

    renderUsersTable(filtered);
}

function bootstrapAlertClass(type) {
    if (type === 'error' || type === 'danger') return 'danger';
    if (type === 'warning') return 'warning';
    if (type === 'info') return 'info';
    return 'success';
}

// Show alert
function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.innerHTML = `<div class="alert alert-${bootstrapAlertClass(type)} alert-dismissible fade show" role="alert">
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
}

function isAddUserModalOpen() {
    const modalEl = document.getElementById('addUserModal');
    return modalEl?.classList.contains('show');
}

function showAddUserAlert(message, type = 'error') {
    const alertDiv = document.getElementById('addUserMessageAlert');
    if (!alertDiv) return;

    alertDiv.innerHTML = `<div class="alert alert-${bootstrapAlertClass(type)} alert-dismissible fade show" role="alert">
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    alertDiv.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function clearAddUserAlert() {
    const alertDiv = document.getElementById('addUserMessageAlert');
    if (alertDiv) alertDiv.innerHTML = '';
}

function showFormAlert(message, type = 'success') {
    if (isAddUserModalOpen()) {
        showAddUserAlert(message, type);
    } else {
        showAlert(message, type);
    }
}

function namePartError(value, label, required) {
    const namePattern = /^[\p{L}\s.,\-]+$/u;
    if (!value) {
        return required ? `${label} is required` : '';
    }
    if (!namePattern.test(value) || !/\p{L}/u.test(value)) {
        return `${label} must contain only letters, spaces, dots, commas, and hyphens`;
    }
    if (required && value.length < 2) {
        return `${label} must be at least 2 characters`;
    }
    if (value.length > 100) {
        return `${label} must be at most 100 characters`;
    }
    return '';
}

function validateAddUserFieldErrors() {
    const errors = {};
    const firstname = document.getElementById('firstname').value.trim();
    const lastname = document.getElementById('lastname').value.trim();
    const middlename = document.getElementById('middlename').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const roleName = getSelectedRoleName();
    const isExaminee = roleName === 'Examinee';
    const editUserIdCheck = document.getElementById('editUserId').value;

    const firstNameError = namePartError(firstname, 'First name', true);
    const lastNameError = namePartError(lastname, 'Last name', true);
    const middleNameError = namePartError(middlename, 'Middle name', false);
    if (firstNameError) errors.firstname = firstNameError;
    if (lastNameError) errors.lastname = lastNameError;
    if (middleNameError) errors.middlename = middleNameError;

    if (isExaminee) {
        const badgeNumber = document.getElementById('badge_number').value.trim();
        if (!badgeNumber) {
            errors.badge_number = 'Badge number is required';
        } else if (!BADGE_NUMBER_PATTERN.test(badgeNumber)) {
            errors.badge_number = BADGE_NUMBER_FORMAT_MESSAGE;
        }
        if (email) {
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                errors.email = 'Please enter a valid email address';
            }
        }
        if (!editUserIdCheck && !document.getElementById('addUserSectionSelect')?.value) {
            errors.addUserEnrollmentSection = 'Please select a course, batch, and section for the trainee';
        }
    } else {
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email) {
            errors.email = 'Email is required';
        } else if (!emailPattern.test(email)) {
            errors.email = 'Please enter a valid email address';
        }
    }

    if (!document.getElementById('role_id').value) {
        errors.role_id = 'Role is required';
    }

    if (!editUserIdCheck || (password && password !== '********')) {
        const skipDefaultExamineePassword = !editUserIdCheck && isExaminee && !password;
        if (!skipDefaultExamineePassword) {
            const passwordError = validatePasswordPolicy(password);
            if (passwordError) {
                errors.password = passwordError;
            }
        }
    }

    return errors;
}

function validateAssignBatchFieldErrors() {
    const errors = {};
    if (!document.getElementById('assignCourseSelect')?.value) {
        errors.assignCourseSelect = 'Please select a course';
    }
    if (!document.getElementById('assignParentBatchSelect')?.value) {
        errors.assignParentBatchSelect = 'Please select a batch';
    }
    if (!document.getElementById('assignSectionSelect')?.value) {
        errors.assignSectionSelect = 'Please select a section';
    }
    return errors;
}

// Add user form submit
const addUserFormEl = document.getElementById('addUserForm');
if (addUserFormEl) {
ModalFormValidation.bindModalFieldErrorClear(addUserFormEl);
addUserFormEl.addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const fieldErrors = validateAddUserFieldErrors();
    if (Object.keys(fieldErrors).length > 0) {
        ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'addUserMessageAlert' });
        return;
    }
    ModalFormValidation.clearModalFieldErrors(form);
    clearAddUserAlert();
    
    // Get form values
    const email = document.getElementById('email').value.trim();
    const badgeNumber = document.getElementById('badge_number').value.trim();
    const password = document.getElementById('password').value;
    const roleName = getSelectedRoleName();
    const isExaminee = roleName === 'Examinee';
    const editUserIdCheck = document.getElementById('editUserId').value;
    
    const formData = new FormData(this);
    const editUserId = formData.get('edit_user_id');

    // Common fields
    const payload = {
        firstname: formData.get('firstname'),
        lastname: formData.get('lastname'),
        middlename: formData.get('middlename'),
        email: formData.get('email'),
        badge_number: isExaminee ? badgeNumber : '',
        personnel_rank: formData.get('personnel_rank'),
        role_id: formData.get('role_id')
    };

    // Decide between add vs update
    if (editUserId) {
        payload.action = 'update';
        payload.user_id = editUserId;
        payload.status = document.getElementById('user_status').value || 'Active';

        // Check if password field has a value (not empty and not placeholder)
        const passwordValue = formData.get('password');
        if (passwordValue && passwordValue.trim() !== '' && passwordValue !== '********') {
            payload.new_password = passwordValue;
        }

        axios.post(API_BASE, payload)
            .then(response => {
                if (response.data.success) {
                    showAlert('User updated successfully', 'success');
                    this.reset();
                    addUserModal.hide();
                    loadUsers();
                }
            })
            .catch(error => {
                const message = error.response?.data?.message || 'Error updating user';
                showFormAlert(message, 'error');
            });
    } else {
        payload.action = 'add';
        payload.password = formData.get('password');
        const sectionId = document.getElementById('addUserSectionSelect')?.value;
        if (isExaminee && sectionId) {
            payload.section_id = sectionId;
        }

        axios.post(API_BASE, payload)
            .then(response => {
                if (response.data.success) {
                    showAlert('User added successfully', 'success');
                    this.reset();
                    addUserModal.hide();
                    loadUsers();
                }
            })
            .catch(error => {
                const message = error.response?.data?.message || 'Error adding user';
                showFormAlert(message, 'error');
            });
    }
});
}

function resetAddUserModal() {
    const form = document.getElementById('addUserForm');
    const passwordInput = document.getElementById('password');
    const passwordOptionalLabel = document.getElementById('passwordOptionalLabel');
    const passwordHint = document.getElementById('passwordHint');
    const editIdInput = document.getElementById('editUserId');
    const submitBtn = document.getElementById('addUserSubmitBtn');
    const titleEl = document.getElementById('addUserModalLabel');
    const userStatusSection = document.getElementById('userStatusSection');

    if (form) form.reset();
    if (editIdInput) editIdInput.value = '';
    if (passwordInput) {
        passwordInput.readOnly = false;
        passwordInput.value = '';
        passwordInput.required = true;
        passwordInput.placeholder = '';
        setInputValidationState(passwordInput, null);
    }
    if (passwordOptionalLabel) {
        passwordOptionalLabel.style.display = 'none';
    }
    if (passwordHint) {
        passwordHint.textContent = '8+ characters with upper, lower, number, and special character';
    }
    if (submitBtn) submitBtn.textContent = 'Add User';
    if (titleEl) titleEl.textContent = 'Add New User';
    if (userStatusSection) userStatusSection.style.display = 'none';

    clearAddUserAlert();
    populatePersonnelRankSelect('');
    resetAddUserEnrollmentSelects();
    updateUserFormByRole('');
    initAdminPasswordPolicyValidation();
}

function resetAddUserEnrollmentSelects() {
    const courseSelect = document.getElementById('addUserCourseSelect');
    const batchSelect = document.getElementById('addUserBatchSelect');
    const sectionSelect = document.getElementById('addUserSectionSelect');

    if (courseSelect) {
        courseSelect.innerHTML = '<option value="">Select a course</option>';
    }
    if (batchSelect) {
        batchSelect.innerHTML = '<option value="">Select a batch</option>';
        batchSelect.disabled = true;
    }
    if (sectionSelect) {
        sectionSelect.innerHTML = '<option value="">Select a section</option>';
        sectionSelect.disabled = true;
    }
}

function loadAddUserCourseTree() {
    return axios.get(`${COURSES_API}?action=tree`)
        .then(response => {
            if (response.data.success) {
                assignCourseTree = response.data.data || [];
                populateAddUserCourses();
            }
        })
        .catch(error => {
            console.error('Error loading courses for add user:', error);
        });
}

function populateAddUserCourses() {
    const courseSelect = document.getElementById('addUserCourseSelect');
    if (!courseSelect) return;

    resetAddUserEnrollmentSelects();
    assignCourseTree.forEach(course => {
        const option = document.createElement('option');
        option.value = course.Course_ID;
        option.textContent = course.Course_Name;
        courseSelect.appendChild(option);
    });
}

function populateAddUserBatches(courseId) {
    const batchSelect = document.getElementById('addUserBatchSelect');
    const sectionSelect = document.getElementById('addUserSectionSelect');
    if (!batchSelect || !sectionSelect) return;

    batchSelect.innerHTML = '<option value="">Select a batch</option>';
    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    sectionSelect.disabled = true;

    if (!courseId) {
        batchSelect.disabled = true;
        return;
    }

    const course = assignCourseTree.find(c => String(c.Course_ID) === String(courseId));
    const batches = course && course.batches ? course.batches : [];

    batches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.Batch_ID;
        option.textContent = batch.Batch_Name;
        batchSelect.appendChild(option);
    });

    batchSelect.disabled = batches.length === 0;
}

function populateAddUserSections(batchId) {
    const sectionSelect = document.getElementById('addUserSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">Select a section</option>';

    if (!batchId) {
        sectionSelect.disabled = true;
        return;
    }

    let sections = [];
    assignCourseTree.forEach(course => {
        if (course.batches) {
            const batch = course.batches.find(b => String(b.Batch_ID) === String(batchId));
            if (batch && batch.sections) {
                sections = batch.sections;
            }
        }
    });

    sections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.Section_ID;
        option.textContent = section.Section_Name;
        sectionSelect.appendChild(option);
    });

    sectionSelect.disabled = sections.length === 0;
}

document.getElementById('addUserCourseSelect')?.addEventListener('change', function () {
    populateAddUserBatches(this.value);
});

document.getElementById('addUserBatchSelect')?.addEventListener('change', function () {
    populateAddUserSections(this.value);
});

document.getElementById('openAddUserModalBtn').addEventListener('click', resetAddUserModal);

document.getElementById('addUserModal').addEventListener('show.bs.modal', function() {
    loadRoles();
    loadPersonnelRanks();
    ModalFormValidation.clearModalFieldErrors(document.getElementById('addUserForm'));
    clearAddUserAlert();
});

document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
    const editIdInput = document.getElementById('editUserId');
    const userStatusSection = document.getElementById('userStatusSection');
    if (editIdInput) editIdInput.value = '';
    if (userStatusSection) userStatusSection.style.display = 'none';
    ModalFormValidation.clearModalFieldErrors(document.getElementById('addUserForm'));
    clearAddUserAlert();
});

// When role changes, adjust form fields and default password
document.getElementById('role_id').addEventListener('change', function () {
    updateUserFormByRole(getSelectedRoleName());
});

// Archive user
function archiveUser(userId) {
    Swal.fire({
        title: 'Archive User?',
        html: 'The user will be hidden from active lists and permanently removed after <strong>2 years</strong>. You can restore them from the Archive page.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Archive',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.post(API_BASE, {
                action: 'archive',
                user_id: userId
            })
            .then(response => {
                if (response.data.success) {
                    showAlert('User archived successfully', 'success');
                    loadUsers();
                }
            })
            .catch(error => {
                const message = error.response?.data?.message || 'Error archiving user';
                showAlert(message, 'error');
            });
        }
    });
}


function editUser(userId) {
    const user = allUsers.find(u => u.User_ID === userId);
    if (!user) {
        showAlert('User not found in current list', 'error');
        return;
    }

    const editIdInput = document.getElementById('editUserId');
    const firstnameInput = document.getElementById('firstname');
    const lastnameInput = document.getElementById('lastname');
    const middlenameInput = document.getElementById('middlename');
    const emailInput = document.getElementById('email');
    const badgeInput = document.getElementById('badge_number');
    const roleSelect = document.getElementById('role_id');
    const passwordInput = document.getElementById('password');
    const passwordOptionalLabel = document.getElementById('passwordOptionalLabel');
    const passwordHint = document.getElementById('passwordHint');
    const submitBtn = document.getElementById('addUserSubmitBtn');
    const titleEl = document.getElementById('addUserModalLabel');
    const userStatusSelect = document.getElementById('user_status');
    const userStatusSection = document.getElementById('userStatusSection');

    if (editIdInput) editIdInput.value = user.User_ID;
    if (firstnameInput) firstnameInput.value = user.Firstname || '';
    if (lastnameInput) lastnameInput.value = user.Lastname || '';
    if (middlenameInput) middlenameInput.value = user.Middlename || '';
    if (emailInput) {
        const displayEmail = user.Email && !String(user.Email).trim().toLowerCase().endsWith(EXAMINEE_EMAIL_SUFFIX)
            ? user.Email
            : '';
        emailInput.value = displayEmail;
    }
    if (badgeInput) badgeInput.value = user.Badge_Number || '';
    populatePersonnelRankSelect(user.Personnel_Rank || '');

    // Select the correct role once roles are loaded
    loadRoles();
    loadPersonnelRanks();
    setTimeout(() => {
        if (roleSelect) {
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].dataset.roleName === user.Role_Name) {
                    roleSelect.selectedIndex = i;
                    updateUserFormByRole(user.Role_Name);
                    break;
                }
            }
        }
    }, 200);

    if (userStatusSection) userStatusSection.style.display = 'block';
    if (userStatusSelect) userStatusSelect.value = user.Status || 'Active';

    // Make password optional for editing (can reset password here)
    if (passwordInput) {
        passwordInput.readOnly = false;
        passwordInput.value = '';
        passwordInput.required = false;
        passwordInput.placeholder = 'Leave blank to keep current password';
    }
    
    if (passwordOptionalLabel) {
        passwordOptionalLabel.style.display = 'inline';
    }
    
    if (passwordHint) {
        passwordHint.textContent = 'Enter a new password only if you want to reset it';
    }

    initAdminPasswordPolicyValidation();

    if (submitBtn) submitBtn.textContent = 'Save Changes';
    if (titleEl) titleEl.textContent = 'Edit User';

    addUserModal.show();
}

function resetAssignSelects() {
    const courseSelect = document.getElementById('assignCourseSelect');
    const batchSelect = document.getElementById('assignParentBatchSelect');
    const sectionSelect = document.getElementById('assignSectionSelect');

    if (courseSelect) {
        courseSelect.innerHTML = '<option value="">Select a course</option>';
    }
    if (batchSelect) {
        batchSelect.innerHTML = '<option value="">Select a batch</option>';
        batchSelect.disabled = true;
    }
    if (sectionSelect) {
        sectionSelect.innerHTML = '<option value="">Select a section</option>';
        sectionSelect.disabled = true;
    }
}

function populateAssignCourses() {
    const courseSelect = document.getElementById('assignCourseSelect');
    if (!courseSelect) return;

    resetAssignSelects();
    assignCourseTree.forEach(course => {
        const option = document.createElement('option');
        option.value = course.Course_ID;
        option.textContent = course.Course_Name;
        courseSelect.appendChild(option);
    });
}

function populateAssignBatches(courseId) {
    const batchSelect = document.getElementById('assignParentBatchSelect');
    const sectionSelect = document.getElementById('assignSectionSelect');
    if (!batchSelect || !sectionSelect) return;

    batchSelect.innerHTML = '<option value="">Select a batch</option>';
    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    sectionSelect.disabled = true;

    if (!courseId) {
        batchSelect.disabled = true;
        return;
    }

    const course = assignCourseTree.find(c => String(c.Course_ID) === String(courseId));
    const batches = course && course.batches ? course.batches : [];

    batches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.Batch_ID;
        option.textContent = batch.Batch_Name;
        batchSelect.appendChild(option);
    });

    batchSelect.disabled = batches.length === 0;
}

function populateAssignSections(batchId) {
    const sectionSelect = document.getElementById('assignSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">Select a section</option>';

    if (!batchId) {
        sectionSelect.disabled = true;
        return;
    }

    let sections = [];
    assignCourseTree.forEach(course => {
        if (course.batches) {
            const batch = course.batches.find(b => String(b.Batch_ID) === String(batchId));
            if (batch && batch.sections) {
                sections = batch.sections;
            }
        }
    });

    sections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.Section_ID;
        option.textContent = section.Section_Name;
        sectionSelect.appendChild(option);
    });

    sectionSelect.disabled = sections.length === 0;
}

// Assign examinee to course/batch/section
function assignToBatch(userId) {
    document.getElementById('assignUserId').value = userId;

    const warningEl = document.getElementById('assignEnrollmentWarning');
    if (warningEl) {
        warningEl.classList.add('d-none');
        warningEl.textContent = '';
    }

    if (!assignBatchModal) {
        assignBatchModal = new bootstrap.Modal(document.getElementById('assignBatchModal'));
    }

    Promise.all([
        axios.get(`${COURSES_API}?action=tree`),
        axios.get(`${BATCHES_API}?action=batches_by_user&user_id=${userId}`)
    ])
        .then(([coursesResponse, batchesResponse]) => {
            if (!coursesResponse.data.success) {
                showAlert('Error loading courses', 'error');
                return;
            }

            const submitBtn = document.getElementById('assignBatchSubmitBtn');
            assignCourseTree = coursesResponse.data.data || [];
            populateAssignCourses();

            const enrollments = (batchesResponse.data.success ? batchesResponse.data.data || [] : [])
                .filter(b => b.Status === 'Active');
            if (submitBtn) {
                submitBtn.disabled = enrollments.length > 0;
            }
            if (warningEl && enrollments.length > 0) {
                const label = enrollments.map(b =>
                    `${b.Course_Name} → ${b.Section_Name || '—'} → ${b.Batch_Name}`
                ).join('; ');
                warningEl.textContent = `This trainee is already assigned to ${label}. Remove them from that assignment before assigning elsewhere.`;
                warningEl.classList.remove('d-none');
            }

            assignBatchModal.show();
        })
        .catch(error => {
            console.error('Error loading assignment data:', error);
            showAlert('Error loading assignment data', 'error');
        });
}

document.getElementById('assignCourseSelect').addEventListener('change', function() {
    populateAssignBatches(this.value);
});

document.getElementById('assignParentBatchSelect').addEventListener('change', function() {
    populateAssignSections(this.value);
});

// Assign form handler
const assignBatchFormEl = document.getElementById('assignBatchForm');
if (assignBatchFormEl) {
ModalFormValidation.bindModalFieldErrorClear(assignBatchFormEl);
assignBatchFormEl.addEventListener('submit', function(e) {
    e.preventDefault();
    const form = e.target;
    const fieldErrors = validateAssignBatchFieldErrors();
    if (Object.keys(fieldErrors).length > 0) {
        ModalFormValidation.applyModalFieldErrors(form, fieldErrors, { summaryAlertId: 'assignBatchFormAlert' });
        return;
    }
    ModalFormValidation.clearModalFieldErrors(form);
    ModalFormValidation.setSummaryAlert('assignBatchFormAlert', '');

    const formData = new FormData(this);
    const userId = formData.get('user_id');
    const sectionId = formData.get('section_id');

    if (!userId || !sectionId) {
        showAlert('Please select a course, batch, and section', 'error');
        return;
    }

    const data = {
        user_ids: [userId],
        section_id: sectionId,
        status: 'Active',
        action: 'assign_users'
    };

    axios.post(BATCHES_API, data)
        .then(response => {
            if (response.data.success) {
                showAlert('Trainee assigned successfully', 'success');
                this.reset();
                if (assignBatchModal) {
                    assignBatchModal.hide();
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('assignBatchModal'))?.hide();
                }
                loadUsers();
            }
        })
        .catch(error => {
            showAlert(error.response?.data?.message || 'Error assigning trainee', 'error');
        });
});
}

document.getElementById('assignBatchModal')?.addEventListener('show.bs.modal', () => {
    ModalFormValidation.clearModalFieldErrors(assignBatchFormEl);
    ModalFormValidation.setSummaryAlert('assignBatchFormAlert', '');
});

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function normalizeImportHeader(value) {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[\s.]+/g, '_');
}

function mapImportRow(rawRow, rowNumber) {
    const mapped = { _row: rowNumber };
    Object.keys(rawRow || {}).forEach(key => {
        const normalizedKey = normalizeImportHeader(key);
        const value = rawRow[key];
        if (normalizedKey === 'full_name' || normalizedKey === 'fullname' || normalizedKey === 'name') {
            mapped.Fullname = value;
        } else if (normalizedKey === 'first_name' || normalizedKey === 'firstname') {
            mapped.Firstname = value;
        } else if (normalizedKey === 'last_name' || normalizedKey === 'lastname') {
            mapped.Lastname = value;
        } else if (normalizedKey === 'middle_name' || normalizedKey === 'middlename' || normalizedKey === 'middle') {
            mapped.Middlename = value;
        } else if (
            normalizedKey === 'badge_number'
            || normalizedKey === 'badge_no'
            || normalizedKey === 'academic_number'
            || normalizedKey === 'academic_no'
        ) {
            mapped.Badge_Number = value;
        } else if (normalizedKey === 'personnel_rank' || normalizedKey === 'rank') {
            mapped.Personnel_Rank = String(value || '').trim().toUpperCase();
        } else if (normalizedKey === 'email') {
            mapped.Email = value;
        }
    });
    return mapped;
}

function parseImportFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];
                const jsonRows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
                const rows = jsonRows
                    .map((row, index) => mapImportRow(row, index + 2))
                    .filter(row => {
                        const hasName = [row.Fullname, row.Firstname, row.Lastname, row.Middlename]
                            .some(value => String(value || '').trim() !== '');
                        const hasBadge = String(row.Badge_Number || '').trim() !== '';
                        return hasName || hasBadge;
                    });
                resolve(rows);
            } catch (err) {
                reject(err);
            }
        };
        reader.onerror = () => reject(new Error('Unable to read file'));
        reader.readAsArrayBuffer(file);
    });
}

function resetImportPreview() {
    importPreviewData = null;
    parsedImportRows = [];
    document.getElementById('importPreviewWrap').style.display = 'none';
    document.getElementById('importPreviewBody').innerHTML = '';
    document.getElementById('importSummary').classList.add('d-none');
    document.getElementById('importSummary').textContent = '';
    document.getElementById('importCapacityInfo').classList.add('d-none');
    document.getElementById('commitImportBtn').disabled = true;
}

function resetImportModal() {
    resetImportPreview();
    document.getElementById('importFile').value = '';
    document.getElementById('previewImportBtn').disabled = true;
    populateImportCourses();
}

function populateImportCourses() {
    const courseSelect = document.getElementById('importCourseSelect');
    const batchSelect = document.getElementById('importBatchSelect');
    const sectionSelect = document.getElementById('importSectionSelect');
    if (!courseSelect) return;

    courseSelect.innerHTML = '<option value="">Select a course</option>';
    batchSelect.innerHTML = '<option value="">Select a batch</option>';
    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    batchSelect.disabled = true;
    sectionSelect.disabled = true;

    importCourseTree.forEach(course => {
        const option = document.createElement('option');
        option.value = course.Course_ID;
        option.textContent = course.Course_Name;
        courseSelect.appendChild(option);
    });
}

function populateImportBatches(courseId) {
    const batchSelect = document.getElementById('importBatchSelect');
    const sectionSelect = document.getElementById('importSectionSelect');
    if (!batchSelect || !sectionSelect) return;

    batchSelect.innerHTML = '<option value="">Select a batch</option>';
    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    sectionSelect.disabled = true;

    if (!courseId) {
        batchSelect.disabled = true;
        return;
    }

    const course = importCourseTree.find(c => String(c.Course_ID) === String(courseId));
    const batches = course && course.batches ? course.batches : [];
    batches.forEach(batch => {
        const option = document.createElement('option');
        option.value = batch.Batch_ID;
        option.textContent = batch.Batch_Name;
        batchSelect.appendChild(option);
    });
    batchSelect.disabled = batches.length === 0;
}

function populateImportSections(batchId) {
    const sectionSelect = document.getElementById('importSectionSelect');
    if (!sectionSelect) return;

    sectionSelect.innerHTML = '<option value="">Select a section</option>';
    if (!batchId) {
        sectionSelect.disabled = true;
        return;
    }

    let sections = [];
    importCourseTree.forEach(course => {
        if (course.batches) {
            const batch = course.batches.find(b => String(b.Batch_ID) === String(batchId));
            if (batch && batch.sections) {
                sections = batch.sections;
            }
        }
    });

    sections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.Section_ID;
        option.textContent = section.Section_Name;
        sectionSelect.appendChild(option);
    });
    sectionSelect.disabled = sections.length === 0;
}

function updateImportActionState() {
    const sectionId = document.getElementById('importSectionSelect')?.value;
    const hasFile = parsedImportRows.length > 0;
    document.getElementById('previewImportBtn').disabled = !(sectionId && hasFile);
    if (!sectionId || !hasFile) {
        resetImportPreview();
    }
}

function renderImportPreview(data) {
    const wrap = document.getElementById('importPreviewWrap');
    const body = document.getElementById('importPreviewBody');
    const summaryEl = document.getElementById('importSummary');
    const capacityEl = document.getElementById('importCapacityInfo');
    const commitBtn = document.getElementById('commitImportBtn');

    if (!wrap || !body || !data) return;

    body.innerHTML = (data.rows || []).map(row => {
        const statusClass = row.status === 'error'
            ? 'text-danger'
            : row.status === 'skip'
                ? 'text-muted'
                : 'text-success';
        const notes = [...(row.errors || []), ...(row.warnings || [])].join('; ') || '—';
        return `
            <tr>
                <td>${row.row}</td>
                <td>${escapeHtml(row.fullname || '')}</td>
                <td>${escapeHtml(row.badge_number || '')}</td>
                <td>${escapeHtml(row.personnel_rank || '—')}</td>
                <td class="${statusClass} fw-semibold">${escapeHtml(row.status)}</td>
                <td><small>${escapeHtml(notes)}</small></td>
            </tr>
        `;
    }).join('');

    wrap.style.display = 'block';

    const summary = data.summary || {};
    summaryEl.classList.remove('d-none');
    summaryEl.innerHTML = `
        <span class="me-3"><strong>${summary.total || 0}</strong> rows</span>
        <span class="me-3 text-success"><strong>${summary.ready || 0}</strong> ready</span>
        <span class="me-3 text-muted"><strong>${summary.skip || 0}</strong> skip</span>
        <span class="text-danger"><strong>${summary.error || 0}</strong> errors</span>
    `;

    const capacity = data.capacity || {};
    capacityEl.classList.remove('d-none');
    if (capacity.exceeded) {
        capacityEl.className = 'alert alert-danger py-2 small mb-3';
        capacityEl.textContent = `Section capacity exceeded for ${capacity.section_name || 'section'}. Max ${capacity.capacity}, assigned ${capacity.assigned_count}, available ${capacity.available_slots}, import needs ${summary.new_assignments}.`;
    } else {
        capacityEl.className = 'alert alert-info py-2 small mb-3';
        capacityEl.textContent = `${capacity.section_name || 'Section'}: capacity ${capacity.capacity}, assigned ${capacity.assigned_count}, available ${capacity.available_slots}, import needs ${summary.new_assignments}.`;
    }

    const canCommit = (summary.ready || 0) > 0 && !capacity.exceeded;
    commitBtn.disabled = !canCommit;
}

function previewBulkImport() {
    const sectionId = document.getElementById('importSectionSelect')?.value;
    if (!sectionId) {
        showAlert('Select a section first', 'error');
        return;
    }
    if (parsedImportRows.length === 0) {
        showAlert('Upload a roster file first', 'error');
        return;
    }

    const previewBtn = document.getElementById('previewImportBtn');
    previewBtn.disabled = true;
    previewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Previewing...';

    axios.post(API_BASE, {
        action: 'preview_bulk_examinees',
        section_id: sectionId,
        rows: parsedImportRows
    })
        .then(response => {
            if (response.data.success) {
                importPreviewData = response.data;
                renderImportPreview(response.data);
            } else {
                showAlert(response.data.message || 'Preview failed', 'error');
            }
        })
        .catch(error => {
            showAlert(error.response?.data?.message || 'Preview failed', 'error');
        })
        .finally(() => {
            previewBtn.disabled = false;
            previewBtn.innerHTML = '<i class="bi bi-search me-1"></i>Preview';
            updateImportActionState();
        });
}

function commitBulkImport() {
    const sectionId = document.getElementById('importSectionSelect')?.value;
    if (!sectionId || parsedImportRows.length === 0) {
        showAlert('Select a section and upload a file first', 'error');
        return;
    }

    Swal.fire({
        title: 'Import Trainees?',
        html: 'Valid rows will create new accounts (default password) and assign them to the selected section.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Import',
        cancelButtonText: 'Cancel'
    }).then(result => {
        if (!result.isConfirmed) return;

        const commitBtn = document.getElementById('commitImportBtn');
        commitBtn.disabled = true;
        commitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing...';

        axios.post(API_BASE, {
            action: 'commit_bulk_examinees',
            section_id: sectionId,
            rows: parsedImportRows
        })
            .then(response => {
                if (response.data.success) {
                    showAlert(response.data.message || 'Import completed', 'success');
                    if (bulkImportModal) {
                        bulkImportModal.hide();
                    }
                    loadUsers();
                } else {
                    showAlert(response.data.message || 'Import failed', 'error');
                }
            })
            .catch(error => {
                showAlert(error.response?.data?.message || 'Import failed', 'error');
            })
            .finally(() => {
                commitBtn.disabled = false;
                commitBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Trainees';
            });
    });
}

function downloadImportTemplate() {
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.aoa_to_sheet([
        ['Lastname', 'Firstname', 'Middlename', 'Badge_Number', 'Rank', 'Email'],
        ['Dela Cruz', 'Juan', '', '123456', 'PAT', 'juan@example.com'],
        ['Santos', 'Maria', 'Reyes', '123457', 'PCPL', 'maria@example.com']
    ]);
    XLSX.utils.book_append_sheet(wb, ws, 'Trainees');
    XLSX.writeFile(wb, 'trainee_import_template.xlsx');
}

function openBulkImportModal() {
    if (!bulkImportModal) {
        bulkImportModal = new bootstrap.Modal(document.getElementById('bulkImportModal'));
    }

    resetImportModal();

    axios.get(`${COURSES_API}?action=tree`)
        .then(response => {
            if (response.data.success) {
                importCourseTree = response.data.data || [];
                populateImportCourses();
                bulkImportModal.show();
            }
        })
        .catch(error => {
            console.error('Error loading courses:', error);
            showAlert('Error loading courses', 'error');
        });
}

// Dropdown toggle handler
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
        e.stopPropagation(); // Stop event from bubbling up
        const href = this.getAttribute('href');
        
        // Navigate without closing dropdown
        window.location.href = href;
    });
});

// Initialize on page load
loadRoles();
loadPersonnelRanks();
loadUsers();

document.getElementById('openBulkImportModalBtn')?.addEventListener('click', openBulkImportModal);
document.getElementById('downloadImportTemplateBtn')?.addEventListener('click', downloadImportTemplate);
document.getElementById('previewImportBtn')?.addEventListener('click', previewBulkImport);
document.getElementById('commitImportBtn')?.addEventListener('click', commitBulkImport);
document.getElementById('importCourseSelect')?.addEventListener('change', function() {
    populateImportBatches(this.value);
    updateImportActionState();
});
document.getElementById('importBatchSelect')?.addEventListener('change', function() {
    populateImportSections(this.value);
    updateImportActionState();
});
document.getElementById('importSectionSelect')?.addEventListener('change', updateImportActionState);
document.getElementById('importFile')?.addEventListener('change', function() {
    resetImportPreview();
    const file = this.files && this.files[0];
    if (!file) {
        parsedImportRows = [];
        updateImportActionState();
        return;
    }

    parseImportFile(file)
        .then(rows => {
            parsedImportRows = rows;
            if (rows.length === 0) {
                showAlert('No data rows found in file', 'error');
            }
            updateImportActionState();
        })
        .catch(error => {
            console.error(error);
            parsedImportRows = [];
            showAlert('Unable to parse file. Use the template format.', 'error');
            updateImportActionState();
        });
});
document.getElementById('bulkImportModal')?.addEventListener('hidden.bs.modal', resetImportModal);

function bindNamePartValidation(inputId, label, required) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.addEventListener('input', function() {
        const message = namePartError(this.value.trim(), label, required);
        if (!this.value.trim()) {
            this.setCustomValidity('');
            this.classList.remove('is-invalid', 'is-valid');
        } else if (message) {
            this.setCustomValidity(message);
            this.classList.add('is-invalid');
            this.classList.remove('is-valid');
        } else {
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
            this.classList.add('is-valid');
        }
    });
}

bindNamePartValidation('firstname', 'First name', true);
bindNamePartValidation('lastname', 'Last name', true);
bindNamePartValidation('middlename', 'Middle name', false);

document.getElementById('badge_number')?.addEventListener('input', function() {
    const value = this.value.trim();

    if (value.length === 0) {
        this.setCustomValidity('');
        this.classList.remove('is-invalid', 'is-valid');
    } else if (!BADGE_NUMBER_PATTERN.test(value)) {
        this.setCustomValidity(BADGE_NUMBER_FORMAT_MESSAGE);
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Real-time validation for email
document.getElementById('email').addEventListener('input', function() {
    const value = this.value;
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (value.length === 0) {
        this.setCustomValidity('');
        this.classList.remove('is-invalid', 'is-valid');
    } else if (!pattern.test(value)) {
        this.setCustomValidity('Please enter a valid email address');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

initAdminPasswordPolicyValidation();

// Filter controls
document.getElementById('filterRole').addEventListener('change', function () {
    currentRoleFilter = this.value || 'All';
    applyUserFilters();
});

document.getElementById('filterStatus').addEventListener('change', function () {
    currentStatusFilter = this.value || 'All';
    applyUserFilters();
});

document.getElementById('filterSearch').addEventListener('input', function () {
    currentSearch = this.value || '';
    applyUserFilters();
});
