<?php

/** @var array  $protocol */
/** @var array  $sections */
/** @var bool   $includeAll */

$title         = htmlspecialchars($protocol['research_title'] ?? 'Protocol', ENT_QUOTES, 'UTF-8');
$totalComments = array_sum(array_map(fn($section) => count($section['annotations']), $sections));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reviewer comments: <?= $title ?></title>

  <link rel="stylesheet" href="<?= asset_css('body.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('viewer.css') ?>">
  <link rel="stylesheet" href="<?= asset_css('comments-report.css') ?>">

  <?php include __DIR__ . '/includes/icons.php'; ?>
  <?php include_once __DIR__ . '/includes/pdfjs.php'; ?>
</head>

<body>
  <?php include __DIR__ . '/includes/sprites.php'; ?>

  <main class="report" id="main-content">

    <!-- ===== Report toolbar ===== -->
    <div class="report-toolbar">
      <button type="button" class="tool-btn tool-btn--success" id="reportDownload" disabled>
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#download-icon" />
        </svg>
        Download PDF
      </button>
    </div>

    <div class="report-paper">

    <!-- ===== Report header ===== -->
    <header class="report-header">
      <h1>Reviewer comments</h1>
      <p class="report-title"><?= $title ?></p>
      <p class="report-meta">
        <?= $totalComments ?> <?= $totalComments === 1 ? 'comment' : 'comments' ?>
        &middot; <?= $includeAll ? 'All versions up to this one' : htmlspecialchars($sections[0]['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
        &middot; Generated <?= date(DATETIME_FORMAT) ?>
      </p>
    </header>

    <!-- ===== Version sections ===== -->
    <?php foreach ($sections as $section): ?>
      <section class="report-section" data-file-url="<?= htmlspecialchars($section['fileUrl'], ENT_QUOTES, 'UTF-8') ?>">
        <h2>
          <?= htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8') ?>
          <span class="report-section-date">Uploaded <?= date(DATETIME_FORMAT, strtotime($section['uploadedAt'])) ?></span>
        </h2>

        <?php if (!$section['annotations']): ?>
          <p class="annot-empty">No comments on this version.</p>
        <?php endif; ?>

        <?php foreach ($section['annotations'] as $index => $annotation): ?>
          <article class="report-comment"
            data-page="<?= (int) $annotation['page_number'] ?>"
            data-x="<?= (float) $annotation['x'] ?>"
            data-y="<?= (float) $annotation['y'] ?>"
            data-width="<?= (float) $annotation['width'] ?>"
            data-height="<?= (float) $annotation['height'] ?>">
            <div class="annot-item-header">
              <span class="annot-num"><?= $index + 1 ?></span>
              <span class="annot-page">Page <?= (int) $annotation['page_number'] ?></span>
            </div>
            <figure class="report-excerpt">
              <canvas role="img" aria-label="Excerpt of page <?= (int) $annotation['page_number'] ?> that comment <?= $index + 1 ?> refers to"></canvas>
            </figure>
            <p class="annot-comment"><?= htmlspecialchars($annotation['comment'], ENT_QUOTES, 'UTF-8') ?></p>
            <p class="annot-date">
              <?= !empty($annotation['author']) ? htmlspecialchars($annotation['author'], ENT_QUOTES, 'UTF-8') . ' &middot; ' : '' ?><?= date(DATETIME_FORMAT, strtotime($annotation['created_at'])) ?>
            </p>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    </div>
  </main>

  <script src="<?= asset_js('vendor/jspdf/jspdf.umd.min.js') ?>"></script>
  <script src="<?= asset_js('comments-report.js') ?>"></script>
</body>

</html>
