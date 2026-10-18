<div class="modal-backdrop" id="historyModalBackdrop">
  <div class="modal-card history-modal-card" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#history-icon" />
        </svg>
      </span>
      <div class="history-modal-title-wrapper">
        <p class="modal-label">Submission History</p>
        <p class="modal-title" id="historyModalTitle"></p>
      </div>
      <button class="modal-close" id="historyModalClose" onclick="closeHistoryModal()" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div id="historyModalBody" class="panel-modal-body history-timeline-body">
      <p class="helper history-loading">Loading&hellip;</p>
    </div>
  </div>
</div>