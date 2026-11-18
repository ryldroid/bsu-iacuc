<?php
$numberKind  = $numberKind ?? '';
$numberLabel = $numberLabel ?? '';
$csrf        = $csrf ?? '';
$numberFormat = NumberFormat::of($numberKind);
$numberName   = NumberFormat::NAMES[$numberKind] ?? $numberLabel;
$numberValues = $numberFormat->values();
$numberIntro  = $numberKind === 'ar'
    ? 'Sets how AR Numbers must be written. Only the prefix and year are pre-filled. Modifying the format will not affect existing AR Numbers.'
    : 'Sets how new IPNs are written and numbered. IPNs that already exist are not changed. Numbering restarts at 1 each year when the format includes a year.';
?>
<!-- ===== <?= htmlspecialchars($numberLabel, ENT_QUOTES) ?> ===== -->
<section class="accounts-card">
    <h2>
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#edit-icon" />
        </svg>
        <?= htmlspecialchars($numberName, ENT_QUOTES) ?>
    </h2>
    <p class="audit-description"><?= $numberIntro ?></p>
    <form id="<?= $numberKind ?>FormatForm" method="POST" action="<?= ROOT ?>/personnel/number_formats_save" data-number-format="<?= $numberKind ?>" data-cm-dirty
        data-confirm-message="Save the new <?= htmlspecialchars($numberLabel, ENT_QUOTES) ?> format?" data-confirm-ok-text="Save">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="kind" value="<?= $numberKind ?>">
        <div class="records-form-grid">
            <div class="records-form-group">
                <label class="field-label" for="<?= $numberKind ?>_prefix">Prefix</label>
                <input type="text" id="<?= $numberKind ?>_prefix" name="<?= $numberKind ?>_prefix" maxlength="6" pattern="[A-Za-z0-9.\-]{0,6}"
                    value="<?= htmlspecialchars($numberValues[$numberKind . '_prefix'], ENT_QUOTES) ?>">
                <span class="helper">Optional. Up to 6 letters, numbers, dots or dashes.</span>
            </div>
            <div class="records-form-group">
                <label class="field-label" for="<?= $numberKind ?>_separator">Separator</label>
                <select id="<?= $numberKind ?>_separator" name="<?= $numberKind ?>_separator">
                    <?php foreach (NumberFormat::SEPARATORS as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>" <?= $numberValues[$numberKind . '_separator'] === (string) $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="helper">Placed between the prefix, number and year.</span>
            </div>
            <div class="records-form-group">
                <label class="field-label" for="<?= $numberKind ?>_digits">Number length</label>
                <select id="<?= $numberKind ?>_digits" name="<?= $numberKind ?>_digits">
                    <?php for ($digits = 1; $digits <= 8; $digits++): ?>
                        <option value="<?= $digits ?>" <?= $numberValues[$numberKind . '_digits'] === (string) $digits ? 'selected' : '' ?>><?= $digits ?> digit<?= $digits > 1 ? 's' : '' ?></option>
                    <?php endfor; ?>
                </select>
                <span class="helper">The running number is padded with zeros.</span>
            </div>
            <div class="records-form-group">
                <label class="field-label" for="<?= $numberKind ?>_year">Year</label>
                <select id="<?= $numberKind ?>_year" name="<?= $numberKind ?>_year">
                    <?php foreach (NumberFormat::YEARS as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $numberValues[$numberKind . '_year'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="records-form-group records-form-full">
                <label class="field-label" for="<?= $numberKind ?>_year_position">Year position</label>
                <select id="<?= $numberKind ?>_year_position" name="<?= $numberKind ?>_year_position">
                    <?php foreach (NumberFormat::POSITIONS as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $numberValues[$numberKind . '_year_position'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="helper">Only used when a year is included.</span>
            </div>
        </div>
        <div class="number-preview">
            <span class="number-preview-label">Example</span>
            <strong class="number-preview-value" id="<?= $numberKind ?>Preview" aria-live="polite" data-year="<?= date('Y') ?>"><?= htmlspecialchars($numberFormat->format(1), ENT_QUOTES) ?></strong>
        </div>
        <div class="cm-savebar">
            <span class="cm-savebar-status">
                <span class="cm-savebar-clean">No unsaved changes</span>
                <span class="cm-savebar-dirty">You have unsaved changes</span>
            </span>
            <button type="reset" class="cm-link-btn" data-cm-dirty-reset hidden>Discard</button>
            <button type="submit" class="accounts-btn-primary" data-cm-dirty-submit disabled>Save Changes</button>
        </div>
    </form>
</section>