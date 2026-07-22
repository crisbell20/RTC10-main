const DEFAULT_EXAMINEE_PASSWORD = 'PNP@rtc10';

const PASSWORD_POLICY_MESSAGE = 'Password must be at least 8 characters and include upper, lower, number, and special character';

const PASSWORD_POLICY_RULES = [
    { key: 'length', label: '8 characters minimum', test: (p) => p.length >= 8 },
    { key: 'uppercase', label: 'One uppercase letter', test: (p) => /[A-Z]/.test(p) },
    { key: 'lowercase', label: 'One lowercase letter', test: (p) => /[a-z]/.test(p) },
    { key: 'number', label: 'One number', test: (p) => /\d/.test(p) },
    { key: 'special', label: 'One special character', test: (p) => /[^A-Za-z0-9]/.test(p) },
];

function getPasswordPolicyChecks(password) {
    const value = password || '';
    const checks = {};
    PASSWORD_POLICY_RULES.forEach((rule) => {
        checks[rule.key] = rule.test(value);
    });
    checks.allMet = PASSWORD_POLICY_RULES.every((rule) => checks[rule.key]);
    return checks;
}

function validatePasswordPolicy(password) {
    const checks = getPasswordPolicyChecks(password);
    return checks.allMet ? null : PASSWORD_POLICY_MESSAGE;
}

function getPasswordPolicyChecklistHtml() {
    return `
        <ul class="password-policy-checklist list-unstyled small mb-2" id="pwdPolicyChecklist">
            ${PASSWORD_POLICY_RULES.map((rule) => `
                <li class="pwd-req pwd-req-neutral" data-req="${rule.key}">
                    <span class="pwd-req-icon" aria-hidden="true"></span>${rule.label}
                </li>
            `).join('')}
        </ul>
    `;
}

function getPasswordPolicyHintHtml() {
    return getPasswordPolicyChecklistHtml();
}

function getPasswordChangeFormHtml() {
    return `
        <div class="text-start password-change-form">
            <label class="form-label" for="curPwd">Current Password*</label>
            <input type="password" id="curPwd" class="form-control mb-2" autocomplete="current-password">
            <label class="form-label" for="newPwd">New Password*</label>
            <input type="password" id="newPwd" class="form-control mb-2" autocomplete="new-password">
            ${getPasswordPolicyChecklistHtml()}
            <label class="form-label" for="confPwd">Confirm New Password*</label>
            <input type="password" id="confPwd" class="form-control mb-2" autocomplete="new-password">
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" id="togglePwdVisibility">
                <label class="form-check-label" for="togglePwdVisibility">Show passwords</label>
            </div>
        </div>
    `;
}

function setInputValidationState(input, state) {
    if (!input) return;
    input.classList.remove('is-valid', 'is-invalid');
    if (state === 'valid') {
        input.classList.add('is-valid');
    } else if (state === 'invalid') {
        input.classList.add('is-invalid');
    }
}

function updatePasswordPolicyChecklist(password, container) {
    const root = container || document.getElementById('pwdPolicyChecklist');
    if (!root) return getPasswordPolicyChecks(password);

    const checks = getPasswordPolicyChecks(password);
    const hasInput = (password || '').length > 0;

    PASSWORD_POLICY_RULES.forEach((rule) => {
        const item = root.querySelector(`[data-req="${rule.key}"]`);
        if (!item) return;

        const met = checks[rule.key];
        const icon = item.querySelector('.pwd-req-icon');

        item.classList.remove('pwd-req-met', 'pwd-req-unmet', 'pwd-req-neutral');

        if (!hasInput) {
            item.classList.add('pwd-req-neutral');
            if (icon) {
                icon.className = 'pwd-req-icon';
                icon.innerHTML = '';
            }
        } else if (met) {
            item.classList.add('pwd-req-met');
            if (icon) {
                icon.className = 'pwd-req-icon';
                icon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
            }
        } else {
            item.classList.add('pwd-req-unmet');
            if (icon) {
                icon.className = 'pwd-req-icon';
                icon.innerHTML = '<i class="bi bi-x-circle-fill"></i>';
            }
        }
    });

    return checks;
}

function bindPasswordVisibilityToggle(toggleId = 'togglePwdVisibility', inputIds = ['curPwd', 'newPwd', 'confPwd']) {
    const toggle = document.getElementById(toggleId);
    if (!toggle) return;

    toggle.addEventListener('change', (e) => {
        const type = e.target.checked ? 'text' : 'password';
        inputIds.forEach((id) => {
            const input = document.getElementById(id);
            if (input) input.type = type;
        });
    });
}

function bindPasswordChangeFormValidation(options = {}) {
    const {
        currentId = 'curPwd',
        newId = 'newPwd',
        confirmId = 'confPwd',
        checklistContainer = null,
        visibilityToggleId = 'togglePwdVisibility',
    } = options;

    const currentInput = document.getElementById(currentId);
    const newInput = document.getElementById(newId);
    const confirmInput = document.getElementById(confirmId);

    const refresh = () => {
        const newValue = newInput?.value || '';
        const checks = updatePasswordPolicyChecklist(newValue, checklistContainer);

        if (currentInput) {
            setInputValidationState(currentInput, null);
        }

        if (newInput) {
            if (!newValue) {
                setInputValidationState(newInput, null);
            } else if (checks.allMet) {
                setInputValidationState(newInput, 'valid');
            } else {
                setInputValidationState(newInput, 'invalid');
            }
        }

        if (confirmInput) {
            setInputValidationState(confirmInput, null);
        }
    };

    if (newInput) {
        newInput.addEventListener('input', refresh);
    }
    if (currentInput) {
        currentInput.addEventListener('input', refresh);
    }
    if (confirmInput) {
        confirmInput.addEventListener('input', refresh);
    }

    bindPasswordVisibilityToggle(visibilityToggleId, [currentId, newId, confirmId]);
    refresh();
}

function mountPasswordPolicyChecklist(containerId, checklistId = 'pwdPolicyChecklist') {
    const container = document.getElementById(containerId);
    if (!container) return null;

    container.innerHTML = getPasswordPolicyChecklistHtml().replace(
        'id="pwdPolicyChecklist"',
        `id="${checklistId}"`
    );
    return document.getElementById(checklistId);
}

function bindPasswordFieldValidation(input, checklistContainer = null) {
    if (!input) return;

    if (input.dataset.policyBound !== '1') {
        input.dataset.policyBound = '1';
        input.addEventListener('input', () => refreshPasswordFieldValidation(input, checklistContainer));
    }

    refreshPasswordFieldValidation(input, checklistContainer);
}

function refreshPasswordFieldValidation(input, checklistContainer = null) {
    if (!input) return;

    const value = input.value || '';
    if (value === '********') {
        setInputValidationState(input, null);
        if (checklistContainer) {
            updatePasswordPolicyChecklist('', checklistContainer);
        }
        return;
    }

    const checks = updatePasswordPolicyChecklist(value, checklistContainer);
    if (!value) {
        setInputValidationState(input, null);
    } else if (checks.allMet) {
        setInputValidationState(input, 'valid');
    } else {
        setInputValidationState(input, 'invalid');
    }
}

function ensurePasswordPolicyStyles() {
    let style = document.getElementById('password-policy-styles');
    if (!style) {
        style = document.createElement('style');
        style.id = 'password-policy-styles';
        document.head.appendChild(style);
    }

    style.textContent = `
        .password-policy-checklist .pwd-req {
            margin-bottom: 0.2rem;
            display: flex;
            align-items: flex-start;
            gap: 0.35rem;
        }
        .password-policy-checklist .pwd-req-icon {
            display: inline-flex;
            width: 1rem;
            flex-shrink: 0;
            justify-content: center;
            margin-top: 0.1rem;
        }
        .password-policy-checklist .pwd-req-met {
            color: #198754;
        }
        .password-policy-checklist .pwd-req-unmet {
            color: #dc3545;
        }
        .password-policy-checklist .pwd-req-neutral {
            color: #6b7280;
        }
        .swal2-html-container .form-control.is-valid,
        .swal2-html-container .form-control.is-invalid {
            background-image: none;
            padding-right: 0.75rem;
        }
    `;
}

document.addEventListener('DOMContentLoaded', ensurePasswordPolicyStyles);
ensurePasswordPolicyStyles();
