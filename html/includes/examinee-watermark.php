<?php
// Examinee watermark
function examineeWatermarkText(?string $suffix = null): string
{
    $rank = trim((string)($_SESSION['user_rank'] ?? ''));
    $name = trim((string)($_SESSION['user_name'] ?? 'Trainee'));
    $badge = trim((string)($_SESSION['user_badge_number'] ?? ''));
    $parts = array_filter([$rank, $name, $badge], static fn($p) => $p !== '');
    $text = implode(', ', $parts);
    if ($suffix !== null && trim($suffix) !== '') {
        $text .= ($text !== '' ? ', ' : '') . trim($suffix);
    }
    return $text !== '' ? $text : 'PNP RTC X';
}

function renderExamineeWatermarkLayer(?string $suffix = null, int $tiles = 84): void
{
    $text = htmlspecialchars(examineeWatermarkText($suffix), ENT_QUOTES, 'UTF-8');
    echo '<div class="exam-watermark-layer" data-watermark-text="' . $text . '" aria-hidden="true"></div>';
}
