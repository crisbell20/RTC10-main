<?php
/**
 * Shared examinee watermark helpers (display only).
 */

function examineeWatermarkText(?string $suffix = null): string
{
    $rank = trim((string)($_SESSION['user_rank'] ?? ''));
    $name = trim((string)($_SESSION['user_name'] ?? 'Examinee'));
    $academic = trim((string)($_SESSION['user_academic_number'] ?? ''));
    $parts = array_filter([$rank, $name, $academic], static fn($p) => $p !== '');
    $text = implode(', ', $parts);
    if ($suffix !== null && trim($suffix) !== '') {
        $text .= ($text !== '' ? ', ' : '') . trim($suffix);
    }
    return $text !== '' ? $text : 'PNP RTC X';
}

function renderExamineeWatermarkLayer(?string $suffix = null, int $tiles = 18): void
{
    $text = htmlspecialchars(examineeWatermarkText($suffix), ENT_QUOTES, 'UTF-8');
    echo '<div class="exam-watermark-layer" aria-hidden="true">';
    for ($i = 0; $i < $tiles; $i++) {
        echo '<span class="exam-watermark-tile">' . $text . '</span>';
    }
    echo '</div>';
}
