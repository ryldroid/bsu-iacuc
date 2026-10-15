<?php
// ===== Tab guide button (inside the active tab) =====
$guideDiscId    = $tabGuideId ?? '';
$guideDiscLabel = 'Page guide for ' . ($tabGuideLabel ?? 'this tab');
$guideDiscClass = 'tab-guide';
include __DIR__ . '/guide-disc.php';
unset($tabGuideId, $tabGuideLabel);
