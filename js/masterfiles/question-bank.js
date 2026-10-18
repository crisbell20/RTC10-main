        const CHOICE_COUNT = 5;
        const MODULES_API = '../../api/masterfiles/modules.php';
        let allCourses = [];
        let allSubjects = [];
        let modulesEnabled = false;
        let modulesByCourse = {};
        let subjectsByModule = {};
        let currentFilterModule = '';

        function renderAnswerOptions(existingAnswers = [], targetId = 'editAnswersList', radioName = 'editCorrectAnswer') {
            const answersList = document.getElementById(targetId);
            if (!answersList) return;
            answersList.innerHTML = '';

            for (let i = 0; i < CHOICE_COUNT; i++) {
                const label = String.fromCharCode(65 + i);
                const existing = existingAnswers[i] || null;
                const answerDiv = document.createElement('div');
                answerDiv.className = 'input-group mb-2';
                const isCorrect = existing && String(existing.Is_Correct) === '1';
                const noCorrectMarked = !existingAnswers.some(a => String(a.Is_Correct) === '1');
                answerDiv.innerHTML = `
                    <span class="input-group-text">
                        <input type="radio" name="${radioName}" value="${i}" ${isCorrect || (noCorrectMarked && i === 0) ? 'checked' : ''} required>
                    </span>
                    <span class="input-group-text fw-bold">${label}</span>
                    <input type="text" class="form-control" placeholder="Option ${label}" data-answer-index="${i}" value="${existing ? escapeHtml(existing.Choice_Text) : ''}" required>
                `;
                answersList.appendChild(answerDiv);
            }
        }

        function updateBuilderContextLabel() {
            const label = document.getElementById('builderContextLabel');
            if (!label) return;

            const courseId = document.getElementById('builderCourse')?.value || '';
            const moduleId = document.getElementById('builderModule')?.value || '';
            const subjectId = document.getElementById('builderSubject')?.value || '';
            const course = allCourses.find(c => String(c.Course_ID) === String(courseId));
            const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
            const module = (modulesByCourse[courseId] || []).find(m => String(m.Module_ID) === String(moduleId));

            if (!courseId || !subjectId || (modulesEnabled && !moduleId)) {
                label.textContent = 'Select course, module, and subject';
                return;
            }

            const subjectLabel = subject
                ? subject.Subject_Name
                : 'Selected subject';
            const modulePart = module ? `${module.Module_Name} → ` : '';
            label.textContent = `${course?.Course_Name || 'Course'} → ${modulePart}${subjectLabel}`;
        }

        function getBuilderSubjectId() {
            return document.getElementById('builderSubject')?.value || '';
        }

        function getCourseName(courseId) {
            const course = allCourses.find(c => String(c.Course_ID) === String(courseId));
            return course ? course.Course_Name : '';
        }

        function setModuleFieldsVisible(visible) {
            ['filterModuleWrap', 'importModuleWrap', 'builderModuleWrap', 'editQuestionModuleWrap'].forEach(id => {
                document.getElementById(id)?.classList.toggle('d-none', !visible);
            });
            ['importModule', 'builderModule', 'editQuestionModule'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.required = visible;
            });
        }

        function buildCurriculumMaps(tree) {
            modulesByCourse = {};
            subjectsByModule = {};
            allSubjects = [];

            (tree || []).forEach(course => {
                modulesByCourse[course.Course_ID] = course.modules || [];
                (course.modules || []).forEach(mod => {
                    const subjects = (mod.subjects || []).map(s => ({
                        ...s,
                        Module_ID: mod.Module_ID,
                        Module_Name: mod.Module_Name,
                        Course_ID: course.Course_ID,
                    }));
                    subjectsByModule[mod.Module_ID] = subjects;
                    allSubjects.push(...subjects);
                });
            });
        }

        function populateModuleOptions(selectEl, courseId, placeholder, selectedId) {
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder || 'All modules'}</option>`;
            if (!courseId) {
                selectEl.disabled = true;
                return;
            }
            const modules = modulesByCourse[courseId] || [];
            modules.forEach(mod => {
                const option = new Option(mod.Module_Name, mod.Module_ID);
                if (selectedId && String(selectedId) === String(mod.Module_ID)) {
                    option.selected = true;
                }
                selectEl.add(option);
            });
            selectEl.disabled = modules.length === 0;
        }

        function populateSubjectOptions(selectEl, options = {}) {
            const {
                courseId = '',
                moduleId = '',
                placeholder = 'All subjects',
                selectedId = '',
            } = options;
            if (!selectEl) return;
            selectEl.innerHTML = `<option value="">${placeholder}</option>`;

            let list = [];
            if (modulesEnabled && moduleId) {
                list = subjectsByModule[moduleId] || [];
            } else if (modulesEnabled && courseId) {
                list = allSubjects.filter(s => String(s.Course_ID) === String(courseId));
            } else if (courseId) {
                list = allSubjects.filter(s => String(s.Course_ID) === String(courseId));
            } else {
                list = allSubjects;
            }

            list.forEach(subject => {
                const option = new Option(
                    subject.Subject_Name,
                    subject.Subject_ID
                );
                selectEl.add(option);
            });

            if (selectedId) {
                selectEl.value = selectedId;
            }

            if (selectEl.id === 'importSubject' || selectEl.id === 'builderSubject' || selectEl.id === 'editQuestionSubject') {
                selectEl.disabled = modulesEnabled ? !moduleId : !courseId;
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

        function hasActiveFilters() {
            return !!(document.getElementById('filterCourse').value
                || document.getElementById('filterModule')?.value
                || document.getElementById('filterSubject').value
                || document.getElementById('searchQuestion').value.trim());
        }

        function updateFilterSummary(count) {
            const summary = document.getElementById('questionsFilterSummary');
            const clearBtn = document.getElementById('clearFiltersBtn');
            const courseId = document.getElementById('filterCourse').value;
            const moduleId = document.getElementById('filterModule')?.value || '';
            const subjectId = document.getElementById('filterSubject').value;
            const base = `Showing ${count} question${count === 1 ? '' : 's'}`;
            const labels = [];

            if (courseId) {
                const courseName = getCourseName(courseId);
                if (courseName) labels.push(courseName);
            }
            if (moduleId) {
                const mod = (modulesByCourse[courseId] || []).find(m => String(m.Module_ID) === String(moduleId));
                if (mod) labels.push(mod.Module_Name);
            }
            if (subjectId) {
                const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
                if (subject) labels.push(subject.Subject_Name);
            }

            summary.textContent = labels.length ? `${base} for ${labels.join(', ')}` : base;
            clearBtn.style.display = hasActiveFilters() ? 'inline-block' : 'none';
            updateFiltersToggleState();
        }

        function clearFilters() {
            document.getElementById('filterCourse').value = '';
            currentFilterModule = '';
            document.getElementById('filterModule').value = '';
            document.getElementById('filterSubject').value = '';
            document.getElementById('searchQuestion').value = '';
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('filterModule'), '', 'All modules');
            }
            populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
            loadQuestions();
        }

        function loadCourses() {
            return axios.get('../../api/masterfiles/courses.php?action=list')
                .then(response => {
                    if (response.data.success) {
                        allCourses = response.data.data || [];
                        const filterSelect = document.getElementById('filterCourse');
                        const builderCourseSelect = document.getElementById('builderCourse');
                        const editCourseSelect = document.getElementById('editQuestionCourse');

                        filterSelect.innerHTML = '<option value="">All courses</option>';
                        builderCourseSelect.innerHTML = '<option value="">Select course...</option>';
                        editCourseSelect.innerHTML = '<option value="">Select course...</option>';

                        allCourses.forEach(course => {
                            filterSelect.add(new Option(course.Course_Name, course.Course_ID));
                            builderCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                            editCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                        });

                        const importCourseSelect = document.getElementById('importCourse');
                        if (importCourseSelect) {
                            importCourseSelect.innerHTML = '<option value="">Select course...</option>';
                            allCourses.forEach(course => {
                                importCourseSelect.add(new Option(course.Course_Name, course.Course_ID));
                            });
                        }
                    }
                })
                .catch(error => console.error('Error loading courses:', error));
        }

        function loadCurriculumData() {
            return axios.get(`${MODULES_API}?action=tree`)
                .then(response => {
                    if (response.data.success) {
                        modulesEnabled = true;
                        buildCurriculumMaps(response.data.data || []);
                        setModuleFieldsVisible(true);
                        populateModuleOptions(document.getElementById('filterModule'), '', 'All modules');
                        populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
                        return;
                    }
                    return loadSubjectsLegacy();
                })
                .catch(err => {
                    if (err.response?.status === 503) {
                        return loadSubjectsLegacy();
                    }
                    console.error('Error loading curriculum:', err);
                    return loadSubjectsLegacy();
                });
        }

        function loadSubjectsLegacy() {
            modulesEnabled = false;
            setModuleFieldsVisible(false);
            return axios.get('../../api/masterfiles/subjects.php?action=list')
                .then(response => {
                    if (response.data.success) {
                        allSubjects = response.data.data || [];
                        populateSubjectOptions(document.getElementById('filterSubject'), { placeholder: 'All subjects' });
                    }
                })
                .catch(error => console.error('Error loading subjects:', error));
        }

        function loadSubjectsData() {
            return loadCurriculumData();
        }

        function countCurriculumFilters() {
            let count = 0;
            if (document.getElementById('filterCourse')?.value) count++;
            if (document.getElementById('filterModule')?.value) count++;
            if (document.getElementById('filterSubject')?.value) count++;
            return count;
        }

        function updateFiltersToggleState() {
            const toggle = document.getElementById('qbFiltersToggle');
            const countEl = document.getElementById('qbFilterCount');
            if (!toggle || !countEl) return;

            const activeCount = countCurriculumFilters();
            toggle.classList.toggle('has-active-filters', activeCount > 0);
            if (activeCount > 0) {
                countEl.textContent = String(activeCount);
                countEl.classList.remove('d-none');
            } else {
                countEl.textContent = '';
                countEl.classList.add('d-none');
            }
        }

        function buildQuestionActionMenu(questionId) {
            const items = [
                { action: 'edit-question', attrs: `data-question-id="${questionId}"`, label: '<i class="bi bi-pencil me-2"></i>Edit' },
                { action: 'archive-question', attrs: `data-question-id="${questionId}"`, label: '<i class="bi bi-archive me-2"></i>Archive' },
            ];
            const buildMenu = window.buildStaffActionMenu || window.buildActionMenu;
            if (typeof buildMenu === 'function') {
                return buildMenu(items);
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

        function onQuestionsContainerClick(e) {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;

            const action = btn.dataset.action;
            const questionId = btn.dataset.questionId;
            if (action === 'edit-question') {
                editQuestion(questionId);
            } else if (action === 'archive-question') {
                archiveQuestion(questionId);
            }
        }

        document.getElementById('questionsContainer').addEventListener('click', onQuestionsContainerClick);

        function loadQuestions() {
            const courseFilter = document.getElementById('filterCourse').value;
            const moduleFilter = document.getElementById('filterModule')?.value || '';
            const subjectFilter = document.getElementById('filterSubject').value;
            const searchTerm = document.getElementById('searchQuestion').value.trim();

            let url = '../../api/masterfiles/exam.php?action=get_questions';
            if (courseFilter) url += `&course_id=${encodeURIComponent(courseFilter)}`;
            if (moduleFilter) url += `&module_id=${encodeURIComponent(moduleFilter)}`;
            if (subjectFilter) url += `&subject_id=${encodeURIComponent(subjectFilter)}`;
            if (searchTerm) url += `&search=${encodeURIComponent(searchTerm)}`;

            axios.get(url)
                .then(response => {
                    const container = document.getElementById('questionsContainer');
                    const questions = response.data.success ? (response.data.questions || []) : [];

                    updateFilterSummary(questions.length);

                    if (questions.length > 0) {
                        container.innerHTML = questions.map(q => `
                            <div class="question-card">
                                <div class="question-card-header">
                                    <div class="question-text">${escapeHtml(q.Question_Text)}</div>
                                    <div class="staff-row-actions">
                                        ${buildQuestionActionMenu(q.Question_ID)}
                                    </div>
                                </div>
                                <div class="question-answers mt-1">
                                    ${q.answers ? q.answers.map(a => `
                                        <div class="answer-option ${a.Is_Correct ? 'correct' : ''}">
                                            ${a.Is_Correct ? '<i class="bi bi-check-circle-fill text-success me-2"></i>' : ''}
                                            ${escapeHtml(a.Choice_Text)}
                                        </div>
                                    `).join('') : '<small class="text-muted">No answers available</small>'}
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="text-center text-muted py-4">No questions found</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading questions:', error);
                    document.getElementById('questionsContainer').innerHTML =
                        '<div class="text-center text-danger py-4">Error loading questions</div>';
                });
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Edit question form submission
        document.getElementById('editQuestionForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const questionId = document.getElementById('editQuestionId').value;
            const formData = {
                subject_id: document.getElementById('editQuestionSubject').value,
                question_text: document.getElementById('editQuestionText').value,
                question_type: 'Multiple Choice',
                points: 1,
                answers: []
            };

            const answerInputs = document.querySelectorAll('#editAnswersList input[type="text"]');
            const correctAnswerIndex = parseInt(document.querySelector('input[name="editCorrectAnswer"]:checked')?.value ?? '0', 10);

            if (answerInputs.length !== CHOICE_COUNT) {
                Swal.fire('Error', `Exactly ${CHOICE_COUNT} answer options are required`, 'error');
                return;
            }

            answerInputs.forEach((input, index) => {
                const text = input.value.trim();
                if (!text) {
                    return;
                }
                formData.answers.push({
                    answer_text: text,
                    is_correct: index === correctAnswerIndex
                });
            });

            if (formData.answers.length !== CHOICE_COUNT) {
                Swal.fire('Error', `Please fill in all ${CHOICE_COUNT} answer options (A through E)`, 'error');
                return;
            }

            axios.post(`../../api/masterfiles/exam.php?action=update_question&question_id=${questionId}`, formData)
                .then(response => {
                    if (response.data.success) {
                        Swal.fire('Success', response.data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('editQuestionModal')).hide();
                        loadQuestions();
                    } else {
                        Swal.fire('Error', response.data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to update question', 'error');
                });
        });

        // Edit question
        function editQuestion(questionId) {
            // Find the question in the loaded questions
            axios.get(`../../api/masterfiles/exam.php?action=get_questions`)
                .then(response => {
                    if (response.data.success) {
                        const question = response.data.questions.find(q => q.Question_ID == questionId);
                        if (!question) {
                            Swal.fire('Error', 'Question not found', 'error');
                            return;
                        }

                        // Populate the form
                        document.getElementById('editQuestionId').value = question.Question_ID;
                        const subject = allSubjects.find(s => String(s.Subject_ID) === String(question.Subject_ID));
                        const courseId = subject ? subject.Course_ID : '';
                        document.getElementById('editQuestionCourse').value = courseId || '';
                        const moduleId = subject ? (subject.Module_ID || findModuleIdForSubject(subject.Subject_ID, courseId)) : '';
                        if (modulesEnabled) {
                            populateModuleOptions(document.getElementById('editQuestionModule'), courseId, 'Select module...', moduleId);
                        }
                        populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                            courseId,
                            moduleId,
                            placeholder: 'Select subject...',
                            selectedId: question.Subject_ID,
                        });
                        document.getElementById('editQuestionText').value = question.Question_Text;

                        const paddedAnswers = [...(question.answers || [])];
                        while (paddedAnswers.length < CHOICE_COUNT) {
                            paddedAnswers.push({ Choice_Text: '', Is_Correct: 0 });
                        }
                        renderAnswerOptions(paddedAnswers.slice(0, CHOICE_COUNT));

                        bootstrap.Modal.getOrCreateInstance(document.getElementById('editQuestionModal')).show();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Failed to load question', 'error');
                });
        }

        // Archive question
        function archiveQuestion(questionId) {
            Swal.fire({
                title: 'Archive Question?',
                html: 'The question will be hidden from the bank and permanently removed after <strong>2 years</strong>. You can restore it from the Archive page.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Archive',
                confirmButtonColor: '#d97706'
            }).then((result) => {
                if (result.isConfirmed) {
                    axios.post(`../../api/masterfiles/exam.php?action=archive_question`, { question_id: questionId })
                        .then(response => {
                            if (response.data.success) {
                                Swal.fire('Archived', response.data.message, 'success');
                                loadQuestions();
                            } else {
                                Swal.fire('Error', response.data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            const errorMsg = error.response?.data?.message || 'Failed to archive question';
                            Swal.fire('Error', errorMsg, 'error');
                        });
                }
            });
        }

        // Filter handlers
        document.getElementById('filterCourse').addEventListener('change', function() {
            const courseId = this.value;
            currentFilterModule = '';
            document.getElementById('filterModule').value = '';
            document.getElementById('filterSubject').value = '';
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('filterModule'), courseId, 'All modules');
            }
            populateSubjectOptions(document.getElementById('filterSubject'), {
                courseId,
                placeholder: 'All subjects',
            });
            loadQuestions();
        });
        document.getElementById('filterModule')?.addEventListener('change', function() {
            currentFilterModule = this.value || '';
            document.getElementById('filterSubject').value = '';
            populateSubjectOptions(document.getElementById('filterSubject'), {
                courseId: document.getElementById('filterCourse').value,
                moduleId: currentFilterModule,
                placeholder: 'All subjects',
            });
            loadQuestions();
        });
        document.getElementById('filterSubject').addEventListener('change', loadQuestions);
        document.getElementById('searchQuestion').addEventListener('input', debounce(loadQuestions, 500));
        document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);

        document.getElementById('builderCourse')?.addEventListener('change', function() {
            const courseId = this.value;
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('builderModule'), courseId, 'Select module...');
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
            } else {
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
            }
            updateBuilderContextLabel();
        });

        document.getElementById('builderModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('builderSubject'), {
                courseId: document.getElementById('builderCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
            updateBuilderContextLabel();
        });

        document.getElementById('builderSubject')?.addEventListener('change', updateBuilderContextLabel);

        document.getElementById('editQuestionCourse')?.addEventListener('change', function() {
            const courseId = this.value;
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('editQuestionModule'), courseId, 'Select module...');
                populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                    courseId,
                    placeholder: 'Select subject...',
                });
                return;
            }
            populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                courseId,
                placeholder: 'Select subject...',
            });
        });

        document.getElementById('editQuestionModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('editQuestionSubject'), {
                courseId: document.getElementById('editQuestionCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
        });

        // Debounce function
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Initialize
        Promise.all([loadCourses(), loadSubjectsData()])
            .then(() => {
                QuestionBuilder.init({
                    canvasId: 'builderQuestionCanvas',
                    countId: 'builderQuestionCount',
                    addBtnId: 'builderAddQuestionBtn',
                    clearBtnId: 'builderClearBtn',
                    getSubjectId: getBuilderSubjectId,
                    subjectRequiredMessage: modulesEnabled
                        ? 'Please select course, module, and subject first'
                        : 'Please select course and subject first',
                    onError: (message) => Swal.fire('Cannot add question', message, 'warning'),
                    onCountChange: (count) => {
                        const saveBtn = document.getElementById('saveBuilderQuestionsBtn');
                        if (saveBtn) {
                            saveBtn.textContent = count > 0 ? `Save ${count} Question${count === 1 ? '' : 's'}` : 'Save Questions';
                        }
                    }
                });
                return loadQuestions();
            })
            .catch(error => {
                console.error('Error initializing question bank:', error);
                document.getElementById('questionsContainer').innerHTML =
                    '<div class="text-center text-danger py-4">Failed to load question bank. Please refresh the page.</div>';
                document.getElementById('questionsFilterSummary').textContent = 'Error loading filters';
            });

        document.getElementById('addQuestionsModal').addEventListener('show.bs.modal', function() {
            QuestionBuilder.clear();

            const filterCourse = document.getElementById('filterCourse').value;
            const filterModule = document.getElementById('filterModule')?.value || '';
            const filterSubject = document.getElementById('filterSubject').value;

            document.getElementById('builderSubject').disabled = true;
            document.getElementById('builderSubject').innerHTML = '<option value="">Select subject...</option>';
            if (modulesEnabled) {
                document.getElementById('builderModule').disabled = true;
                document.getElementById('builderModule').innerHTML = '<option value="">Select module...</option>';
            }

            if (filterCourse) {
                document.getElementById('builderCourse').value = filterCourse;
                if (modulesEnabled) {
                    populateModuleOptions(document.getElementById('builderModule'), filterCourse, 'Select module...', filterModule || '');
                }
                populateSubjectOptions(document.getElementById('builderSubject'), {
                    courseId: filterCourse,
                    moduleId: filterModule,
                    placeholder: 'Select subject...',
                    selectedId: filterSubject || '',
                });
            } else {
                document.getElementById('builderCourse').value = '';
            }

            updateBuilderContextLabel();
            const saveBtn = document.getElementById('saveBuilderQuestionsBtn');
            if (saveBtn) saveBtn.textContent = 'Save Questions';
        });

        document.getElementById('saveBuilderQuestionsBtn').addEventListener('click', async function() {
            const saveBtn = this;
            saveBtn.disabled = true;
            try {
                const savedCount = await QuestionBuilder.saveAll();
                bootstrap.Modal.getInstance(document.getElementById('addQuestionsModal')).hide();
                Swal.fire('Success', `${savedCount} question${savedCount === 1 ? '' : 's'} added to the bank`, 'success');
                loadQuestions();
            } catch (err) {
                Swal.fire('Error', err.response?.data?.message || err.message || 'Failed to save questions', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        // Import functionality
        let importData = [];
        let importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };

        const IMPORT_COLUMN_ALIASES = {
            Question: ['Question', 'question', 'QUESTION', 'Question Text', 'Question_Text'],
            Option_A: ['Option_A', 'Option A', 'option_a', 'A'],
            Option_B: ['Option_B', 'Option B', 'option_b', 'B'],
            Option_C: ['Option_C', 'Option C', 'option_c', 'C'],
            Option_D: ['Option_D', 'Option D', 'option_d', 'D'],
            Option_E: ['Option_E', 'Option E', 'option_e', 'E'],
            Correct_Answer: ['Correct_Answer', 'Correct Answer', 'correct_answer', 'Answer', 'Correct'],
            Subject: ['Subject', 'subject', 'Subject_Name']
        };

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

        function getSelectedImportSubjectId() {
            return document.getElementById('importSubject')?.value || '';
        }

        function ensureImportSubjectSelected() {
            if (!getSelectedImportSubjectId()) {
                const msg = modulesEnabled
                    ? 'Please select a course, module, and subject first.'
                    : 'Please select a course and subject first.';
                Swal.fire('Select Subject', msg, 'warning');
                return false;
            }
            return true;
        }

        function resetImportState() {
            importData = [];
            importValidationResult = { validCount: 0, invalidRows: [], invalidBlocks: [] };
            document.getElementById('importBtn').disabled = true;
            document.getElementById('importValidation').style.display = 'none';
            document.getElementById('importValidation').innerHTML = '';
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

        function setImportReadyState(rows, validationResult) {
            importData = rows;
            importValidationResult = validationResult;
            displayImportValidation(validationResult);

            const canImport = validationResult.validCount > 0
                && validationResult.invalidRows.length === 0
                && validationResult.invalidBlocks.length === 0;
            document.getElementById('importBtn').disabled = !canImport;
        }

        function displayImportValidation(result) {
            const container = document.getElementById('importValidation');
            if (!container) return;

            const { validCount, invalidRows, invalidBlocks } = result;
            let html = '';

            if (validCount > 0) {
                html += `<div class="alert alert-success py-2 mb-2"><i class="bi bi-check-circle me-2"></i><strong>${validCount}</strong> question${validCount === 1 ? '' : 's'} ready to import</div>`;
            }

            if (invalidBlocks.length > 0) {
                html += `<div class="alert alert-danger py-2 mb-2"><strong>${invalidBlocks.length} question block${invalidBlocks.length === 1 ? '' : 's'} with errors:</strong><ul class="mb-0 mt-2 small">`;
                invalidBlocks.slice(0, 15).forEach(item => {
                    const label = item.preview ? escapeHtml(item.preview.substring(0, 80)) : `Block ${item.blockNum}`;
                    html += `<li><strong>Question ${item.blockNum}:</strong> ${label}${item.preview && item.preview.length > 80 ? '...' : ''}<br><span class="text-muted">${escapeHtml(item.errors.join('; '))}</span></li>`;
                });
                if (invalidBlocks.length > 15) {
                    html += `<li>...and ${invalidBlocks.length - 15} more</li>`;
                }
                html += '</ul></div>';
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

            if (!validCount && !invalidRows.length && !invalidBlocks.length) {
                html = '<div class="alert alert-warning py-2 mb-0">No questions found. Upload an Excel file to import.</div>';
            }

            container.innerHTML = html;
            container.style.display = 'block';
        }

        function processImportFileRows(rawRows) {
            if (!ensureImportSubjectSelected()) {
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

            const validationResult = validateImportRows(rows);
            setImportReadyState(rows, validationResult);
        }

        document.getElementById('importQuestionsModal').addEventListener('show.bs.modal', function() {
            resetImportState();
            document.getElementById('importForm')?.reset();
            document.getElementById('importSubject').disabled = true;
            document.getElementById('importSubject').innerHTML = '<option value="">Select subject...</option>';
            if (modulesEnabled) {
                document.getElementById('importModule').disabled = true;
                document.getElementById('importModule').innerHTML = '<option value="">Select module...</option>';
            }

            const filterCourse = document.getElementById('filterCourse').value;
            const filterModule = document.getElementById('filterModule')?.value || '';
            const filterSubject = document.getElementById('filterSubject').value;
            if (filterCourse) {
                document.getElementById('importCourse').value = filterCourse;
                if (modulesEnabled) {
                    populateModuleOptions(document.getElementById('importModule'), filterCourse, 'Select module...', filterModule || '');
                }
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: filterCourse,
                    moduleId: filterModule,
                    placeholder: 'Select subject...',
                    selectedId: filterSubject || '',
                });
            }
        });

        document.getElementById('importCourse').addEventListener('change', function() {
            if (modulesEnabled) {
                populateModuleOptions(document.getElementById('importModule'), this.value, 'Select module...');
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: this.value,
                    placeholder: 'Select subject...',
                });
            } else {
                populateSubjectOptions(document.getElementById('importSubject'), {
                    courseId: this.value,
                    placeholder: 'Select subject...',
                });
            }
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importModule')?.addEventListener('change', function() {
            populateSubjectOptions(document.getElementById('importSubject'), {
                courseId: document.getElementById('importCourse').value,
                moduleId: this.value,
                placeholder: 'Select subject...',
            });
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importSubject').addEventListener('change', function() {
            resetImportState();
            document.getElementById('importFile').value = '';
        });

        document.getElementById('importFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            if (!ensureImportSubjectSelected()) {
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(evt) {
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

        document.getElementById('importBtn').addEventListener('click', function() {
            if (importData.length === 0 || importValidationResult.validCount === 0) return;
            if (importValidationResult.invalidRows.length > 0 || importValidationResult.invalidBlocks.length > 0) return;

            Swal.fire({
                title: 'Import Questions?',
                text: `This will import ${importValidationResult.validCount} question${importValidationResult.validCount === 1 ? '' : 's'}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, import',
                confirmButtonColor: '#2563eb'
            }).then((result) => {
                if (result.isConfirmed) {
                    performImport();
                }
            });
        });

        function performImport() {
            const importBtn = document.getElementById('importBtn');
            const subjectId = getSelectedImportSubjectId();
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Importing...';

            axios.post('../../api/masterfiles/exam.php?action=import_questions', {
                subject_id: subjectId,
                questions: importData
            })
            .then(response => {
                if (response.data.success) {
                    let errorDetails = '';
                    if (response.data.errors && response.data.errors.length > 0) {
                        errorDetails = '<div class="mt-3 text-start alert alert-danger"><strong>Error details:</strong><ul class="mb-0 mt-2">';
                        response.data.errors.forEach(err => {
                            errorDetails += `<li>${escapeHtml(err)}</li>`;
                        });
                        errorDetails += '</ul></div>';
                    }

                    Swal.fire({
                        title: response.data.failed > 0 ? 'Import Completed with Errors' : 'Success!',
                        html: `
                            <div class="text-start">
                                <p><strong>Imported:</strong> ${response.data.imported || 0} questions</p>
                                ${response.data.failed ? `<p><strong>Failed:</strong> ${response.data.failed} questions</p>` : ''}
                                ${errorDetails}
                            </div>
                        `,
                        icon: response.data.failed > 0 ? 'warning' : 'success',
                        width: '700px'
                    });

                    if (response.data.imported > 0) {
                        bootstrap.Modal.getInstance(document.getElementById('importQuestionsModal')).hide();
                        loadQuestions();
                        document.getElementById('importForm').reset();
                        resetImportState();
                    }
                } else {
                    Swal.fire('Error', response.data.message || 'Import failed', 'error');
                }
            })
            .catch(error => {
                console.error('Import error:', error);
                const errorMsg = error.response?.data?.message || 'Failed to import questions. Please check your file format.';
                Swal.fire('Error', errorMsg, 'error');
            })
            .finally(() => {
                const blocked = importValidationResult.invalidRows.length > 0
                    || importValidationResult.invalidBlocks.length > 0;
                importBtn.disabled = importValidationResult.validCount === 0 || blocked;
                importBtn.innerHTML = '<i class="bi bi-upload me-2"></i>Import Questions';
            });
        }

        function parseQuestionsSheet(sheet) {
            const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
            const headerIndex = matrix.findIndex(row =>
                String(row[0] ?? '').trim().toLowerCase() === 'question'
            );

            if (headerIndex === -1) {
                return XLSX.utils.sheet_to_json(sheet, { defval: '' });
            }

            const headers = matrix[headerIndex].map(cell => String(cell ?? '').trim());

            return matrix
                .slice(headerIndex + 1)
                .filter(row => row.some(cell => String(cell ?? '').trim() !== ''))
                .map(row => {
                    const obj = {};
                    headers.forEach((header, index) => {
                        if (header) {
                            obj[header] = row[index] ?? '';
                        }
                    });
                    return obj;
                });
        }

        function getImportMetadataFromForm(subject) {
            const courseSelect = document.getElementById('importCourse');
            const moduleSelect = document.getElementById('importModule');
            return {
                course: courseSelect?.selectedOptions?.[0]?.text?.trim() || subject?.Course_Name || '',
                module: modulesEnabled
                    ? (moduleSelect?.selectedOptions?.[0]?.text?.trim() || subject?.Module_Name || '')
                    : '',
                subject: subject?.Subject_Name || '',
            };
        }

        function buildImportWorkbook(questionRows, metadata) {
            const info = {
                course: String(metadata?.course ?? '').trim(),
                module: String(metadata?.module ?? '').trim(),
                subject: String(metadata?.subject ?? '').trim(),
            };
            const wb = XLSX.utils.book_new();
            const headers = ['Question', 'Option_A', 'Option_B', 'Option_C', 'Option_D', 'Option_E', 'Correct_Answer'];
            const sheetRows = [
                ['Course', info.course],
                ['Module', info.module],
                ['Subject', info.subject],
                [],
                headers,
                ...questionRows.map(row => headers.map(header => row[header] ?? '')),
            ];
            const qWs = XLSX.utils.aoa_to_sheet(sheetRows);
            qWs['!cols'] = [
                { wch: 55 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 28 }, { wch: 16 }
            ];
            XLSX.utils.book_append_sheet(wb, qWs, 'Questions');
            return wb;
        }

        function getSampleQuestionRows(subject) {
            const subjectLabel = subject ? subject.Subject_Name : 'your subject';
            return [
                {
                    Question: `Sample question 1 for ${subjectLabel}?`,
                    Option_A: 'First choice',
                    Option_B: 'Second choice',
                    Option_C: 'Third choice (correct)',
                    Option_D: 'Fourth choice',
                    Option_E: 'Fifth choice',
                    Correct_Answer: 'C'
                },
                {
                    Question: `Sample question 2 for ${subjectLabel}?`,
                    Option_A: 'True statement',
                    Option_B: 'Another option',
                    Option_C: 'Correct choice',
                    Option_D: 'Incorrect choice',
                    Option_E: 'Extra option',
                    Correct_Answer: 'C'
                }
            ];
        }

        function downloadImportTemplate() {
            const subjectId = getSelectedImportSubjectId();
            if (!subjectId) {
                Swal.fire('Select Subject', 'Please select a course and subject first.', 'warning');
                return;
            }

            const subject = allSubjects.find(s => String(s.Subject_ID) === String(subjectId));
            const metadata = getImportMetadataFromForm(subject);
            const wb = buildImportWorkbook(getSampleQuestionRows(subject), metadata);
            const filenameSubject = subject?.Subject_Name || 'template';
            XLSX.writeFile(wb, `question_import_${String(filenameSubject).replace(/[^a-z0-9_-]+/gi, '_')}.xlsx`);
        }

        document.getElementById('downloadImportTemplateBtn').addEventListener('click', downloadImportTemplate);
    