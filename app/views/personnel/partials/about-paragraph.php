<?php

$paragraphKey = $paragraphKey ?? 'new';
$paragraph    = $paragraph ?? '';
$number       = $number ?? 1;
?>
<div class="cm-paragraph" data-cm-paragraph data-key="<?= $paragraphKey ?>">
  <div class="cm-paragraph-head">
    <label class="field-label" for="about_paragraph_<?= $number ?>">About IACUC &ndash; Paragraph <span data-cm-paragraph-number><?= $number ?></span></label>
    <div class="ann-row-actions">
      <button type="button" class="row-btn" data-cm-paragraph-remove aria-label="Delete paragraph <?= $number ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#trash-icon" />
        </svg>
      </button>
    </div>
  </div>
  <textarea id="about_paragraph_<?= $number ?>" name="about_paragraphs[]" rows="4"><?= htmlspecialchars($paragraph, ENT_QUOTES) ?></textarea>
</div>