<?php
// Examinee bottom navigation

$examineeNavActive = $examineeNavActive ?? '';

$examsUrl = 'dashboard.php';
$resultsUrl = 'result.php';
$performanceUrl = 'performance.php';
$profileUrl = 'profile.php';

function examineeBottomNavClass(string $item, string $active): string
{
    return $item === $active ? ' examinee-bottom-nav-item active' : ' examinee-bottom-nav-item';
}
?>
<nav class="examinee-bottom-nav" aria-label="Main navigation">
    <a href="<?= htmlspecialchars($profileUrl) ?>" class="<?= trim(examineeBottomNavClass('profile', $examineeNavActive)) ?>">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span>Profile</span>
    </a>
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
</nav>
