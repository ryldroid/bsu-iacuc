<?php
// Shared protocol modals: file popup, rename, delete / request deletion,
// amendments, amendment submit and rename history.
// They are driven by assets/js/protocol-modals.js and take a protocol id when
// opened, so the viewer and the list pages can share them. The server still
// enforces permissions on every endpoint they call.
include_once __DIR__ . '/file-popup.php';
?>
<link rel="stylesheet" href="<?= asset_css('protocol-modals.css') ?>">
<script src="<?= asset_js('file-popup.js') ?>"></script>

<!-- ===== Rename protocol modal ===== -->
<div class="modal-backdrop" id="renameModalBackdrop" data-static>
  <div class="modal-card panel-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#edit-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Rename Protocol</p>
        <p class="modal-title" id="renameModalTitle"></p>
      </div>
      <button class="modal-close" onclick="closeRenameModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="panel-modal-body">
      <label class="return-comment-label" for="renameTitleInput">New research title</label>
      <input type="text" id="renameTitleInput" class="return-comment-textarea rename-title-input" maxlength="255">

      <div id="renameError" class="error-messages" hidden></div>

      <div class="panel-modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeRenameModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="button" id="renameSubmitBtn" onclick="submitRename()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#edit-icon" />
          </svg>
          Save
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Delete / request deletion modal ===== -->
<div class="modal-backdrop" id="deleteModalBackdrop" data-static>
  <div class="modal-card panel-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon modal-header-icon--danger" id="deleteModalIcon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#trash-icon" data-mode-icon />
        </svg>
      </span>
      <div>
        <p class="modal-label" id="deleteModalLabel"></p>
        <p class="modal-title" id="deleteModalTitle"></p>
      </div>
      <button class="modal-close" onclick="closeDeleteModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="panel-modal-body">
      <p class="panel-modal-intro" id="deleteModalIntro"></p>

      <label class="return-comment-label" for="deleteReasonText">Reason <span class="return-comment-optional" id="deleteReasonRequirement">(required)</span></label>
      <textarea id="deleteReasonText" class="return-comment-textarea"
        placeholder="Explain why this protocol should be deleted..."
        rows="4" maxlength="1000"></textarea>
      <p class="return-char-count"><span id="deleteCharCount">0</span> / 1000</p>

      <div id="deleteError" class="error-messages" hidden></div>

      <div class="panel-modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeDeleteModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="deleteSubmitBtn" onclick="submitDelete()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#trash-icon" data-mode-icon />
          </svg>
          <span id="deleteSubmitLabel"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ===== Amendments list modal ===== -->
<div class="modal-backdrop" id="amendmentsModalBackdrop">
  <div class="modal-card panel-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#history-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Amendments</p>
        <p class="modal-title" id="amendmentsModalTitle"></p>
        <span class="payment-state payment-tone-info" id="amendmentsCount" hidden></span>
      </div>
      <button class="modal-close" onclick="closeAmendmentsModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="panel-modal-body">
      <div class="payment-timeline" id="amendmentsList"></div>
    </div>
  </div>
</div>

<!-- ===== Submit amendment modal (researcher, approved protocols) ===== -->
<div class="modal-backdrop" id="amendmentModalBackdrop" data-static>
  <div class="modal-card panel-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#upload-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label">Submit Amendment</p>
        <p class="modal-title" id="amendmentModalTitle"></p>
      </div>
      <button class="modal-close" onclick="closeAmendmentModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="panel-modal-body">
      <p class="panel-modal-intro">If you have an amendment to this approved protocol, submit the updated file here and explain the changes to inform CCARD. This protocol will not undergo the review process again unless deemed necessary by CCARD or the IACUC. You will be notified if any action is required on your end.</p>

      <div class="doc-list">
        <div class="doc-row">
          <div class="doc-row-info">
            <div class="doc-row-title">Updated protocol <span class="req">*</span></div>
            <span class="doc-row-sub" id="amendmentFileName">PDF, DOC, or DOCX, max 10 MB</span>
            <div class="upload-progress-container" id="amendmentFileProgress"></div>
          </div>
          <div class="doc-row-action" id="amendmentFileAction">
            <button type="button" class="resub-btn-link is-hidden" id="amendmentPreviewBtn"
              onclick="previewAmendmentFile()">Preview</button>
            <label class="btn-upload-inline">
              <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#upload-icon" />
              </svg>
              Upload
              <input type="file" id="amendmentFile" accept=".pdf,.doc,.docx" onchange="handleAmendmentFile(event)">
            </label>
          </div>
        </div>
      </div>

      <div class="notice notice-info is-hidden" id="amendmentConvertedNotice">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#info-icon" />
        </svg>
        <span><strong>Converted to PDF:</strong> Please review the converted file before submitting, since conversion can shift the layout slightly.</span>
      </div>

      <div class="notice notice-info">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#info-icon" />
        </svg>
        <span><strong>Signatures required:</strong> Print, sign, and scan the updated protocol, then upload the signed copy.</span>
      </div>

      <label class="return-comment-label" for="amendmentNote">What changed and why <span class="return-comment-optional">(required)</span></label>
      <textarea id="amendmentNote" class="return-comment-textarea"
        placeholder="Briefly describe the change to your methods..."
        rows="4" maxlength="1000"></textarea>
      <p class="return-char-count"><span id="amendmentCharCount">0</span> / 1000</p>

      <div id="amendmentError" class="error-messages" hidden></div>

      <div class="upload-progress-container" id="amendmentProgress"></div>

      <div class="panel-modal-actions">
        <button class="confirm-modal-btn confirm-modal-cancel" type="button" onclick="closeAmendmentModal()">Cancel</button>
        <button class="confirm-modal-btn confirm-modal-ok" type="button" id="amendmentSubmitBtn" onclick="submitAmendment()">
          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
          </svg>
          Submit
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= asset_js('protocol-modals.js') ?>"></script>