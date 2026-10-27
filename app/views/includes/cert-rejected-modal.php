<?php

/**
 * Rejected training certificate: the certificate, the reviewer's reason and a way to resubmit.
 *
 * @var array $cert
 * @var array $actor
 */

$certFileUrl = ROOT . '/apply/cert/' . (int) $actor['id'];
$certIsImage = (bool) preg_match('/\.(jpe?g|png)$/i', $cert['cert_path']);
$certReason  = trim($cert['cert_note'] ?? '');
?>
<!-- ===== Rejected training certificate modal ===== -->
<div class="modal-backdrop" id="certRejectedBackdrop">
  <div class="modal-card history-modal-card researcher-modal-card" role="dialog" aria-modal="true" aria-labelledby="certRejectedTitle">
    <div class="modal-header">
      <span class="modal-header-icon modal-header-icon--danger" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#alert-triangle-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Training certificate</p>
        <p class="modal-title" id="certRejectedTitle">Certificate rejected</p>
      </div>
      <button class="modal-close" type="button" onclick="closeCertRejectedModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="history-modal-body researcher-modal-body">
      <p class="helper">Your protocol review is on hold until you resubmit a new certificate. The review continues once it is verified.</p>
      <div class="researcher-detail-rows">
        <?php if (!empty($cert['cert_uploaded_at'])): ?>
          <div class="researcher-detail-row">
            <span class="researcher-detail-label">Submitted</span>
            <span class="researcher-detail-value"><?= htmlspecialchars(date(DATE_FORMAT, strtotime($cert['cert_uploaded_at'])), ENT_QUOTES, 'UTF-8') ?></span>
          </div>
        <?php endif; ?>
        <div class="researcher-detail-row">
          <span class="researcher-detail-label">Reason</span>
          <span class="researcher-detail-value"><?= htmlspecialchars($certReason !== '' ? $certReason : 'No reason was given.', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
      <?php if ($certIsImage): ?>
        <button type="button" class="cert-preview" data-zoom-src="<?= $certFileUrl ?>" data-zoom-alt="Your training certificate" aria-label="Zoom in on your training certificate">
          <img src="<?= $certFileUrl ?>" alt="Your training certificate">
        </button>
      <?php else: ?>
        <p class="cert-preview-link"><a class="researcher-name-link" href="<?= $certFileUrl ?>" target="_blank" rel="noopener">Open certificate (PDF)</a></p>
      <?php endif; ?>
      <div class="modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeCertRejectedModal()">Close</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="button" onclick="resubmitCertFromRejectedModal()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          Resubmit Certificate
        </button>
      </div>
    </div>
  </div>
</div>