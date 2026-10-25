<?php

/**
 * Resubmit a rejected training certificate. A revised protocol file can go with it.
 *
 * @var int|null $certResubmitProtocolId protocol that can take a revised file (under review), or null
 */
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
      data-confirm-message="Submit these files for verification?" data-confirm-ok-text="Submit">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      <?php if (!empty($certResubmitProtocolId)): ?>
        <input type="hidden" name="protocol_id" value="<?= (int) $certResubmitProtocolId ?>">
      <?php endif; ?>

      <p class="panel-modal-intro">Upload a new training certificate. Your protocol stays in the queue and the review continues once it is verified.</p>

      <label class="return-comment-label" for="certResubmitFile">Training certificate <span class="required-asterisk">*</span></label>
      <input type="file" id="certResubmitFile" name="cert_file" accept=".pdf,.jpg,.jpeg,.png" required
        onchange="syncPreviewButton(this, 'certResubmitPreviewBtn')">
      <button type="button" class="resub-btn-link is-hidden" id="certResubmitPreviewBtn"
        onclick="previewPickedFile('certResubmitFile')">Preview</button>
      <p class="helper">PDF, JPG or PNG, max 10 MB.</p>

      <?php if (!empty($certResubmitProtocolId)): ?>
        <label class="return-comment-label" for="certResubmitProtocolFile">Revised protocol <span class="return-comment-optional">(optional)</span></label>
        <input type="file" id="certResubmitProtocolFile" name="protocol_file" accept=".pdf,.doc,.docx"
          onchange="syncPreviewButton(this, 'certResubmitProtocolPreviewBtn')">
        <button type="button" class="resub-btn-link is-hidden" id="certResubmitProtocolPreviewBtn"
          onclick="previewPickedFile('certResubmitProtocolFile')">Preview</button>
        <p class="helper">PDF, DOC or DOCX, max 10 MB. Comments already made on the earlier file stay with that version.</p>
      <?php endif; ?>

      <div class="panel-modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeCertResubmitModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="submit">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          Submit
        </button>
      </div>
    </form>
  </div>
</div>