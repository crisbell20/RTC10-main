const API_BASE = '../../api/masterfiles/users.php';
let addUserModal = new bootstrap.Modal(document.getElementById('addUserModal'));
let personnelRankOptions = [];

// Keep a copy of all users for filtering
let allUsers = [];
let currentRoleFilter = 'All';
let currentStatusFilter = 'All';
let currentSearch = '';

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
        tbody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No users found</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => {
        const batchInfo = user.Role_Name === 'Examinee' && user.batches && user.batches.length > 0
            ? user.batches.map(b => `<small class="d-block">${escapeHtml(b.Course_Name)} → ${escapeHtml(b.Section_Name)} → ${escapeHtml(b.Batch_Name)}</small>`).join('')
            : user.Role_Name === 'Examinee' 
                ? '<small class="text-muted">Not assigned</small>'
                : '<small class="text-muted">—</small>';

        const rankDisplay = user.Personnel_Rank
            ? escapeHtml(user.Personnel_Rank)
            : '<small class="text-muted">—</small>';
        
        return `
        <tr>
            <td><strong>${escapeHtml(user.Fullname)}</strong></td>
            <td>${escapeHtml(user.Email)}</td>
            <td>${escapeHtml(user.Username)}</td>
            <td>${user.Academic_Number ? escapeHtml(user.Academic_Number) : ''}</td>
            <td>${rankDisplay}</td>
            <td><span class="badge badge-info">${escapeHtml(user.Role_Name)}</span></td>
            <td>${batchInfo}</td>
            <td>
                <span class="status-badge ${user.Status === 'Active' ? 'bg-success text-white' : 'bg-warning'}">
                    ${escapeHtml(user.Status)}
                </span>
            </td>
            <td>
                <div class="dropdown">
                    <button class="btn btn-outline btn-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        ${user.Role_Name === 'Examinee' ? `
                        <li>
                            <a class="dropdown-item" href="#" onclick="event.preventDefault(); assignToBatch(${user.User_ID})">
                                <i class="bi bi-person-plus me-2"></i>Assign to Batch
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
                            <a class="dropdown-item text-danger" href="#" onclick="event.preventDefault(); deleteUser(${user.User_ID})">
                                <i class="bi bi-trash me-2"></i>Delete User
                            </a>
                        </li>
                    </ul>
                </div>
            </td>
            <td>${new Date(user.Date_Created).toLocaleDateString()}</td>
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
            (u.Username && u.Username.toLowerCase().includes(term))
        );
    }

    renderUsersTable(filtered);
}

// Show alert
function showAlert(message, type = 'success') {
    const alertDiv = document.getElementById('messageAlert');
    alertDiv.innerHTML = `<div class="alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
}

// Add user form submit
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get form values
    const fullname = document.getElementById('fullname').value.trim();
    const email = document.getElementById('email').value.trim();
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    
    // Validate fullname - only letters, spaces, dots, hyphens, apostrophes
    const namePattern = /^[a-zA-Z\s.\-']+$/;
    if (!namePattern.test(fullname)) {
        showAlert('Full name must contain only letters, spaces, dots, hyphens, and apostrophes', 'error');
        document.getElementById('fullname').focus();
        return;
    }
    
    if (fullname.length < 2 || fullname.length > 100) {
        showAlert('Full name must be between 2 and 100 characters', 'error');
        document.getElementById('fullname').focus();
        return;
    }
    
    // Validate email
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailPattern.test(email)) {
        showAlert('Please enter a valid email address', 'error');
        document.getElementById('email').focus();
        return;
    }
    
    // Validate username - only letters, numbers, underscores, hyphens
    const usernamePattern = /^[a-zA-Z0-9_\-]+$/;
    const hasLetter = /[a-zA-Z]/.test(username);
    
    if (!usernamePattern.test(username)) {
        showAlert('Username must contain only letters, numbers, underscores, and hyphens', 'error');
        document.getElementById('username').focus();
        return;
    }
    
    if (!hasLetter) {
        showAlert('Username must contain at least one letter', 'error');
        document.getElementById('username').focus();
        return;
    }
    
    if (username.length < 3 || username.length > 50) {
        showAlert('Username must be between 3 and 50 characters', 'error');
        document.getElementById('username').focus();
        return;
    }
    
    // Validate password (only if required or has value)
    const editUserIdCheck = document.getElementById('editUserId').value;
    if (!editUserIdCheck || (password && password !== '********')) {
        if (password.length < 6) {
            showAlert('Password must be at least 6 characters', 'error');
            document.getElementById('password').focus();
            return;
        }
    }
    
    const formData = new FormData(this);
    const editUserId = formData.get('edit_user_id');

    // Common fields
    const payload = {
        fullname: formData.get('fullname'),
        email: formData.get('email'),
        username: formData.get('username'),
        academic_number: formData.get('academic_number'),
        personnel_rank: formData.get('personnel_rank'),
        role_id: formData.get('role_id')
    };

    // Decide between add vs update
    if (editUserId) {
        payload.action = 'update';
        payload.user_id = editUserId;
        // Status: when editing we keep current status or default Active if not set
        const existing = allUsers.find(u => String(u.User_ID) === String(editUserId));
        payload.status = existing ? existing.Status : 'Active';

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
                showAlert(message, 'error');
            });
    } else {
        payload.action = 'add';
        payload.password = formData.get('password');

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
                showAlert(message, 'error');
            });
    }
});

// Reload roles when modal is shown
document.getElementById('addUserModal').addEventListener('show.bs.modal', function() {
    loadRoles();
    loadPersonnelRanks();
    // Reset password field to editable/empty by default
    const passwordInput = document.getElementById('password');
    const passwordOptionalLabel = document.getElementById('passwordOptionalLabel');
    const passwordHint = document.getElementById('passwordHint');
    
    if (passwordInput) {
        passwordInput.readOnly = false;
        passwordInput.value = '';
        passwordInput.required = true;
    }
    if (passwordOptionalLabel) {
        passwordOptionalLabel.style.display = 'none';
    }
    if (passwordHint) {
        passwordHint.textContent = '';
    }
    
    // Reset edit state
    const editIdInput = document.getElementById('editUserId');
    const submitBtn = document.getElementById('addUserSubmitBtn');
    const titleEl = document.getElementById('addUserModalLabel');
    if (editIdInput) editIdInput.value = '';
    if (submitBtn) submitBtn.textContent = 'Add User';
    if (titleEl) titleEl.textContent = 'Add New User';
    
    // Hide batch enrollment section when adding new user
    hideBatchEnrollmentSection();
    populatePersonnelRankSelect('');
});

// Add to Batch button handler
document.getElementById('addToBatchBtn').addEventListener('click', function() {
    const editIdInput = document.getElementById('editUserId');
    const userId = editIdInput ? editIdInput.value : '';
    
    if (!userId) {
        showAlert('Please save the user first before adding to batch', 'error');
        return;
    }
    
    // Load all batches
    const batchSelect = document.getElementById('addToBatchSelect');
    batchSelect.innerHTML = '<option value="">Loading batches...</option>';
    
    axios.get('../../api/masterfiles/courses.php?action=list')
        .then(response => {
            if (response.data.success) {
                batchSelect.innerHTML = '<option value="">Select a batch</option>';
                
                response.data.data.forEach(course => {
                    if (course.sections) {
                        course.sections.forEach(section => {
                            if (section.batches) {
                                section.batches.forEach(batch => {
                                    const option = document.createElement('option');
                                    option.value = batch.Batch_ID;
                                    option.textContent = `${course.Course_Name} → ${section.Section_Name} → ${batch.Batch_Name}`;
                                    batchSelect.appendChild(option);
                                });
                            }
                        });
                    }
                });
                
                document.getElementById('addToBatchUserId').value = userId;
                new bootstrap.Modal(document.getElementById('addToBatchModal')).show();
            }
        })
        .catch(error => {
            console.error('Error loading batches:', error);
            showAlert('Error loading batches', 'error');
        });
});

// Add to Batch form handler
document.getElementById('addToBatchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {
        user_id: formData.get('user_id'),
        batch_id: formData.get('batch_id'),
        action: 'assign_user'
    };

    axios.post('../../api/masterfiles/batches.php', data)
        .then(response => {
            if (response.data.success) {
                showAlert('Examinee added to batch successfully', 'success');
                this.reset();
                bootstrap.Modal.getInstance(document.getElementById('addToBatchModal')).hide();
                loadUserBatches(data.user_id);
                loadUsers(); // Refresh main user list
            }
        })
        .catch(error => {
            showAlert(error.response?.data?.message || 'Error adding to batch', 'error');
        });
});

// When role changes, auto-fill default password for Examinee
document.getElementById('role_id').addEventListener('change', function () {
    const roleSelect = this;
    const passwordInput = document.getElementById('password');
    if (!passwordInput) return;

    const selected = roleSelect.options[roleSelect.selectedIndex];
    const roleName = selected ? (selected.dataset.roleName || selected.textContent).trim() : '';

    if (roleName === 'Examinee') {
        // Show and lock the default password
        passwordInput.value = 'PNPRTC10';
        passwordInput.readOnly = true;
    } else {
        // Allow manual password entry for other roles
        passwordInput.readOnly = false;
        passwordInput.value = '';
    }
});

// Delete user
function deleteUser(userId) {
    Swal.fire({
        title: 'Delete User?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.post(API_BASE, {
                action: 'delete',
                user_id: userId
            })
            .then(response => {
                if (response.data.success) {
                    showAlert('User deleted successfully', 'success');
                    loadUsers();
                }
            })
            .catch(error => {
                const message = error.response?.data?.message || 'Error deleting user';
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
    const fullnameInput = document.getElementById('fullname');
    const emailInput = document.getElementById('email');
    const usernameInput = document.getElementById('username');
    const academicInput = document.getElementById('academic_number');
    const roleSelect = document.getElementById('role_id');
    const passwordInput = document.getElementById('password');
    const passwordOptionalLabel = document.getElementById('passwordOptionalLabel');
    const passwordHint = document.getElementById('passwordHint');
    const submitBtn = document.getElementById('addUserSubmitBtn');
    const titleEl = document.getElementById('addUserModalLabel');

    if (editIdInput) editIdInput.value = user.User_ID;
    if (fullnameInput) fullnameInput.value = user.Fullname || '';
    if (emailInput) emailInput.value = user.Email || '';
    if (usernameInput) usernameInput.value = user.Username || '';
    if (academicInput) academicInput.value = user.Academic_Number || '';
    populatePersonnelRankSelect(user.Personnel_Rank || '');

    // Select the correct role once roles are loaded
    loadRoles();
    loadPersonnelRanks();
    setTimeout(() => {
        if (roleSelect) {
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].text.trim() === user.Role_Name) {
                    roleSelect.selectedIndex = i;
                    break;
                }
            }
        }
        
        // Show batch enrollment section if user is Examinee
        if (user.Role_Name === 'Examinee') {
            showBatchEnrollmentSection(user.User_ID);
        } else {
            hideBatchEnrollmentSection();
        }
    }, 200);

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

    if (submitBtn) submitBtn.textContent = 'Save Changes';
    if (titleEl) titleEl.textContent = 'Edit User';

    addUserModal.show();
}

// Show batch enrollment section and load user's batches
function showBatchEnrollmentSection(userId) {
    const section = document.getElementById('batchEnrollmentSection');
    if (section) {
        section.style.display = 'block';
        loadUserBatches(userId);
    }
}

// Hide batch enrollment section
function hideBatchEnrollmentSection() {
    const section = document.getElementById('batchEnrollmentSection');
    if (section) {
        section.style.display = 'none';
    }
}

// Load user's enrolled batches
function loadUserBatches(userId) {
    const container = document.getElementById('userBatchList');
    if (!container) return;
    
    container.innerHTML = '<p class="text-muted text-center py-2 mb-0"><i class="bi bi-hourglass-split"></i> Loading...</p>';
    
    axios.get(`../../api/masterfiles/batches.php?action=batches_by_user&user_id=${userId}`)
        .then(response => {
            if (response.data.success) {
                const batches = response.data.data || [];
                if (batches.length === 0) {
                    container.innerHTML = '<p class="text-muted text-center py-2 mb-0">No batches assigned</p>';
                } else {
                    container.innerHTML = batches.map(batch => `
                        <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                            <div>
                                <strong>${escapeHtml(batch.Batch_Name)}</strong>
                                <br>
                                <small class="text-muted">
                                    ${escapeHtml(batch.Course_Name)} → ${escapeHtml(batch.Section_Name)}
                                </small>
                                <br>
                                <small>
                                    <span class="badge ${batch.Status === 'Active' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(batch.Status)}</span>
                                    Enrolled: ${new Date(batch.Date_Enrolled).toLocaleDateString()}
                                </small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="removeFromBatch(${userId}, ${batch.Batch_ID}, '${escapeHtml(batch.Batch_Name)}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `).join('');
                }
            }
        })
        .catch(error => {
            console.error('Error loading user batches:', error);
            container.innerHTML = '<p class="text-danger text-center py-2 mb-0">Error loading batches</p>';
        });
}

// Remove user from batch
function removeFromBatch(userId, batchId, batchName) {
    Swal.fire({
        title: 'Remove from Batch?',
        text: `Remove this examinee from ${batchName}?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            axios.post('../../api/masterfiles/batches.php', {
                action: 'remove_user',
                user_id: userId,
                batch_id: batchId
            })
            .then(response => {
                if (response.data.success) {
                    showAlert('Examinee removed from batch successfully', 'success');
                    loadUserBatches(userId);
                    loadUsers(); // Refresh main user list
                }
            })
            .catch(error => {
                const message = error.response?.data?.message || 'Error removing from batch';
                showAlert(message, 'error');
            });
        }
    });
}

// Assign examinee to batch
function assignToBatch(userId) {
    document.getElementById('assignUserId').value = userId;
    
    // Load all batches
    axios.get('../../api/masterfiles/courses.php?action=list')
        .then(response => {
            if (response.data.success) {
                const batchSelect = document.getElementById('batchSelect');
                batchSelect.innerHTML = '<option value="">Select a batch</option>';
                
                response.data.data.forEach(course => {
                    if (course.sections) {
                        course.sections.forEach(section => {
                            if (section.batches) {
                                section.batches.forEach(batch => {
                                    const option = document.createElement('option');
                                    option.value = batch.Batch_ID;
                                    option.textContent = `${course.Course_Name} → ${section.Section_Name} → ${batch.Batch_Name}`;
                                    batchSelect.appendChild(option);
                                });
                            }
                        });
                    }
                });
                
                new bootstrap.Modal(document.getElementById('assignBatchModal')).show();
            }
        })
        .catch(error => {
            console.error('Error loading batches:', error);
            alert('Error loading batches');
        });
}

// Assign batch form handler
document.getElementById('assignBatchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = {
        user_id: formData.get('user_id'),
        batch_id: formData.get('batch_id'),
        status: formData.get('status'),
        action: 'assign_user'
    };

    axios.post('../../api/masterfiles/batches.php', data)
        .then(response => {
            if (response.data.success) {
                showAlert('Examinee assigned to batch successfully', 'success');
                this.reset();
                bootstrap.Modal.getInstance(document.getElementById('assignBatchModal')).hide();
                loadUsers();
            }
        })
        .catch(error => {
            showAlert(error.response?.data?.message || 'Error assigning to batch', 'error');
        });
});

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
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

// Logout handler
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

// Initialize on page load
loadRoles();
loadPersonnelRanks();
loadUsers();

// Real-time validation for fullname
document.getElementById('fullname').addEventListener('input', function() {
    const value = this.value;
    const pattern = /^[a-zA-Z\s.\-']+$/;
    
    if (value.length === 0) {
        this.setCustomValidity('');
        this.classList.remove('is-invalid', 'is-valid');
    } else if (!pattern.test(value)) {
        this.setCustomValidity('Full name must contain only letters, spaces, dots, hyphens, and apostrophes');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (value.length < 2 || value.length > 100) {
        this.setCustomValidity('Full name must be between 2 and 100 characters');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    }
});

// Real-time validation for username
document.getElementById('username').addEventListener('input', function() {
    const value = this.value;
    const pattern = /^[a-zA-Z0-9_\-]+$/;
    const hasLetter = /[a-zA-Z]/.test(value);
    
    if (value.length === 0) {
        this.setCustomValidity('');
        this.classList.remove('is-invalid', 'is-valid');
    } else if (!pattern.test(value)) {
        this.setCustomValidity('Username must contain only letters, numbers, underscores, and hyphens');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (!hasLetter) {
        this.setCustomValidity('Username must contain at least one letter');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else if (value.length < 3 || value.length > 50) {
        this.setCustomValidity('Username must be between 3 and 50 characters');
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

// Real-time validation for password
document.getElementById('password').addEventListener('input', function() {
    const value = this.value;
    
    if (value.length === 0) {
        this.setCustomValidity('');
        this.classList.remove('is-invalid', 'is-valid');
    } else if (value !== '********' && value.length < 6) {
        this.setCustomValidity('Password must be at least 6 characters');
        this.classList.add('is-invalid');
        this.classList.remove('is-valid');
    } else {
        this.setCustomValidity('');
        this.classList.remove('is-invalid');
        if (value !== '********') this.classList.add('is-valid');
    }
});

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
