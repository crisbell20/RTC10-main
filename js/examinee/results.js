        if (resultId) {
            loadResultDetails();
        } else {
            loadResultsList();
        }

        function loadResultsList() {
            axios.get('../../api/examinee/dashboard.php?action=all-results')
                .then(response => {
                    if (response.data.success) {
                        displayResultsList(response.data.data || []);
                    } else {
                        showError(response.data.message || 'Failed to load results');
                    }
                })
                .catch(error => {
                    console.error('Error loading results:', error);
                    showError('Failed to load results. Please try again.');
                });
        }

        function displayResultsList(results) {
            document.getElementById('loadingMessage').style.display = 'none';
            document.getElementById('resultsListContent').style.display = 'block';
            
            if (!results || results.length === 0) {
                document.getElementById('resultsList').innerHTML = '<p class="text-center text-muted py-5">No exam results yet</p>';
                return;
            }

            const sortedResults = [...results].sort((a, b) => {
                const dateA = examDateTimeMs(a.Time_Ended || a.Submission_Date);
                const dateB = examDateTimeMs(b.Time_Ended || b.Submission_Date);
                if (dateB !== dateA) return dateB - dateA;
                return (parseInt(b.Result_ID, 10) || 0) - (parseInt(a.Result_ID, 10) || 0);
            });

            const resultsHTML = sortedResults.map(result => {
                const inProgress = !!result.is_in_progress;
                const isPassed = !inProgress && result.Remarks === 'Passed';
                const percentage = parseFloat(result.Percentage) || 0;
                const reviewDisabled = result.can_view_details === false;
                const attemptLabel = inProgress
                    ? '<div class="small text-muted mb-1">In progress</div>'
                    : (result.attempt_number ? `<div class="small text-muted mb-1">Attempt ${result.attempt_number}</div>` : '');
                
                return `
                    <div class="result-card examinee-data-card examinee-clickable-card" onclick="window.location.href='result.php?result_id=${result.Result_ID}'">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <h5 class="mb-2">${escapeHtml(result.exam_title)}</h5>
                                ${attemptLabel}
                                <p class="text-muted mb-2">${result.Subject_Name ? escapeHtml(result.Subject_Name) : 'N/A'}</p>
                                <div class="d-flex gap-3 mb-2 result-card-meta">
                                    <span><i class="bi bi-calendar3"></i> ${formatDate(result.Submission_Date)}</span>
                                    <span><i class="bi bi-clock"></i> ${result.time_taken || 'N/A'}</span>
                                </div>
                                ${reviewDisabled ? '<small class="text-muted"><i class="bi bi-lock"></i> Score summary only</small>' : ''}
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge ${inProgress ? 'bg-secondary' : (isPassed ? 'bg-success' : 'bg-danger')} mb-2">
                                    ${inProgress ? 'In progress' : result.Remarks}
                                </span>
                                ${inProgress ? '' : `<h3 class="mb-0">${percentage.toFixed(1)}%</h3><small class="text-muted">${result.Score}/${result.total_questions}</small>`}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            document.getElementById('resultsList').innerHTML = resultsHTML;
        }

        function loadResultDetails() {
            axios.get(`../../api/examinee/result-details.php?result_id=${resultId}`)
                .then(response => {
                    if (response.data.success) {
                        displayResultDetails(response.data.data);
                    } else {
                        showError(response.data.message || 'Failed to load result details');
                    }
                })
                .catch(error => {
                    console.error('Error loading result details:', error);
                    showError('Failed to load result details. Please try again.');
                });
        }

        function displayResultDetails(data) {
            document.getElementById('loadingMessage').style.display = 'none';
            
            data.Percentage = parseFloat(data.Percentage) || 0;
            data.Score = parseInt(data.Score) || 0;
            data.total_questions = parseInt(data.total_questions) || 0;
            data.Passing_Score = parseFloat(data.Passing_Score) || 0;
            
            const inProgress = data.is_in_progress === true || data.is_in_progress === 1 || data.Remarks === 'In progress';
            const isPassed = !inProgress && data.Remarks === 'Passed';
            const resultClass = inProgress ? '' : (isPassed ? 'passed' : 'failed');
            const attemptLabel = inProgress
                ? '<p class="mb-0 opacity-75">In progress</p>'
                : (data.attempt_number ? `<p class="mb-0 opacity-75">Attempt ${data.attempt_number}</p>` : '');
            
            const resultHTML = `
                <div class="result-summary ${resultClass}">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h2 class="mb-2">${escapeHtml(data.exam_title)}</h2>
                            ${attemptLabel}
                            <p class="mb-0 opacity-75">${data.Course_Name ? escapeHtml(data.Course_Name) + ' - ' : ''}${data.Subject_Name ? escapeHtml(data.Subject_Name) : ''}</p>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="stat-card score-stat ${inProgress ? '' : (isPassed ? 'passed' : 'failed')}">
                                <h3>${inProgress ? '—' : `${data.Score}/${data.total_questions}`}</h3>
                                <p>${inProgress ? 'In progress' : 'Score'}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card percentage-stat ${inProgress ? '' : (isPassed ? 'passed' : 'failed')}">
                                <h3>${inProgress ? '—' : `${data.Percentage.toFixed(1)}%`}</h3>
                                <p>${inProgress ? 'In progress' : 'Average'}</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h3>${data.time_taken}</h3>
                                <p>Time Taken</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top border-white border-opacity-25">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="opacity-75">Submitted on:</small>
                                <p class="mb-0">${formatDateTime(data.Submission_Date)}</p>
                            </div>
                            <div class="col-md-6">
                                <small class="opacity-75">Duration:</small>
                                <p class="mb-0">${data.Duration_Minutes} minutes</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('resultContent').innerHTML = resultHTML;
            document.getElementById('resultContent').style.display = 'block';

            if (data.review_allowed === false) {
                const notice = document.createElement('div');
                notice.className = 'alert alert-info mt-3';
                notice.innerHTML = `<i class="bi bi-info-circle me-2"></i>${escapeHtml(data.review_message || 'Response viewing for this exam has been disabled. You can see your score summary only.')}`;
                document.getElementById('resultContent').appendChild(notice);
                return;
            }
            
            displayQuestions(data.questions);
        }

        function displayQuestions(questions) {
            if (!questions || questions.length === 0) {
                document.getElementById('questionsList').innerHTML = '<p class="text-muted text-center py-4">No questions found</p>';
                document.getElementById('questionsContent').style.display = 'block';
                if (typeof paintExamineeWatermark === 'function') {
                    paintExamineeWatermark(document.getElementById('questionsShield'));
                }
                return;
            }
            
            const questionsHTML = questions.map((q, index) => {
                const isCorrect = q.user_is_correct == 1;
                const isAnswered = q.user_answer_id != null;
                const cardClass = isAnswered ? (isCorrect ? 'correct' : 'incorrect') : '';
                
                const choicesHTML = q.choices.map(choice => {
                    const isUserAnswer = choice.Choice_ID == q.user_answer_id;
                    const isCorrectAnswer = choice.Is_Correct == 1;
                    
                    let choiceClass = 'choice-item';
                    let icon = '';
                    
                    if (isUserAnswer && isCorrectAnswer) {
                        choiceClass += ' user-answer correct-answer';
                        icon = '<i class="bi bi-check-circle-fill text-success me-2"></i>';
                    } else if (isUserAnswer && !isCorrectAnswer) {
                        choiceClass += ' user-answer incorrect';
                        icon = '<i class="bi bi-x-circle-fill text-danger me-2"></i>';
                    } else if (isCorrectAnswer) {
                        choiceClass += ' correct-answer';
                        icon = '<i class="bi bi-check-circle-fill text-success me-2"></i>';
                    }
                    
                    return `
                        <div class="${choiceClass}">
                            ${icon}${escapeHtml(choice.Choice_Text)}
                            ${isCorrectAnswer ? '<span class="badge bg-success ms-2">Correct Answer</span>' : ''}
                            ${isUserAnswer && !isCorrectAnswer ? '<span class="badge bg-danger ms-2">Your Answer</span>' : ''}
                            ${isUserAnswer && isCorrectAnswer ? '<span class="badge bg-success ms-2">Your Answer</span>' : ''}
                        </div>
                    `;
                }).join('');
                
                return `
                    <div class="question-card ${cardClass}">
                        <div class="d-flex align-items-start mb-3">
                            <span class="question-number">${index + 1}</span>
                            <div class="flex-grow-1">
                                <p class="mb-0 fw-medium question-stem">${escapeHtml(q.Question_Text)}</p>
                            </div>
                            ${isAnswered ? 
                                (isCorrect ? 
                                    '<span class="badge bg-success"><i class="bi bi-check-lg"></i> Correct</span>' : 
                                    '<span class="badge bg-danger"><i class="bi bi-x-lg"></i> Incorrect</span>'
                                ) : 
                                '<span class="badge bg-secondary">Not Answered</span>'
                            }
                        </div>
                        <div class="mt-3">
                            ${choicesHTML}
                        </div>
                    </div>
                `;
            }).join('');
            
            document.getElementById('questionsList').innerHTML = questionsHTML;
            document.getElementById('questionsContent').style.display = 'block';
            if (typeof paintExamineeWatermark === 'function') {
                paintExamineeWatermark(document.getElementById('questionsShield'));
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDate(dateStr) {
            const date = parseExamDateTime(dateStr);
            if (!date) return 'N/A';
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function formatDateTime(dateStr) {
            const date = parseExamDateTime(dateStr);
            if (!date) return 'N/A';
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showError(message) {
            document.getElementById('loadingMessage').style.display = 'none';
            Swal.fire({
                title: 'Error',
                text: message,
                icon: 'error',
                confirmButtonText: 'Go Back'
            }).then(() => {
                window.location.href = 'dashboard.php';
            });
        }
    