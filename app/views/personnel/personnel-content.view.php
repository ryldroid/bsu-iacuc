<?php

/** @var array  $user          */
/** @var string $csrf          */
/** @var array  $announcements */
/** @var array  $settings      */
/** @var array  $offices       */
/** @var array  $faqs          */
/** @var string $activeTab     */

$title = 'Content';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user          = $user          ?? $_SESSION['user'] ?? [];
$csrf          = $csrf          ?? '';
$announcements = $announcements ?? [];
$settings      = $settings      ?? [];
$offices       = $offices       ?? [];
$faqs          = $faqs          ?? [];
$activeTab     = $activeTab     ?? 'announcements';
?>

<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/accounts.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/records.css') ?>">
<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">
<link rel="stylesheet" href="<?= asset_css('tabs.css') ?>">
<link rel="stylesheet" href="<?= asset_css('content-manager.css') ?>">

<div class="body">
  <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="page-banner">
      <div class="page-banner-text">
        <h1>Edit Site Content</h1>
        <p class="page-subtitle">Announcements and the information shown on the public pages</p>
      </div>
    </div>

    <?php include dirname(__DIR__) . '/includes/flash.php'; ?>

    <div class="tab-strip" role="tablist" aria-label="Content sections" data-tab-panels="contentTabPanels">
      <button type="button" role="tab" class="has-guide" id="tab-announcements" data-tab="announcements"
        data-tab-href="<?= ROOT ?>/personnel/announcements"
        aria-selected="<?= $activeTab === 'announcements' ? 'true' : 'false' ?>"
        aria-controls="panel-announcements">Announcements</button>
      <?php $tabGuideId = 'site-announcements';
      $tabGuideLabel = 'Announcements';
      include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
      <button type="button" role="tab" class="has-guide" id="tab-site_content" data-tab="site_content"
        data-tab-href="<?= ROOT ?>/personnel/site_content"
        aria-selected="<?= $activeTab === 'site_content' ? 'true' : 'false' ?>"
        aria-controls="panel-site_content">Homepage</button>
      <?php $tabGuideId = 'site-homepage';
      $tabGuideLabel = 'Homepage';
      include dirname(__DIR__) . '/includes/tab-guide.php'; ?>
    </div>

    <div id="contentTabPanels">

      <!-- ===== ANNOUNCEMENTS PANEL ===== -->
      <div class="tab-panel" id="panel-announcements" role="tabpanel" aria-labelledby="tab-announcements"
        data-tab-panel="announcements" <?= $activeTab === 'announcements' ? '' : 'hidden' ?>>

        <div class="dashboard-page-header records-page-header">
          <div>
            <h2><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#announcement-icon" />
              </svg> Manage Announcements <span class="cm-count" data-cm-count="annListWrap" aria-live="polite"><?= count($announcements) ?></span></h2>
          </div>
          <div class="cm-tools">
            <?php $cmSearchTarget = 'annListWrap';
            $cmSearchPlaceholder = 'Search announcements...';
            include dirname(__DIR__) . '/includes/cm-search.php'; ?>
            <button class="row-btn row-btn-primary" id="addAnnouncementBtn" type="button" data-cm-add="announcement">
              <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#add-icon" />
              </svg>
              Add Announcement
            </button>
          </div>
        </div>

        <div class="cm-list-wrap" id="annListWrap">
          <?php if (empty($announcements)): ?>
            <div class="cm-empty">
              <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#announcement-icon" />
              </svg>
              <h3>No announcements yet</h3>
              <p>Press Add Announcement to post the first one.</p>
            </div>
          <?php else: ?>
            <div class="ann-list">
              <?php foreach ($announcements as $a):
                $annTitle = normalize_pasted_text($a['title']);
                $annBody  = normalize_pasted_text($a['body']);
                $hasImage = !empty($a['image_path']);
                $annImage = $hasImage ? ROOT . '/assets/uploads/announcements/' . rawurlencode($a['image_path']) : '';
                $annRowTs = strtotime($a['created_at']);
              ?>
                <div class="ann-row cm-row" data-cm-entity="announcement" data-id="<?= (int) $a['id'] ?>"
                  data-cm-data="<?= htmlspecialchars(json_encode($a, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                  data-cm-label="<?= htmlspecialchars($annTitle !== '' ? $annTitle : 'Untitled', ENT_QUOTES) ?>">
                  <div class="ann-row-thumb" <?= $hasImage ? 'data-zoom-src="' . htmlspecialchars($annImage, ENT_QUOTES) . '" data-zoom-alt="' . htmlspecialchars($annTitle, ENT_QUOTES) . '"' : '' ?>>
                    <?php if ($hasImage): ?>
                      <img src="<?= htmlspecialchars($annImage, ENT_QUOTES) ?>" alt="">
                    <?php else: ?>
                      <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#file-x-icon" />
                      </svg>
                    <?php endif; ?>
                  </div>
                  <div class="ann-row-body">
                    <div class="ann-row-title<?= $annTitle === '' ? ' ann-empty-state' : '' ?>"><?= htmlspecialchars($annTitle !== '' ? $annTitle : 'Untitled', ENT_QUOTES) ?><span class="pending-sync-badge" data-pending-sync="announcement:<?= (int) $a['id'] ?>" hidden></span></div>
                    <div class="ann-row-snippet<?= $annBody === '' ? ' ann-empty-state' : '' ?>"><?= htmlspecialchars($annBody !== '' ? $annBody : 'No caption', ENT_QUOTES) ?></div>
                    <div class="ann-row-date"><?= $annRowTs ? htmlspecialchars(date(DATETIME_FORMAT, $annRowTs), ENT_QUOTES) : htmlspecialchars($a['created_at'], ENT_QUOTES) ?></div>
                  </div>
                  <div class="ann-row-actions">
                    <button type="button" class="row-btn" data-cm-action="edit" aria-label="Edit announcement">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#edit-icon" />
                      </svg>
                    </button>
                    <button type="button" class="row-btn" data-cm-action="delete" aria-label="Delete announcement">
                      <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <use href="#trash-icon" />
                      </svg>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ===== SITE CONTENT PANEL ===== -->
      <div class="tab-panel" id="panel-site_content" role="tabpanel" aria-labelledby="tab-site_content"
        data-tab-panel="site_content" <?= $activeTab === 'site_content' ? '' : 'hidden' ?>>

        <div class="sections">
          <!-- HOMEPAGE CONTENT -->
          <section class="accounts-card">
            <h2><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#home-icon" />
              </svg> Homepage Content</h2>
            <p class="audit-description">These appear on the public homepage.</p>
            <form method="POST" action="<?= ROOT ?>/personnel/site_content_settings" data-cm-dirty
              data-confirm-message="Save changes to the homepage content?" data-confirm-ok-text="Save">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
              <div class="records-form-grid">
                <div class="records-form-group records-form-full">
                  <label class="field-label" for="banner_title">Banner Title</label>
                  <input type="text" id="banner_title" name="banner_title"
                    value="<?= htmlspecialchars($settings['banner_title'] ?? '', ENT_QUOTES) ?>">
                </div>
                <div class="records-form-group records-form-full">
                  <div class="cm-paragraph-list" data-cm-paragraphs="about_paragraph">
                    <?php foreach (array_values($settings['about_paragraphs'] ?? []) as $paragraphKey => $paragraph): ?>
                      <?php $number = $paragraphKey + 1;
                      include __DIR__ . '/partials/about-paragraph.php'; ?>
                    <?php endforeach; ?>
                  </div>
                  <button type="button" class="cm-link-btn" data-cm-paragraph-add>
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                      <use href="#add-icon" />
                    </svg>
                    Add Paragraph
                  </button>
                  <template data-cm-paragraph-template>
                    <?php $paragraphKey = 'new';
                    $paragraph = '';
                    $number = 1;
                    include __DIR__ . '/partials/about-paragraph.php'; ?>
                  </template>
                </div>
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

          <!-- CONTACT OFFICES -->
          <section class="accounts-card">
            <div class="dashboard-page-header records-page-header">
              <div>
                <h2><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#contact-icon" />
                  </svg> Contact Page Offices <span class="cm-count" data-cm-count="officeListWrap" aria-live="polite"><?= count($offices) ?></span></h2>
                <p class="audit-description">These cards appear on the public Contact page.</p>
              </div>
              <div class="cm-tools">
                <button class="row-btn row-btn-primary" id="addOfficeBtn" type="button" data-cm-add="office">
                  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#add-icon" />
                  </svg>
                  Add Office
                </button>
              </div>
            </div>
            <div class="cm-list-wrap" id="officeListWrap">
              <?php if (empty($offices)): ?>
                <div class="cm-empty">
                  <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#contact-icon" />
                  </svg>
                  <h3>No offices yet</h3>
                  <p>Press Add Office to put the first card on the Contact page.</p>
                </div>
              <?php else: ?>
                <div class="ann-list" id="office-list">
                  <?php foreach ($offices as $index => $o): ?>
                    <div class="ann-row cm-row" data-cm-entity="office" data-id="<?= (int) $o['id'] ?>"
                      data-cm-data="<?= htmlspecialchars(json_encode($o, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                      data-cm-label="<?= htmlspecialchars($o['name'], ENT_QUOTES) ?>">
                      <div class="ann-row-body">
                        <div class="ann-row-title"><?= htmlspecialchars($o['name'], ENT_QUOTES) ?><span class="pending-sync-badge" data-pending-sync="office:<?= (int) $o['id'] ?>" hidden></span></div>
                        <div class="ann-row-snippet"><?= htmlspecialchars($o['address'] ?? '', ENT_QUOTES) ?></div>
                      </div>
                      <div class="ann-row-actions">
                        <button type="button" class="row-btn" data-cm-action="move" data-direction="up" aria-label="Move office up" <?= $index === 0 ? 'disabled' : '' ?>>
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-up-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="move" data-direction="down" aria-label="Move office down" <?= $index === count($offices) - 1 ? 'disabled' : '' ?>>
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-down-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="edit" aria-label="Edit office">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#edit-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="delete" aria-label="Delete office">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#trash-icon" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <!-- HOMEPAGE FAQ -->
          <section class="accounts-card">
            <div class="dashboard-page-header records-page-header">
              <div>
                <h2><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#question-info-icon" />
                  </svg> Homepage FAQ <span class="cm-count" data-cm-count="faqListWrap" aria-live="polite"><?= count($faqs) ?></span></h2>
                <p class="audit-description">These questions appear in the FAQ accordion on the public homepage.</p>
              </div>
              <div class="cm-tools">
                <?php $cmSearchTarget = 'faqListWrap';
                $cmSearchPlaceholder = 'Search FAQ...';
                include dirname(__DIR__) . '/includes/cm-search.php'; ?>
                <button class="row-btn row-btn-primary" id="addFaqBtn" type="button" data-cm-add="faq">
                  <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#add-icon" />
                  </svg>
                  Add FAQ
                </button>
              </div>
            </div>
            <div class="cm-list-wrap" id="faqListWrap">
              <?php if (empty($faqs)): ?>
                <div class="cm-empty">
                  <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#question-info-icon" />
                  </svg>
                  <h3>No FAQs yet</h3>
                  <p>Press Add FAQ to add the first question.</p>
                </div>
              <?php else: ?>
                <div class="ann-list">
                  <?php foreach ($faqs as $index => $f): ?>
                    <div class="ann-row cm-row" data-cm-entity="faq" data-id="<?= (int) $f['id'] ?>"
                      data-cm-data="<?= htmlspecialchars(json_encode($f, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), ENT_QUOTES, 'UTF-8') ?>"
                      data-cm-label="<?= htmlspecialchars($f['question'], ENT_QUOTES) ?>">
                      <div class="ann-row-body">
                        <div class="ann-row-title"><?= htmlspecialchars($f['question'], ENT_QUOTES) ?><span class="pending-sync-badge" data-pending-sync="faq:<?= (int) $f['id'] ?>" hidden></span></div>
                        <div class="ann-row-snippet"><?= htmlspecialchars($f['answer'], ENT_QUOTES) ?></div>
                      </div>
                      <div class="ann-row-actions">
                        <button type="button" class="row-btn" data-cm-action="move" data-direction="up" aria-label="Move FAQ up" <?= $index === 0 ? 'disabled' : '' ?>>
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-up-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="move" data-direction="down" aria-label="Move FAQ down" <?= $index === count($faqs) - 1 ? 'disabled' : '' ?>>
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#chev-down-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="edit" aria-label="Edit FAQ">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#edit-icon" />
                          </svg>
                        </button>
                        <button type="button" class="row-btn" data-cm-action="delete" aria-label="Delete FAQ">
                          <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <use href="#trash-icon" />
                          </svg>
                        </button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>

    </div>
  </main>
</div>

<?php
$guideId    = 'site-announcements';
$guideSteps = [
  ['Announcements', 'These are the announcements shown on the public Announcements page. Let us walk through it.'],
  ['Add Announcement', 'Post a new announcement. The title, content, and image are each optional.', '#addAnnouncementBtn'],
];
if ($announcements) {
  $guideSteps[] = ['Search', 'Type to narrow the list by title or caption. The number beside the heading shows how many match.', '#panel-announcements .inbox-search-wrap'];
  $guideSteps[] = ['Announcement list', 'Each row shows the photo, title, caption, and the date it was posted. Click a row to edit it, or press the photo to see it larger.', '#panel-announcements .ann-list'];
  $guideSteps[] = ['Row actions', 'Edit an announcement to change its text, replace its photo, or remove the photo. Delete takes it off the public page.', '#panel-announcements .ann-row-actions:first-visible'];
}
$guideSteps[] = ['Homepage tab', 'Switch to **Homepage** to edit the homepage banner, contact offices, and FAQ.', '#tab-site_content'];
include dirname(__DIR__) . '/includes/guide.php';

$guideId    = 'site-homepage';
$guideSteps = [
  ['Homepage', 'Edit what visitors see on the public homepage and Contact page. Let us walk through it.'],
  ['Homepage Content', 'Change the banner title and the "What is IACUC?" paragraphs. Use **Add Paragraph** for a new one, or the bin button to remove one. **Save Changes** turns on once you change something. Press it and confirm to apply your edits, or press **Discard** to undo them.', '#panel-site_content .sections > .accounts-card:nth-child(1)'],
  ['Add Office', 'Add an office card to the public Contact page.', '#addOfficeBtn'],
];
if ($offices) {
  $guideSteps[] = ['Office list', 'Use the arrows to change the order the offices appear in, or edit and delete an office. Clicking a row also opens it for editing.', '#office-list .ann-row-actions:first-visible'];
}
$guideSteps[] = ['Add FAQ', 'Add a question and answer to the FAQ on the public homepage.', '#addFaqBtn'];
if ($faqs) {
  $guideSteps[] = ['Search FAQ', 'Type to find a question or answer in the list.', '#panel-site_content .sections > .accounts-card:nth-child(3) .inbox-search-wrap'];
  $guideSteps[] = ['FAQ list', 'Use the arrows to change the order the questions appear in, or edit and delete a question. Changes show on the homepage right away.', '#panel-site_content .sections > .accounts-card:nth-child(3) .ann-row-actions:first-visible'];
}
$guideSteps[] = ['Announcements tab', 'Switch to **Announcements** to post and manage announcements.', '#tab-announcements'];
include dirname(__DIR__) . '/includes/guide.php';
?>

<!-- ===== ADD / EDIT ANNOUNCEMENT MODAL (shared) ===== -->
<div class="modal-backdrop" id="announcementModal" data-cm-modal data-static data-confirm-discard role="dialog" aria-modal="true" aria-labelledby="announcementModalTitle">
  <div class="modal-card records-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#announcement-icon" />
        </svg>
      </span>
      <p class="modal-title" id="announcementModalTitle">Add Announcement</p>
      <button type="button" class="modal-close" data-close="announcementModal" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" hidden></div>
      <div class="records-form-grid">
        <div class="records-form-group records-form-full">
          <label class="field-label" for="ann_title">Title (optional)</label>
          <input type="text" id="ann_title" placeholder="e.g. IACUC Protocol Orientation">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="ann_body">Content (optional)</label>
          <textarea id="ann_body" rows="5"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="ann_image">Image (optional)</label>
          <label class="modal-file-picker">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
              <use href="#upload-icon" />
            </svg>
            <span data-picker-label>Choose image</span>
            <input type="file" id="ann_image" accept="image/png,image/jpeg,image/webp,image/gif">
          </label>
          <div class="ann-image-preview-wrap" id="ann_image_preview_wrap" hidden>
            <img id="ann_image_preview" alt="Image preview">
            <button type="button" class="image-remove-btn" id="ann_image_remove_btn" title="Remove image" aria-label="Remove image">
              <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#trash-icon" />
              </svg>
            </button>
          </div>
          <div class="upload-progress-container" id="annImageProgress"></div>
        </div>
      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="announcementModal">Cancel</button>
      <button type="button" class="confirm-modal-btn confirm-modal-ok" data-cm-submit="announcement">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        <span data-cm-save-label>Post Announcement</span>
      </button>
    </div>
  </div>
</div>

<!-- ===== ADD / EDIT OFFICE MODAL (shared) ===== -->
<div class="modal-backdrop" id="officeModal" data-cm-modal data-static data-confirm-discard role="dialog" aria-modal="true" aria-labelledby="officeModalTitle">
  <div class="modal-card records-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#contact-icon" />
        </svg>
      </span>
      <p class="modal-title" id="officeModalTitle">Add Office</p>
      <button type="button" class="modal-close" data-close="officeModal" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" hidden></div>
      <div class="records-form-grid">
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_name">Office Name *</label>
          <input type="text" id="office_name" maxlength="255" placeholder="e.g. Bureau of Animal Industry">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_address">Address</label>
          <textarea id="office_address" rows="2" maxlength="500"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_phone">Phone(s) &ndash; one per line if more than one</label>
          <textarea id="office_phone" rows="2" maxlength="255"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_email">Email(s) &ndash; one per line if more than one</label>
          <textarea id="office_email" rows="2" maxlength="500"></textarea>
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_facebook_url">Facebook URL</label>
          <input type="url" id="office_facebook_url" maxlength="500" placeholder="https://www.facebook.com/...">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_facebook_label">Facebook Link Text</label>
          <input type="text" id="office_facebook_label" maxlength="255" placeholder="e.g. BSU - CCARD">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_website_url">Website URL</label>
          <input type="url" id="office_website_url" maxlength="500" placeholder="https://www.bsu.edu.ph">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_website_label">Website Link Text</label>
          <input type="text" id="office_website_label" maxlength="255" placeholder="e.g. bsu.edu.ph">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_director_name">Contact Person Name (optional)</label>
          <input type="text" id="office_director_name" maxlength="255">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_director_role">Contact Person Role (optional)</label>
          <input type="text" id="office_director_role" maxlength="255" placeholder="e.g. Director, BSU-CCARD">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="office_director_email">Contact Person Email (optional)</label>
          <input type="email" id="office_director_email" maxlength="255">
        </div>
      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="officeModal">Cancel</button>
      <button type="button" class="confirm-modal-btn confirm-modal-ok" data-cm-submit="office">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        <span data-cm-save-label>Save Office</span>
      </button>
    </div>
  </div>
</div>

<!-- ===== ADD / EDIT FAQ MODAL (shared) ===== -->
<div class="modal-backdrop" id="faqModal" data-cm-modal data-static data-confirm-discard role="dialog" aria-modal="true" aria-labelledby="faqModalTitle">
  <div class="modal-card records-modal-card">
    <div class="modal-header">
      <span class="modal-header-icon" aria-hidden="true">
        <svg width="20" height="20" viewBox="0 0 24 24" focusable="false">
          <use href="#question-info-icon" />
        </svg>
      </span>
      <p class="modal-title" id="faqModalTitle">Add FAQ</p>
      <button type="button" class="modal-close" data-close="faqModal" aria-label="Close">
        <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#close-icon" />
        </svg>
      </button>
    </div>
    <div class="records-modal-body">
      <div class="alert error-messages" hidden></div>
      <div class="records-form-grid">
        <div class="records-form-group records-form-full">
          <label class="field-label" for="faq_question">Question *</label>
          <input type="text" id="faq_question" placeholder="e.g. Who may avail?">
        </div>
        <div class="records-form-group records-form-full">
          <label class="field-label" for="faq_answer">Answer *</label>
          <textarea id="faq_answer" rows="4"></textarea>
        </div>
      </div>
    </div>
    <div class="records-modal-footer">
      <button type="button" class="confirm-modal-btn confirm-modal-cancel" data-close="faqModal">Cancel</button>
      <button type="button" class="confirm-modal-btn confirm-modal-ok" data-cm-submit="faq">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        <span data-cm-save-label>Save FAQ</span>
      </button>
    </div>
  </div>
</div>

<?php
// What js/content-manager.js needs to know about this page's lists
$cmConfig = [
  'root' => ROOT,
  'csrf' => $csrf,
  'entities' => [
    'announcement' => [
      'modal'     => 'announcementModal',
      'title'     => 'announcementModalTitle',
      'refresh'   => ['annListWrap'],
      'fields'    => ['title' => 'ann_title', 'body' => 'ann_body'],
      'image'     => [
        'field'      => 'image',
        'column'     => 'image_path',
        'base'       => '/assets/uploads/announcements/',
        'input'      => 'ann_image',
        'previewWrap' => 'ann_image_preview_wrap',
        'preview'    => 'ann_image_preview',
        'removeBtn'  => 'ann_image_remove_btn',
        'progress'   => 'annImageProgress',
      ],
      'endpoints' => [
        'get'    => '/personnel/announcements_get',
        'add'    => '/personnel/announcements_add',
        'edit'   => '/personnel/announcements_edit',
        'delete' => '/personnel/announcements_delete',
      ],
      'validate'  => ['any' => ['title', 'body'], 'anyImage' => true, 'anyMessage' => 'Add a title, content, or an image.'],
      'text'      => [
        'addTitle'      => 'Add Announcement',
        'editTitle'     => 'Edit Announcement',
        'addButton'     => 'Post Announcement',
        'editButton'    => 'Save Changes',
        'confirmAdd'    => 'Post this announcement?',
        'confirmEdit'   => 'Save changes to this announcement?',
        'addOk'         => 'Post',
        'editOk'        => 'Save',
        'confirmDelete' => 'Delete "{name}"? This cannot be undone.',
      ],
    ],
    'office' => [
      'modal'     => 'officeModal',
      'title'     => 'officeModalTitle',
      'refresh'   => ['officeListWrap'],
      'fields'    => [
        'name'           => 'office_name',
        'address'        => 'office_address',
        'phone'          => 'office_phone',
        'email'          => 'office_email',
        'facebook_url'   => 'office_facebook_url',
        'facebook_label' => 'office_facebook_label',
        'website_url'    => 'office_website_url',
        'website_label'  => 'office_website_label',
        'director_name'  => 'office_director_name',
        'director_role'  => 'office_director_role',
        'director_email' => 'office_director_email',
      ],
      'endpoints' => [
        'get'    => '/personnel/site_content_office_get',
        'add'    => '/personnel/site_content_office_add',
        'edit'   => '/personnel/site_content_office_edit',
        'delete' => '/personnel/site_content_office_delete',
        'move'   => '/personnel/site_content_office_move',
      ],
      'validate'  => ['all' => ['name'], 'allMessage' => 'Office name is required.'],
      'text'      => [
        'addTitle'      => 'Add Office',
        'editTitle'     => 'Edit Office',
        'addButton'     => 'Save Office',
        'editButton'    => 'Save Office',
        'confirmAdd'    => 'Add this office to the Contact page?',
        'confirmEdit'   => 'Save changes to this office?',
        'addOk'         => 'Save',
        'editOk'        => 'Save',
        'confirmDelete' => 'Delete "{name}"? This removes it from the public Contact page.',
      ],
    ],
    'faq' => [
      'modal'     => 'faqModal',
      'title'     => 'faqModalTitle',
      'refresh'   => ['faqListWrap'],
      'fields'    => ['question' => 'faq_question', 'answer' => 'faq_answer'],
      'endpoints' => [
        'get'    => '/personnel/site_content_faq_get',
        'add'    => '/personnel/site_content_faq_add',
        'edit'   => '/personnel/site_content_faq_edit',
        'delete' => '/personnel/site_content_faq_delete',
        'move'   => '/personnel/site_content_faq_move',
      ],
      'validate'  => ['all' => ['question', 'answer'], 'allMessage' => 'Both a question and an answer are required.'],
      'text'      => [
        'addTitle'      => 'Add FAQ',
        'editTitle'     => 'Edit FAQ',
        'addButton'     => 'Save FAQ',
        'editButton'    => 'Save FAQ',
        'confirmAdd'    => 'Add this FAQ to the homepage?',
        'confirmEdit'   => 'Save changes to this FAQ?',
        'addOk'         => 'Save',
        'editOk'        => 'Save',
        'confirmDelete' => 'Delete "{name}"? This removes it from the public homepage.',
      ],
    ],
  ],
];
?>
<script type="application/json" id="cmConfig">
  <?= json_encode($cmConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>
</script>

<script src="<?= asset_js('tabs.js') ?>" defer></script>
<script src="<?= asset_js('flash-dismiss.js') ?>" defer></script>
<script src="<?= asset_js('content-manager.js') ?>" defer></script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>