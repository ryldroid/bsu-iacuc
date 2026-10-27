<?php

/** Resubmit a rejected training certificate. */
?>
<!-- ===== Resubmit training certificate modal ===== -->
<div class="modal-backdrop" id="certResubmitBackdrop" data-static>
  <div class="modal-card panel-modal-card" role="dialog" aria-modal="true" aria-labelledby="certResubmitTitle">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#upload-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Training Certificate</p>
        <p class="modal-title" id="certResubmitTitle">Resubmit Certificate</p>
      </div>
      <button class="modal-close" type="button" onclick="closeCertResubmitModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <form class="panel-modal-body" method="POST" action="<?= ROOT ?>/apply/reuploadcert" enctype="multipart/form-data"
      data-confirm-message="Submit this certificate for verification?" data-confirm-ok-text="Submit">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <p class="panel-modal-intro">Upload a new training certificate. Your protocol stays in the queue and the review continues once it is verified.</p>

      <div class="modal-file-row">
        <div class="modal-file-info">
          <div class="modal-file-title">Training certificate <span class="required-asterisk">*</span></div>
          <div class="modal-file-subtitle" id="certResubmitFileSubtitle">PDF, JPG or PNG &middot; max 10 MB</div>
        </div>
        <div class="resub-doc-actions">
          <button type="button" class="resub-btn-link is-hidden" id="certResubmitPreviewBtn"
            onclick="previewPickedFile('certResubmitFile')">Preview</button>
          <label class="modal-file-picker">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#upload-icon" />
            </svg>
            <span id="certResubmitPickerLabel">Upload</span>
            <input type="file" id="certResubmitFile" name="cert_file" accept=".pdf,.jpg,.jpeg,.png"
              onchange="handleCertResubmitFileChange(this)">
          </label>
        </div>
      </div>

      <div class="panel-modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeCertResubmitModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="submit" id="certResubmitSubmitBtn" disabled>
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          Submit
        </button>
      </div>
    </form>
  </div>
</div>