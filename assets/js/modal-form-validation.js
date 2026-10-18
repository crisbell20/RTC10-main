(function (global) {
    'use strict';

    const GROUP_INVALID_CLASSES = ['is-invalid', 'border', 'border-danger', 'rounded'];

    function getFieldContainer(fieldEl) {
        if (!fieldEl) return null;
        if (fieldEl.hasAttribute('data-validate-group')) {
            return fieldEl;
        }
        return fieldEl.closest('[data-validate-group]') || fieldEl.closest('.mb-3') || fieldEl.parentElement;
    }

    function resolveFieldElement(formEl, fieldKey) {
        if (!fieldKey) return null;
        const byId = document.getElementById(fieldKey);
        if (byId) return byId;
        if (formEl) {
            const byName = formEl.querySelector(`[name="${fieldKey}"]`);
            if (byName) return byName;
        }
        return null;
    }

    function removeFieldFeedback(host) {
        if (!host) return;
        host.querySelectorAll('.invalid-feedback[data-modal-field-error]').forEach(el => el.remove());
    }

    function clearModalFieldErrors(formEl) {
        if (!formEl) return;

        formEl.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid', 'border', 'border-danger', 'rounded', 'p-2');
        });
        formEl.querySelectorAll('.invalid-feedback[data-modal-field-error]').forEach(el => el.remove());
    }

    function setModalFieldError(formEl, fieldKey, message) {
        const field = resolveFieldElement(formEl, fieldKey);
        if (!field || !message) return false;

        const isGroup = field.hasAttribute('data-validate-group');
        if (isGroup) {
            field.classList.add(...GROUP_INVALID_CLASSES, 'p-2');
        } else {
            field.classList.add('is-invalid');
        }

        const host = getFieldContainer(field);
        if (!host) return true;

        removeFieldFeedback(host);

        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback d-block';
        feedback.dataset.modalFieldError = '1';
        feedback.textContent = message;
        host.appendChild(feedback);
        return true;
    }

    function setSummaryAlert(summaryAlertId, message, type = 'danger') {
        if (!summaryAlertId) return;
        const summaryEl = document.getElementById(summaryAlertId);
        if (!summaryEl) return;

        if (!message) {
            summaryEl.innerHTML = '';
            return;
        }

        summaryEl.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show mb-3" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        summaryEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function applyModalFieldErrors(formEl, fieldErrors, options = {}) {
        clearModalFieldErrors(formEl);

        const entries = Object.entries(fieldErrors || {}).filter(([, msg]) => Boolean(msg));
        if (!entries.length) {
            setSummaryAlert(options.summaryAlertId, '');
            return { ok: true, firstKey: null, firstMessage: null };
        }

        entries.forEach(([key, message]) => setModalFieldError(formEl, key, message));

        const firstKey = entries[0][0];
        const firstMessage = entries[0][1];
        const firstField = resolveFieldElement(formEl, firstKey);
        if (firstField && options.scroll !== false) {
            firstField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        const summaryMessage = options.summaryMessage
            || (entries.length > 1 ? 'Please fix the highlighted fields below.' : firstMessage);
        setSummaryAlert(options.summaryAlertId, summaryMessage, options.summaryType || 'danger');

        return { ok: false, firstKey, firstMessage };
    }

    function clearFieldErrorOnChange(formEl, target) {
        if (!target || !formEl?.contains(target)) return;

        target.classList.remove('is-invalid');
        const group = target.closest('[data-validate-group]');
        if (group) {
            group.classList.remove('is-invalid', 'border', 'border-danger', 'rounded', 'p-2');
            removeFieldFeedback(group);
        }

        const container = getFieldContainer(target);
        if (container && container !== group) {
            removeFieldFeedback(container);
            if (container.hasAttribute('data-validate-group')) {
                container.classList.remove('is-invalid', 'border', 'border-danger', 'rounded', 'p-2');
            }
        }
    }

    function bindModalFieldErrorClear(formEl) {
        if (!formEl || formEl.dataset.modalValidationBound === '1') return;
        formEl.dataset.modalValidationBound = '1';

        formEl.addEventListener('input', (event) => clearFieldErrorOnChange(formEl, event.target));
        formEl.addEventListener('change', (event) => clearFieldErrorOnChange(formEl, event.target));
    }

    global.ModalFormValidation = {
        applyModalFieldErrors,
        bindModalFieldErrorClear,
        clearModalFieldErrors,
        resolveFieldElement,
        setModalFieldError,
        setSummaryAlert,
    };
}(window));
