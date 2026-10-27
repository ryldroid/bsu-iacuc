<!-- ===== Researcher details modal ===== -->
<div class="modal-backdrop" id="researcherModalBackdrop">
  <div class="modal-card history-modal-card researcher-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#account-icon" />
        </svg>
      </span>
      <div>
        <p class="modal-label" id="researcherModalLabel">Researcher</p>
        <p class="modal-title" id="researcherModalName"></p>
      </div>
      <button class="modal-close" onclick="closeResearcherModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div id="researcherModalBody" class="history-modal-body researcher-modal-body">
      <p class="helper history-loading">Loading&hellip;</p>
    </div>
  </div>
</div>

<script src="<?= asset_js('personnel-researcher-modal.js') ?>"></script>