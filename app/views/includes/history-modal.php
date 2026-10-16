<div class="modal-backdrop" id="historyModalBackdrop">
  <div class="modal-card history-modal-card">
    <div class="modal-header">
      <div class="history-modal-title-wrapper">
        <p class="modal-label">Submission History</p>
        <p class="modal-title" id="historyModalTitle"></p>
      </div>
      <button class="modal-close" onclick="closeHistoryModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div id="historyModalBody" class="history-modal-body">
      <p class="helper history-loading">Loading&hellip;</p>
    </div>
  </div>
</div>