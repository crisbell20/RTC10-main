<?php
/**
 * Shared examinee sidebar navigation.
 *
 * Set before include:
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

function examineeNavClass(string $item, string $active): string
{
    return $item === $active ? ' class="active"' : '';
}
?>
<ul class="sidebar-menu">
    <li><a href="<?= htmlspecialchars($examsUrl) ?>"<?= examineeNavClass('exams', $examineeNavActive) ?>><i class="bi bi-pencil-square"></i>Exams</a></li>
    <li><a href="<?= htmlspecialchars($resultsUrl) ?>"<?= examineeNavClass('results', $examineeNavActive) ?>><i class="bi bi-clipboard-check"></i>Results</a></li>
    <li><a href="<?= htmlspecialchars($performanceUrl) ?>"<?= examineeNavClass('performance', $examineeNavActive) ?>><i class="bi bi-graph-up"></i>Performance</a></li>
    <li><a href="<?= htmlspecialchars($profileUrl) ?>"<?= examineeNavClass('profile', $examineeNavActive) ?>><i class="bi bi-person-circle"></i>Profile</a></li>
</ul>
