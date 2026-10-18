// Question import
(function (global) {
    'use strict';

    const BANK_IMPORT_API = '../../api/masterfiles/exam.php?action=import_questions';
    const EXAM_IMPORT_API = '../../api/masterfiles/exam.php?action=import_exam_questions';

    const IMPORT_COLUMN_ALIASES = {
        Question: ['Question', 'question', 'QUESTION', 'Question Text', 'Question_Text'],
        Option_A: ['Option_A', 'Option A', 'option_a', 'A'],
        Option_B: ['Option_B', 'Option B', 'option_b', 'B'],
        Option_C: ['Option_C', 'Option C', 'option_c', 'C'],
        Option_D: ['Option_D', 'Option D', 'option_d', 'D'],
        Option_E: ['Option_E', 'Option E', 'option_e', 'E'],
        Correct_Answer: ['Correct_Answer', 'Correct Answer', 'correct_answer', 'Answer', 'Correct'],
        Subject: ['Subject', 'subject', 'Subject_Name'],
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    const QUESTION_SHEET_HEADERS = ['Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Option_E', 'Correct_Answer'];

    function normalizeImportMetadata(metadata) {
        return {
            course: String(metadata?.course ?? '').trim(),
            module: String(metadata?.module ?? '').trim(),
            subject: String(metadata?.subject ?? '').trim(),
        };
    }

    function buildQuestionsSheetAoa(metadata, questionRows) {
        const info = normalizeImportMetadata(metadata);
        const aoa = [
            ['Course', info.course],
            ['Module', info.module],
            ['Subject', info.subject],
            [],
            QUESTION_SHEET_HEADERS,
        ];

        questionRows.forEach((row) => {
            aoa.push(QUESTION_SHEET_HEADERS.map((header) => row[header] ?? ''));
        });

        return aoa;
    }

    function parseQuestionsSheet(sheet) {
        const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
        const headerIndex = matrix.findIndex((row) =>
            String(row[0] ?? '').trim().toLowerCase() === 'question'
        );

        if (headerIndex === -1) {
            return XLSX.utils.sheet_to_json(sheet, { defval: '' });
        }

        const headers = matrix[headerIndex].map((cell) => String(cell ?? '').trim());

        return matrix
            .slice(headerIndex + 1)
            .filter((row) => row.some((cell) => String(cell ?? '').trim() !== ''))
            .map((row) => {
                const obj = {};
                headers.forEach((header, index) => {
                    if (header) {
                        obj[header] = row[index] ?? '';
                    }
                });
                return obj;
            });
    }

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

    function buildImportWorkbook(questionRows, metadata) {
        const info = normalizeImportMetadata(metadata);
        const wb = XLSX.utils.book_new();
        const qWs = XLSX.utils.aoa_to_sheet(buildQuestionsSheetAoa(info, questionRows));
        qWs['!cols'] = [
            { wch: 55 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 16 },
        ];
        XLSX.utils.book_append_sheet(wb, qWs, 'Questions');
        return wb;
    }

    function getSampleQuestionRows(subjectLabel) {
        const label = subjectLabel || 'this subject';
        return [
            {
                Question: `Sample question 1 for ${label}?`,
                Option_A: 'First choice',
                Option_B: 'Second choice',
                Option_C: 'Third choice (correct)',
                Option_D: 'Fourth choice',
                Option_E: 'Fifth choice',
                Correct_Answer: 'C',
            },
            {
                Question: `Sample question 2 for ${label}?`,
                Option_A: 'True statement',
                Option_B: 'Another option',
                Option_C: 'Correct choice',
                Option_D: 'Incorrect choice',
                Option_E: 'Extra option',
                Correct_Answer: 'C',
            },
        ];
    }

    /**
     * @param {object} options
     * @param {string} options.modalId
     * @param {string} options.fileInputId
     * @param {string} options.importBtnId
     * @param {string} options.validationId
     * @param {string} options.previewId
     * @param {string} options.previewCardsId
     * @param {string} options.previewTableWrapId
     * @param {string} options.previewTableId
     * @param {string} [options.downloadTemplateBtnId]
     * @param {string} [options.subjectNoteId]
     * @param {function(): string|null} options.getSubjectId
     * @param {function(): string} [options.getSubjectLabel]
     * @param {function(): {course?: string,module?: string,subject?: string}} [options.getImportMetadata]
     * @param {function(): boolean} [options.ensureSubjectSelected]
     * @param {'bank'|'exam'} [options.mode='bank']
     * @param {function(): number|null} [options.getExamId]
     * @param {function(Array): void} [options.onImportToCanvas] exam mode, unsaved exam
     * @param {function(object): void} [options.onSuccess]
     */
    function initQuestionImport(options) {
        const mode = options.mode === 'exam' ? 'exam' : 'bank';
        const modalEl = document.getElementById(options.modalId);
        const fileInput = document.getElementById(options.fileInputId);
        const importBtn = document.getElementById(options.importBtnId);
        if (!modalEl || !fileInput || !importBtn) {
            return;
        }

        let importData = [];
        let importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };

        function resetImportState() {
            importData = [];
            importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };
            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';

            const preview = document.getElementById(options.previewId);
            const validation = document.getElementById(options.validationId);
            if (preview) preview.style.display = 'none';
            if (validation) {
                validation.style.display = 'none';
                validation.innerHTML = '';
            }
            const cards = document.getElementById(options.previewCardsId);
            if (cards) {
                cards.innerHTML = '';
                cards.classList.add('d-none');
            }
            const tableWrap = document.getElementById(options.previewTableWrapId);
            if (tableWrap) tableWrap.classList.remove('d-none');
        }

        function displayImportValidation(result) {
            const container = document.getElementById(options.validationId);
            if (!container) return;

            const { validCount, invalidRows } = result;
            let html = '';

            if (validCount > 0) {
                html += `<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle me-2"></i><strong>${validCount}</strong> question${validCount === 1 ? '' : 's'} ready to import</div>`;
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
            if (!validCount && !invalidRows.length) {
                html = '<div class="alert alert-warning py-2 mb-0">No questions found. Upload an Excel file to import.</div>';
            }

            container.innerHTML = html;
            container.style.display = 'block';
        }

        function displayImportPreview(rows) {
            const preview = document.getElementById(options.previewId);
            const table = document.getElementById(options.previewTableId);
            if (!preview || !table || !rows.length) {
                if (preview) preview.style.display = 'none';
                return;
            }

            const displayColumns = ['Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Option_E', 'Correct_Answer'];
            table.querySelector('thead').innerHTML = `<tr>${displayColumns.map(h => `<th>${h}</th>`).join('')}</tr>`;
            table.querySelector('tbody').innerHTML = rows.slice(0, 5).map(row =>
                `<tr>${displayColumns.map(h => `<td>${escapeHtml(String(row[h] || ''))}</td>`).join('')}</tr>`
            ).join('');
            preview.style.display = 'block';
        }

        function setImportReadyState(rows, validationResult) {
            importData = rows;
            importValidationResult = validationResult;
            displayImportValidation(validationResult);
            displayImportPreview(rows);

            const canImport = validationResult.validCount > 0 && validationResult.invalidRows.length === 0;
            importBtn.disabled = !canImport;
        }

        function ensureSubject() {
            if (typeof options.ensureSubjectSelected === 'function') {
                return options.ensureSubjectSelected();
            }
            const subjectId = options.getSubjectId();
            if (!subjectId) {
                Swal.fire('Select Subject', 'A subject is required before importing.', 'warning');
                return false;
            }
            return true;
        }

        function processImportFileRows(rawRows) {
            if (!ensureSubject()) {
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

            setImportReadyState(rows, validateImportRows(rows));
        }

        function finishImportSuccess(responseData, importedCount) {
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
            fileInput.value = '';
            resetImportState();
            if (typeof options.onSuccess === 'function') {
                options.onSuccess(responseData || { imported: importedCount });
            }
        }

        function showImportResultDialog(responseData) {
            let errorDetails = '';
            if (responseData.errors && responseData.errors.length > 0) {
                errorDetails = '<div class="mt-3 text-start alert alert-danger"><strong>Error details:</strong><ul class="mb-0 mt-2">';
                responseData.errors.forEach(err => {
                    errorDetails += `<li>${escapeHtml(err)}</li>`;
                });
                errorDetails += '</ul></div>';
            }

            const destLabel = mode === 'exam'
                ? 'added to this exam (question bank after publish)'
                : 'imported to the question bank';

            return Swal.fire({
                title: responseData.failed > 0 ? 'Import Completed with Errors' : 'Success!',
                html: `
                    <div class="text-start">
                        <p><strong>Imported:</strong> ${responseData.imported || 0} question(s) ${destLabel}</p>
                        ${responseData.failed ? `<p><strong>Failed:</strong> ${responseData.failed} question(s)</p>` : ''}
                        ${errorDetails}
                    </div>
                `,
                icon: responseData.failed > 0 ? 'warning' : 'success',
                width: '700px',
            });
        }

        function performImport() {
            const subjectId = options.getSubjectId();
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';

            if (mode === 'exam') {
                const examId = typeof options.getExamId === 'function' ? options.getExamId() : null;
                if (!examId) {
                    try {
                        if (typeof options.onImportToCanvas === 'function') {
                            options.onImportToCanvas(importData);
                        }
                        showImportResultDialog({
                            imported: importValidationResult.validCount,
                            failed: 0,
                            errors: [],
                        }).then(() => {
                            finishImportSuccess({ imported: importValidationResult.validCount, questions: [] }, importValidationResult.validCount);
                        });
                    } catch (err) {
                        Swal.fire('Error', err.message || 'Failed to add imported questions', 'error');
                    } finally {
                        const blocked = importValidationResult.invalidRows.length > 0;
                        importBtn.disabled = importValidationResult.validCount === 0 || blocked;
                        importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';
                    }
                    return;
                }

                axios.post(EXAM_IMPORT_API, {
                    exam_id: examId,
                    subject_id: subjectId,
                    questions: importData,
                })
                    .then(response => {
                        if (!response.data.success) {
                            Swal.fire('Error', response.data.message || 'Import failed', 'error');
                            return;
                        }
                        showImportResultDialog(response.data).then(() => {
                            if (response.data.imported > 0) {
                                finishImportSuccess(response.data, response.data.imported);
                            }
                        });
                    })
                    .catch(error => {
                        const errorMsg = error.response?.data?.message || 'Failed to import questions.';
                        Swal.fire('Error', errorMsg, 'error');
                    })
                    .finally(() => {
                        const blocked = importValidationResult.invalidRows.length > 0;
                        importBtn.disabled = importValidationResult.validCount === 0 || blocked;
                        importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';
                    });
                return;
            }

            axios.post(BANK_IMPORT_API, {
                subject_id: subjectId,
                questions: importData,
            })
                .then(response => {
                    if (!response.data.success) {
                        Swal.fire('Error', response.data.message || 'Import failed', 'error');
                        return;
                    }
                    showImportResultDialog(response.data).then(() => {
                        if (response.data.imported > 0) {
                            finishImportSuccess(response.data, response.data.imported);
                        }
                    });
                })
                .catch(error => {
                    const errorMsg = error.response?.data?.message || 'Failed to import questions. Please check your file format.';
                    Swal.fire('Error', errorMsg, 'error');
                })
                .finally(() => {
                    const blocked = importValidationResult.invalidRows.length > 0;
                    importBtn.disabled = importValidationResult.validCount === 0 || blocked;
                    importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';
                });
        }

        modalEl.addEventListener('show.bs.modal', () => {
            resetImportState();
            fileInput.value = '';
            const noteEl = options.subjectNoteId ? document.getElementById(options.subjectNoteId) : null;
            if (noteEl && typeof options.getSubjectLabel === 'function') {
                const label = options.getSubjectLabel();
                if (mode === 'exam') {
                    noteEl.textContent = label
                        ? `Questions import into this exam for ${label}. They are exam drafts until you publish the exam (then they appear in the Question Bank).`
                        : 'Select course, module, and subject before importing.';
                } else {
                    noteEl.textContent = label
                        ? `Imported questions will be added to the question bank for: ${label}`
                        : 'Select an exam subject before importing.';
                }
            }
        });

        fileInput.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (!file) return;
            if (!ensureSubject()) {
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (evt) {
                try {
                    const data = new Uint8Array(evt.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheetName = workbook.SheetNames.find(name => name.toLowerCase() === 'questions') || workbook.SheetNames[0];
                    const sheet = workbook.Sheets[sheetName];
                    const jsonData = parseQuestionsSheet(sheet);
                    processImportFileRows(jsonData);
                } catch (error) {
                    console.error('Error reading file:', error);
                    Swal.fire('Error', 'Failed to read file. Please check the format.', 'error');
                    resetImportState();
                }
            };
            reader.readAsArrayBuffer(file);
        });

        importBtn.addEventListener('click', () => {
            if (importData.length === 0 || importValidationResult.validCount === 0) return;
            if (importValidationResult.invalidRows.length > 0) return;

            const confirmText = mode === 'exam'
                ? `This will add ${importValidationResult.validCount} question${importValidationResult.validCount === 1 ? '' : 's'} to the exam (not the Question Bank until publish)`
                : `This will import ${importValidationResult.validCount} question${importValidationResult.validCount === 1 ? '' : 's'} into the question bank`;

            Swal.fire({
                title: 'Import Questions?',
                text: confirmText,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, import',
                confirmButtonColor: '#2563eb',
            }).then(result => {
                if (result.isConfirmed) {
                    performImport();
                }
            });
        });

        if (options.downloadTemplateBtnId) {
            const dlBtn = document.getElementById(options.downloadTemplateBtnId);
            dlBtn?.addEventListener('click', () => {
                if (!ensureSubject()) return;
                const subjectLabel = typeof options.getSubjectLabel === 'function' ? options.getSubjectLabel() : '';
                const metadata = typeof options.getImportMetadata === 'function'
                    ? normalizeImportMetadata(options.getImportMetadata())
                    : normalizeImportMetadata({ subject: subjectLabel });
                const wb = buildImportWorkbook(getSampleQuestionRows(metadata.subject || subjectLabel), metadata);
                const filenameSubject = String(metadata.subject || subjectLabel || 'template').replace(/[^a-z0-9_-]+/gi, '_');
                XLSX.writeFile(wb, `question_import_${filenameSubject}.xlsx`);
            });
        }
    }

    global.QuestionImport = {
        init: initQuestionImport,
        buildImportWorkbook,
        parseQuestionsSheet,
        buildQuestionsSheetAoa,
    };
})(window);
