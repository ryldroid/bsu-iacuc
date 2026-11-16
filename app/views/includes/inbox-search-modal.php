<?php

/** @var string $searchPlaceholder  hint inside the search box, e.g. "Search by title..." */
?>
<!-- ===== Search modal ===== -->
<div class="modal-backdrop" id="inboxSearchBackdrop">
    <div class="modal-card panel-modal-card inbox-search-modal-card">
        <div class="modal-header">
            <span class="modal-header-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
                    <use href="#search-icon" />
                </svg>
            </span>
            <p class="modal-title">Search protocols</p>
            <button class="modal-close" type="button" id="inboxSearchClose" aria-label="Close">
                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>
        <div class="panel-modal-body">
            <div class="inbox-search-wrap">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                <input type="search" id="inboxSearchInput" class="inbox-search-input"
                    aria-label="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="<?= htmlspecialchars($searchPlaceholder, ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="search" data-lpignore="true" data-1p-ignore data-bwignore data-form-type="other">
                <button class="inbox-search-clear" id="inboxSearchClear" aria-label="Clear search">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#close-icon" />
                    </svg>
                </button>
            </div>
            <p class="results-summary" id="resultsSummary" aria-live="polite"></p>
            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-ok" type="button" id="inboxSearchDone">Done</button>
            </div>
        </div>
    </div>
</div>
