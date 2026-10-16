<?php
// Shared file preview popup. Pair with assets/js/file-popup.js.
// PDFs render through PDF.js, so make sure it is loaded.
include_once __DIR__ . '/pdfjs.php';
?>
<div class="modal-backdrop" id="filePopupBackdrop">
  <div class="modal-card file-popup-card">
    <div class="file-popup-header">
      <span class="file-popup-title" id="filePopupTitle"></span>
      <button class="modal-close" onclick="closeFilePopup()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="file-popup-frame" id="filePopupFrame">
      <div id="filePopupPdfPages" class="file-popup-pdf-pages" hidden></div>
      <img id="filePopupImg" alt="">
      <p id="filePopupMessage" class="helper file-popup-message">Loading&hellip;</p>
    </div>
    <div class="file-popup-footer" id="filePopupFooter" hidden>
      <p class="helper file-popup-footer-note" id="filePopupFooterNote"></p>
      <button class="confirm-modal-btn confirm-modal-ok" type="button" id="filePopupFooterBtn">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#upload-icon" />
        </svg>
        <span id="filePopupFooterLabel"></span>
      </button>
    </div>
  </div>
</div>