<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Examinee') {
    header('Location: ../../login.php');
    exit;
}

$userName = $_SESSION['user_name'] ?? 'User';
$resultId = $_GET['result_id'] ?? null;

require __DIR__ . '/../includes/examinee-watermark.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/../includes/sidebar-state-early.php'; ?>
    <title><?= $resultId ? 'Exam Result Details' : 'My Results' ?> - PNP RTC X</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/question-display.css?v=2">
    <link rel="stylesheet" href="../../assets/css/exam-results.css?v=5">
    <link rel="stylesheet" href="../../assets/css/examinee-responsive.css?v=13">
    <link rel="stylesheet" href="../../assets/css/examinee-watermark.css?v=15">
</head>
<body class="examinee-portal">
    <div class="top-nav">
        <?php
        $navBrandSubtitle = $resultId ? 'Exam Result Details' : 'My Results';
        require __DIR__ . '/../includes/nav-brand.php';
        ?>
        <div class="nav-user">
            <span><?= htmlspecialchars($userName) ?></span>
            <button class="btn-logout" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>

    <!-- Main Container -->
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <?php
            $examineeNavFrom = 'examinee';
            $examineeNavActive = 'results';
            require __DIR__ . '/../includes/examinee-sidebar.php';
            ?>
        </div>

        <div class="main-content">
            <div id="loadingMessage" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Loading...</p>
            </div>

            <?php if (!$resultId): ?>
            <!-- Results List View -->
            <div id="resultsListContent" style="display: none;">
                <div class="page-header mb-4">
                    <h2>My Exam Results</h2>
                    <p class="text-muted">View your completed exam results</p>
                </div>
                <div id="resultsList"></div>
            </div>
            <?php else: ?>
            <!-- Single Result Detail View -->
            <div id="resultContent" style="display: none;"></div>
            <div id="questionsContent" style="display: none;">
                <div class="section-card">
                    <div class="section-header">
                        <i class="bi bi-list-check" style="color: var(--secondary-color); font-size: 1.3rem;"></i>
                        <div>
                            <h5>Question Review</h5>
                        </div>
                    </div>
                    <div class="section-body watermark-shield" id="questionsShield">
                        <div id="questionsList" class="watermark-shield-content"></div>
                        <?php renderExamineeWatermarkLayer(); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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
    <script src="../../assets/js/examinee-responsive-nav.js?v=7"></script>
    <script src="../../assets/js/auto-logout.js?v=2"></script>
    <script src="../../assets/js/parse-datetime.js?v=1"></script>
    <script>
        const resultId = <?= json_encode($resultId) ?>;
        window.EXAM_WATERMARK = <?= json_encode([
            'rank' => trim((string)($_SESSION['user_rank'] ?? '')),
            'name' => $_SESSION['user_name'] ?? 'Trainee',
            'badge_number' => trim((string)($_SESSION['user_badge_number'] ?? '')),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="../../assets/js/examinee-watermark.js?v=7"></script>
    <script src="../../js/examinee/results.js?v=6"></script>
</body>
</html>
