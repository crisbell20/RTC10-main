<?php
/**
 * Mobile bottom tab navigation for examinee portal.
 *
 * Set before include (same as examinee-sidebar.php):
 *   $examineeNavFrom   = 'examinee' | 'masterfiles'
 *   $examineeNavActive = 'exams' | 'results' | 'performance' | 'profile'
 */
$examineeNavFrom = $examineeNavFrom ?? 'examinee';
$examineeNavActive = $examineeNavActive ?? '';

if ($examineeNavFrom === 'masterfiles') {
    $examsUrl = '../examinee/dashboard.php';
    $resultsUrl = 'result.php';
    $performanceUrl = 'perfomance.php';
    $profileUrl = 'profile.php';
} else {
    $examsUrl = 'dashboard.php';
    $resultsUrl = '../student masterfiles/result.php';
    $performanceUrl = '../student masterfiles/perfomance.php';
    $profileUrl = '../student masterfiles/profile.php';
}

function examineeBottomNavClass(string $item, string $active): string
{
    return $item === $active ? ' examinee-bottom-nav-item active' : ' examinee-bottom-nav-item';
}
?>
<nav class="examinee-bottom-nav" aria-label="Main navigation">
    <a href="<?= htmlspecialchars($examsUrl) ?>" class="<?= trim(examineeBottomNavClass('exams', $examineeNavActive)) ?>">
        <i class="bi bi-pencil-square" aria-hidden="true"></i>
        <span>Exams</span>
    </a>
    <a href="<?= htmlspecialchars($resultsUrl) ?>" class="<?= trim(examineeBottomNavClass('results', $examineeNavActive)) ?>">
        <i class="bi bi-clipboard-check" aria-hidden="true"></i>
        <span>Results</span>
    </a>
    <a href="<?= htmlspecialchars($performanceUrl) ?>" class="<?= trim(examineeBottomNavClass('performance', $examineeNavActive)) ?>">
        <i class="bi bi-graph-up" aria-hidden="true"></i>
        <span>Performance</span>
    </a>
    <a href="<?= htmlspecialchars($profileUrl) ?>" class="<?= trim(examineeBottomNavClass('profile', $examineeNavActive)) ?>">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span>Profile</span>
    </a>
</nav>
