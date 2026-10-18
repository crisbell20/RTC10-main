<?php
$navBrandTitle = $navBrandTitle ?? 'PNP Regional Training Center X';
$navBrandSubtitle = $navBrandSubtitle ?? '';
$navToggleClass = (($_SESSION['user_role'] ?? '') === 'Examinee')
    ? 'examinee-nav-toggle'
    : 'sidebar-toggle-btn';
?>
<button type="button" class="<?= $navToggleClass ?>" aria-label="Toggle navigation menu" aria-expanded="true">
    <i class="bi bi-list"></i>
</button>
<div class="nav-brand">
    <img class="nav-brand-logo" src="../../assets/images/pnp-badge.png" alt="PNP RTC X" width="38" height="38">
    <div class="nav-brand-text">
        <h5><?= htmlspecialchars((string) $navBrandTitle, ENT_QUOTES, 'UTF-8') ?></h5>
        <?php if ($navBrandSubtitle !== ''): ?>
        <p><?= htmlspecialchars((string) $navBrandSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
</div>
