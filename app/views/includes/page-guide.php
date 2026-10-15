<?php
// ===== Page guide button (beside the page title) =====
$guideDiscId    = $pageGuideId ?? '';
$guideDiscLabel = 'Page guide';
include __DIR__ . '/guide-disc.php';
unset($pageGuideId);
