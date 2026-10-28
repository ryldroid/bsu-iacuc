<?php $filePrefix = $filePrefix ?? 'add'; ?>
<div class="records-form-group records-form-full" id="<?= $filePrefix ?>_file_field">
  <label class="field-label" for="<?= $filePrefix ?>_protocol_file">Protocol File</label>
  <label class="modal-file-picker" data-file-picker>
    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <use href="#upload-icon" />
    </svg>
    <span>Choose file</span>
    <input type="file" id="<?= $filePrefix ?>_protocol_file" name="protocol_file" accept=".pdf,.doc,.docx">
  </label>
  <div class="resub-doc is-done is-hidden" data-file-card>
    <span class="resub-doc-badge"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <use href="#check-circle-icon" />
      </svg></span>
    <div class="resub-doc-info">
      <div class="resub-doc-title" data-file-name></div>
      <span class="resub-doc-sub is-file" data-file-sub></span>
    </div>
    <div class="resub-doc-actions">
      <a class="resub-btn-link is-hidden" data-file-preview target="_blank" rel="noopener noreferrer">Preview</a>
      <label class="resub-btn-link" for="<?= $filePrefix ?>_protocol_file">Replace</label>
      <button type="button" class="resub-btn-link is-danger" data-file-remove>Remove</button>
    </div>
  </div>
  <span class="helper" data-file-note>Optional. PDF or Word (Word is converted to PDF), max 10 MB.</span>
</div>