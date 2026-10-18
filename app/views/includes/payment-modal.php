<?php

/** @var bool        $isBsuResearcher */
/** @var string|null $paymentModalTitle */
?>
<!-- ===== Payment modal (shared by the submissions list and the protocol viewer) ===== -->
<div class="modal-backdrop" id="paymentModalBackdrop" data-static>
  <div class="modal-card panel-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#check-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Pay Clearance Fee</p>
        <p class="modal-title" id="paymentModalTitle"><?= htmlspecialchars($paymentModalTitle ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      </div>
      <button class="modal-close" type="button" onclick="closePaymentModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="panel-modal-body">
      <p class="modal-notice">Your protocol passed review. To get your animal research clearance from the Bureau of Animal Industry, pay the Php 100.00 fee below.</p>

      <div id="paymentModalError" class="alert error-messages" hidden></div>

      <?php if (!$isBsuResearcher): ?>
        <div class="consent-list">
          <label class="consent-item">
            <input type="radio" class="consent-radio" name="payment_method" value="in_person"
              checked onchange="handlePaymentMethodChange()">
            <span>Pay in person at CCARD</span>
          </label>
          <label class="consent-item">
            <input type="radio" class="consent-radio" name="payment_method" value="online"
              onchange="handlePaymentMethodChange()">
            <span>Pay online</span>
          </label>
        </div>
      <?php endif; ?>

      <div id="paymentInPersonPanel">
        <p class="modal-notice">Settle the fee at the BSU-CCARD office, then tick the box below to confirm payment.</p>
        <div class="consent-list">
          <label class="consent-item">
            <input type="checkbox" class="consent-checkbox" id="confirm_in_person_paid">
            <span>I confirm that I have paid the Php 100.00 fee in person at CCARD.</span>
          </label>
        </div>
      </div>

      <div id="paymentOnlinePanel" hidden>
        <p class="modal-notice">
          <a href="<?= ROOT ?>/contact#director-contact" class="underlined" target="_blank" rel="noopener noreferrer">Contact the CCARD Director</a>
          to arrange online payment. Once paid, upload a photo of your receipt (or a photo of you handing over the payment) below.
        </p>

        <div class="modal-file-row">
          <div class="modal-file-info">
            <div class="modal-file-title">Proof of payment <span class="required-asterisk">*</span></div>
            <div class="modal-file-subtitle" id="paymentFileSubtitle">PDF or image &middot; max 10 MB</div>
          </div>
          <label class="modal-file-picker">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#upload-icon" />
            </svg>
            <span id="paymentFilePickerLabel">Upload</span>
            <input type="file" id="payment_proof_file" name="payment_proof_file"
              accept=".pdf,application/pdf,.jpg,.jpeg,.png,image/jpeg,image/png"
              onchange="handlePaymentFileChange(this)">
          </label>
        </div>

        <div class="upload-progress-container" id="paymentProofProgress"></div>
      </div>

      <div class="modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closePaymentModal()">Back</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="button" id="paymentModalSubmitBtn"
          onclick="submitPaymentProof()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          <span id="paymentModalSubmitLabel">Verify Payment</span>
        </button>
      </div>
    </div>
  </div>
</div>