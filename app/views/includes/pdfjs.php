<?php
// Shared PDF.js loader (self-hosted, so PDF viewing works offline).
// Include once per page, before any script that uses pdfjsLib.
?>
<script src="<?= asset_js('vendor/pdfjs/pdf.min.js') ?>"></script>
<script>
  pdfjsLib.GlobalWorkerOptions.workerSrc = <?= json_encode(asset_js('vendor/pdfjs/pdf.worker.min.js')) ?>;
</script>