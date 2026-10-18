// Question builder

(function(global) {
    const CHOICE_COUNT = 5;
    let canvas = [];
    let draftCounter = 0;
    let dragIndex = null;
    let config = {
        canvasId: 'builderQuestionCanvas',
        countId: 'builderQuestionCount',
        addBtnId: 'builderAddQuestionBtn',
        clearBtnId: 'builderClearBtn',
        apiUrl: '../../api/masterfiles/exam.php',
        getSubjectId: () => '',
        getSubjectLabel: () => '',
        onCountChange: null
    };

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function isChoiceCorrect(choice) {
        return String(choice.Is_Correct) === '1' || choice.Is_Correct === 1 || choice.Is_Correct === true;
    }

    function getCorrectIndexFromChoices(choices) {
        const index = (choices || []).findIndex(choice => isChoiceCorrect(choice));
        return index >= 0 ? index : 0;
    }

    function createEmptyChoices(correctIndex = 0) {
        return Array.from({ length: CHOICE_COUNT }, (_, index) => ({
            Choice_Text: '',
            Is_Correct: index === correctIndex ? 1 : 0
        }));
    }

    function createDraft() {
        draftCounter += 1;
        return {
            isDraft: true,
            draftId: `draft_${Date.now()}_${draftCounter}`,
            Question_Text: '',
            correctIndex: 0,
            choices: createEmptyChoices(0)
        };
    }

    function getEditKey(question) {
        return question.draftId;
    }

    function collectFromDom() {
        const container = document.getElementById(config.canvasId);
        if (!container) {
            return;
        }

        container.querySelectorAll('.exam-question-card-draft').forEach(card => {
            const draftId = card.dataset.draftId;
            const question = canvas.find(q => q.draftId === draftId);
            if (!question) {
                return;
            }

            question.Question_Text = card.querySelector('.draft-question-text')?.value.trim() || '';
            const choiceInputs = card.querySelectorAll('.draft-choice-input');
            const correctIndex = parseInt(
                card.querySelector(`input[name="correct_${draftId}"]:checked`)?.value ?? '0',
                10
            );
            question.correctIndex = correctIndex;
            question.choices = Array.from(choiceInputs).map((input, index) => ({
                Choice_Text: input.value.trim(),
                Is_Correct: index === correctIndex ? 1 : 0
            }));
        });
    }

    function validateQuestion(question, position) {
        if (!question.Question_Text) {
            return { ok: false, message: `Question ${position}: enter the question text` };
        }
        if (!question.choices || question.choices.length !== CHOICE_COUNT) {
            return { ok: false, message: `Question ${position}: all ${CHOICE_COUNT} answer options are required` };
        }
        if (question.choices.some(choice => !choice.Choice_Text)) {
            return { ok: false, message: `Question ${position}: fill in all answer options (A through E)` };
        }
        return { ok: true };
    }

    function renderChoiceRows(question) {
        const editKey = getEditKey(question);
        const choices = question.choices || createEmptyChoices(question.correctIndex || 0);
        return choices.map((choice, index) => {
            const letter = String.fromCharCode(65 + index);
            const checked = index === (question.correctIndex || 0) ? 'checked' : '';
            return `
                <div class="draft-choice-row">
                    <input class="form-check-input" type="radio" name="correct_${editKey}" value="${index}" ${checked}>
                    <span class="draft-choice-label">${letter}</span>
                    <input type="text" class="form-control draft-choice-input" placeholder="Option ${letter}" value="${escapeHtml(choice.Choice_Text)}">
                </div>`;
        }).join('');
    }

    function renderDragHandle(index) {
        return `<div class="drag-handle" draggable="true" data-canvas-index="${index}" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></div>`;
    }

    function renderCanvasDropEnd() {
        return `<div class="exam-canvas-drop-end" data-drop-end="true">Drop here to move to the end</div>`;
    }

    function moveItem(fromIndex, toIndex) {
        if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 ||
            fromIndex >= canvas.length || toIndex >= canvas.length) {
            return;
        }
        collectFromDom();
        const item = canvas.splice(fromIndex, 1)[0];
        canvas.splice(toIndex, 0, item);
        render();
    }

    function moveItemToEnd(fromIndex) {
        if (fromIndex < 0 || fromIndex >= canvas.length - 1) {
            return;
        }
        collectFromDom();
        const item = canvas.splice(fromIndex, 1)[0];
        canvas.push(item);
        render();
    }

    function renderDraftCard(question, index) {
        return `
            <div class="exam-question-card exam-question-card-draft"
                 data-canvas-index="${index}" data-draft-id="${question.draftId}">
                <div class="d-flex gap-2">
                    ${renderDragHandle(index)}
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary">Q${index + 1}</span>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-builder-remove"
                                    data-draft-id="${question.draftId}" title="Discard question">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <label class="form-label small text-muted mb-1">Question text</label>
                        <textarea class="form-control draft-question-text mb-3" rows="3"
                                  placeholder="Enter your question...">${escapeHtml(question.Question_Text)}</textarea>
                        <label class="form-label small text-muted mb-1">Answer options (select the correct one)</label>
                        <div class="draft-choice-list">${renderChoiceRows(question)}</div>
                    </div>
                </div>
            </div>`;
    }

    function updateCount() {
        const countEl = document.getElementById(config.countId);
        const count = canvas.length;
        if (countEl) {
            countEl.textContent = `${count} question${count === 1 ? '' : 's'}`;
        }
        if (typeof config.onCountChange === 'function') {
            config.onCountChange(count);
        }
    }

    function bindCanvasEvents() {
        const container = document.getElementById(config.canvasId);
        if (!container || container.dataset.builderBound) {
            return;
        }

        container.addEventListener('click', function(e) {
            const removeBtn = e.target.closest('.btn-builder-remove');
            if (removeBtn) {
                canvas = canvas.filter(q => q.draftId !== removeBtn.dataset.draftId);
                render();
            }
        });

        container.addEventListener('dragstart', function(e) {
            const handle = e.target.closest('.drag-handle');
            if (!handle) {
                return;
            }

            const card = handle.closest('.exam-question-card');
            if (!card) {
                return;
            }

            dragIndex = parseInt(handle.dataset.canvasIndex, 10);
            if (Number.isNaN(dragIndex)) {
                e.preventDefault();
                return;
            }

            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(dragIndex));
        });

        container.addEventListener('dragend', function(e) {
            const handle = e.target.closest('.drag-handle');
            const card = handle?.closest('.exam-question-card');
            if (card) {
                card.classList.remove('dragging');
            }
            document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                .forEach(el => el.classList.remove('drag-over'));
            dragIndex = null;
            if (window.DragAutoScroll) {
                DragAutoScroll.stop();
            }
        });

        container.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (window.DragAutoScroll) {
                DragAutoScroll.update(e.clientY, container);
            }

            const dropEnd = e.target.closest('.exam-canvas-drop-end');
            if (dropEnd) {
                document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                    .forEach(el => el.classList.remove('drag-over'));
                dropEnd.classList.add('drag-over');
                return;
            }

            const card = e.target.closest('.exam-question-card');
            if (!card) {
                return;
            }
            document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                .forEach(el => el.classList.remove('drag-over'));
            card.classList.add('drag-over');
        });

        container.addEventListener('drop', function(e) {
            e.preventDefault();
            if (window.DragAutoScroll) {
                DragAutoScroll.stop();
            }

            if (dragIndex === null) {
                return;
            }

            const dropEnd = e.target.closest('.exam-canvas-drop-end');
            if (dropEnd) {
                moveItemToEnd(dragIndex);
                document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                    .forEach(el => el.classList.remove('drag-over'));
                return;
            }

            const card = e.target.closest('.exam-question-card');
            if (card) {
                const toIndex = parseInt(card.dataset.canvasIndex, 10);
                moveItem(dragIndex, toIndex);
            }
            document.querySelectorAll('.exam-question-card.drag-over, .exam-canvas-drop-end.drag-over')
                .forEach(el => el.classList.remove('drag-over'));
        });

        container.dataset.builderBound = 'true';
    }

    function render() {
        const container = document.getElementById(config.canvasId);
        if (!container) {
            return;
        }

        if (container.querySelector('.exam-question-card-draft')) {
            collectFromDom();
        }

        if (!canvas.length) {
            container.innerHTML = `
                <div class="exam-question-canvas-empty">
                    <i class="bi bi-ui-checks fs-2 d-block mb-2"></i>
                    Select course, module, and subject, then click <strong>New question</strong>
                </div>`;
            updateCount();
            return;
        }

        container.innerHTML = canvas.map((question, index) => renderDraftCard(question, index)).join('')
            + (canvas.length > 1 ? renderCanvasDropEnd() : '');
        bindCanvasEvents();
        updateCount();
    }

    function clear() {
        canvas = [];
        render();
    }

    function addDraft() {
        const subjectId = config.getSubjectId();
        if (!subjectId) {
            throw new Error(config.subjectRequiredMessage || 'Please select course, module, and subject first');
        }

        canvas.push(createDraft());
        render();

        const container = document.getElementById(config.canvasId);
        if (container) {
            container.scrollTop = container.scrollHeight;
            const lastTextarea = container.querySelector('.exam-question-card-draft:last-of-type .draft-question-text');
            if (lastTextarea) {
                lastTextarea.focus();
            }
        }
    }

    async function saveAll() {
        const subjectId = config.getSubjectId();
        if (!subjectId) {
            throw new Error(config.subjectRequiredMessage || 'Please select course, module, and subject first');
        }
        if (!canvas.length) {
            throw new Error('Add at least one question before saving');
        }

        collectFromDom();

        for (let index = 0; index < canvas.length; index += 1) {
            const question = canvas[index];
            const validation = validateQuestion(question, index + 1);
            if (!validation.ok) {
                throw new Error(validation.message);
            }

            const response = await axios.post(`${config.apiUrl}?action=add_question`, {
                subject_id: subjectId,
                question_text: question.Question_Text,
                question_type: 'Multiple Choice',
                points: 1,
                answers: question.choices.map((choice, choiceIndex) => ({
                    answer_text: choice.Choice_Text,
                    is_correct: choiceIndex === question.correctIndex
                }))
            });

            if (!response.data.success) {
                throw new Error(response.data.message || `Failed to save question ${index + 1}`);
            }
        }

        const savedCount = canvas.length;
        clear();
        return savedCount;
    }

    function init(options) {
        config = Object.assign({}, config, options || {});
        render();

        const addBtn = document.getElementById(config.addBtnId);
        const clearBtn = document.getElementById(config.clearBtnId);

        if (addBtn && !addBtn.dataset.builderInit) {
            addBtn.addEventListener('click', function() {
                try {
                    addDraft();
                } catch (err) {
                    if (typeof config.onError === 'function') {
                        config.onError(err.message);
                    }
                }
            });
            addBtn.dataset.builderInit = 'true';
        }

        if (clearBtn && !clearBtn.dataset.builderInit) {
            clearBtn.addEventListener('click', function() {
                if (!canvas.length) {
                    return;
                }
                clear();
            });
            clearBtn.dataset.builderInit = 'true';
        }
    }

    global.QuestionBuilder = {
        init,
        clear,
        addDraft,
        render,
        saveAll,
        getCount: () => canvas.length,
        hasQuestions: () => canvas.length > 0
    };
})(window);
