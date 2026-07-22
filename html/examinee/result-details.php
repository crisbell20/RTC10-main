<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$resultId = $_GET['result_id'] ?? null;

require __DIR__ . '/../includes/examinee-watermark.php';

if (!$resultId) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Exam Result Details - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=4">
    <link rel="stylesheet" href="../../assets/css/examinee-watermark.css?v=4">
    <style>
        .result-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        .result-summary.passed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .result-summary.failed {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: bold;
        }
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        .question-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: white;
        }
        .question-number {
            display: inline-block;
            width: 32px;
            height: 32px;
            line-height: 32px;
            text-align: center;
            border-radius: 50%;
            background: #e5e7eb;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        .question-card.correct .question-number {
            background: #d1fae5;
            color: #065f46;
        }
        .question-card.incorrect .question-number {
            background: #fee2e2;
            color: #991b1b;
        }
        .choice-item {
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        .choice-item.user-answer {
            border-color: #3b82f6;
            background: #dbeafe;
        }
        .choice-item.correct-answer {
            border-color: #10b981;
            background: #d1fae5;
        }
        .choice-item.user-answer.incorrect {
            border-color: #ef4444;
            background: #fee2e2;
        }
        .badge-result {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="examinee-portal">
    <div class="top-nav">
        <div class="nav-brand">
            <h5>PNP Regional Training Center X</h5>
            <p>Exam Result Details</p>
        </div>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="sidebar">
            <?php
            $examineeNavFrom = 'examinee';
            $examineeNavActive = 'results';
            require __DIR__ . '/../includes/examinee-sidebar.php';
            ?>
        </div>

        <div class="main-content">
            <div class="mb-3">
                <a href="dashboard.php" class="btn btn-outline">
                    <i class="bi bi-arrow-left me-2"></i>Back to Exams
                </a>
            </div>

            <div id="loadingMessage" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading result details...</p>
            </div>

            <div id="resultContent" style="display: none;">
                <!-- Result summary will be inserted here -->
            </div>

            <div id="questionsContent" style="display: none;">
                <div class="section-card">
                    <div class="section-header">
                        <i class="bi bi-list-check" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                        <div>
                            <h5>Question Review</h5>
                            <p>Review your answers and see the correct solutions</p>
                        </div>
                    </div>
                    <div class="section-body watermark-shield" id="questionsShield">
                        <div id="questionsList" class="watermark-shield-content"></div>
                        <?php renderExamineeWatermarkLayer(null, 54); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    $examineeNavFrom = 'examinee';
    $examineeNavActive = 'results';
    require __DIR__ . '/../includes/examinee-bottom-nav.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <script src="../../assets/js/logout-handler.js?v=1"></script>
    <script src="../../assets/js/examinee-responsive-nav.js?v=1"></script>
    <script src="../../assets/js/auto-logout.js"></script>
    <script>
        const resultId = <?= json_encode($resultId) ?>;

        // Load result details
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
            
            // Convert numeric strings to numbers
            data.Percentage = parseFloat(data.Percentage) || 0;
            data.Score = parseInt(data.Score) || 0;
            data.total_questions = parseInt(data.total_questions) || 0;
            data.Passing_Score = parseFloat(data.Passing_Score) || 0;
            
            // Display result summary
            const isPassed = data.Remarks === 'Passed';
            const resultClass = isPassed ? 'passed' : 'failed';
            
            const resultHTML = `
                <div class="result-summary ${resultClass}">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <div>
                            <h2 class="mb-2">${escapeHtml(data.exam_title)}</h2>
                            <p class="mb-0 opacity-75">${data.Course_Name ? escapeHtml(data.Course_Name) + ' - ' : ''}${data.Subject_Name ? escapeHtml(data.Subject_Name) : ''}</p>
                        </div>
                        <span class="badge ${isPassed ? 'bg-success' : 'bg-danger'} badge-result">
                            <i class="bi ${isPassed ? 'bi-check-circle' : 'bi-x-circle'} me-1"></i>
                            ${data.Remarks}
                        </span>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3>${data.Score}/${data.total_questions}</h3>
                                <p>Score</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3>${data.Percentage.toFixed(1)}%</h3>
                                <p>Percentage</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h3>${data.Passing_Score}%</h3>
                                <p>Passing Score</p>
                            </div>
                        </div>
                        <div class="col-md-3">
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
            
            // Display questions
            displayQuestions(data.questions);
        }

        function displayQuestions(questions) {
            if (!questions || questions.length === 0) {
                document.getElementById('questionsList').innerHTML = '<p class="text-muted text-center py-4">No questions found</p>';
                document.getElementById('questionsContent').style.display = 'block';
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
                                <p class="mb-0 fw-medium">${escapeHtml(q.Question_Text)}</p>
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
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatDateTime(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
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

        // Load result details on page load
        loadResultDetails();
    </script>
</body>
</html>
