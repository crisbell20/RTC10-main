<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Examinee') {
    header('Location: ../../login.php');
    exit;
}

$watermarkName = $_SESSION['user_name'] ?? 'Trainee';
$watermarkRank = trim((string)($_SESSION['user_rank'] ?? ''));
$watermarkBadge = trim((string)($_SESSION['user_badge_number'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Exam - RTC Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/custom.css">
    <link rel="stylesheet" href="../../assets/css/exam.css?v=13">
    <link rel="stylesheet" href="../../assets/css/question-display.css?v=2">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=17">
    <link rel="stylesheet" href="../../assets/css/examinee-watermark.css?v=15">
</head>
<body class="examinee-exam">
    <div id="offlineBanner" class="exam-connection-banner" hidden role="status" aria-live="polite">
        <span id="offlineBannerText">You are currently offline</span>
    </div>
    <div id="loadingOverlay" class="loading-overlay">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3 text-muted">Loading exam...</p>
        </div>
    </div>

    <div class="exam-container" id="examContainer" style="display: none;">
        <div class="exam-proctor-notice">
            <i class="bi bi-shield-exclamation me-1"></i>
            <strong>Proctored exam:</strong> Stay in fullscreen. Tab switches and copy/paste are logged. Content is watermarked.
        </div>
        <div class="exam-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 id="examTitle">Exam Title</h2>
                    <p class="mb-0" id="examInfo">Subject • Course</p>
                </div>
                <div class="text-end exam-header-aside">
                    <div class="timer" id="timer">00:00:00</div>
                    <small>Time Remaining</small>
                    <div class="mt-2" id="deadlineInfo">
                        <i class="bi bi-calendar-event"></i> <span id="deadlineText"></span>
                    </div>
                </div>
            </div>
            <div class="progress progress-bar-custom mt-3">
                <div class="exam-progress-segments" id="progressBarSegments" role="progressbar" aria-valuemin="0" aria-valuemax="0" aria-valuenow="0"></div>
            </div>
            <small class="text-white-50 mt-2 d-block">
                <span id="answeredCount">0</span> of <span id="totalQuestions">0</span> questions answered
            </small>
        </div>

        <div id="questionsArea" class="watermark-shield">
            <div id="examWatermarkLayer" class="exam-watermark-layer" aria-hidden="true"></div>
            <div id="questionsContainer"></div>
        </div>

        <div class="nav-buttons">
            <div class="d-flex justify-content-between align-items-center">
                <button class="btn btn-outline-secondary" id="prevBtn" onclick="previousQuestion()">
                    <i class="bi bi-arrow-left"></i> Previous
                </button>
                <span class="text-muted">
                    Question <span id="currentQuestionNum">1</span> of <span id="totalQuestionsNav">0</span>
                </span>
                <button class="btn btn-primary" id="nextBtn" onclick="nextQuestion()">
                    Next <i class="bi bi-arrow-right"></i>
                </button>
                <button class="btn btn-success" id="submitBtn" onclick="submitExam()" style="display: none;">
                    <i class="bi bi-check-circle"></i> Submit Exam
                </button>
            </div>
        </div>
    </div>

    <script>
        window.EXAM_WATERMARK = <?= json_encode([
            'rank' => $watermarkRank,
            'name' => $watermarkName,
            'badge_number' => $watermarkBadge,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../../assets/js/parse-datetime.js?v=1"></script>
    <script src="../../assets/js/examinee-watermark.js?v=7"></script>
    <script src="../../js/examinee/exam.js?v=55"></script>
</body>
</html>
