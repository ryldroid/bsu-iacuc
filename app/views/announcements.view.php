<?php
$title = 'Announcements';

include "includes/header.php";
include "includes/scroll-top.php";

require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
$announcementModel = new AnnouncementModel();
$officeAnnouncements = $announcementModel->getAll();

?>

<link rel="stylesheet" href="<?= asset_css('announcements.css') ?>">

<div id="fb-root"></div>
<script async defer crossorigin="anonymous"
    src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v19.0&appId=1366404755375168">
</script>

<div class="body">
    <?php include "includes/navigation.php"; ?>

    <main class="main-content" id="main-content" tabindex="-1">

        <div class="announcements-header">
            <h1 class="announcements-title">Announcements</h1>
        </div>

        <div class="announcements-sections">
            <section class="announcements">
                <h2>From Our Office</h2>

                <?php if (empty($officeAnnouncements)): ?>
                    <p class="announcements-empty">No announcements yet. Check back soon.</p>
                <?php else: ?>
                    <div class="office-announcements-list">
                        <?php foreach ($officeAnnouncements as $post):
                            $annTitle = normalize_pasted_text(trim($post['title'] ?? ''));
                            $annBody = normalize_pasted_text(trim($post['body'] ?? ''));
                            $hasImage = !empty($post['image_path']);
                            $hasTitle = $annTitle !== '';
                            $hasBody = $annBody !== '';
                            $isPhotoOnly = $hasImage && !$hasTitle && !$hasBody;

                            if ($hasTitle) {
                                $annHeading = $annTitle;
                                $annSnippet = $hasBody ? $annBody : '';
                            } elseif ($hasBody) {
                                $annHeading = $annBody;
                                $annSnippet = '';
                            } else {
                                $annHeading = 'Photo update';
                                $annSnippet = '';
                            }
                            $isUntitled = !$hasTitle && !$hasBody;
                            $annTimestamp = strtotime($post['created_at']);
                            $annDateDisplay = $annTimestamp ? date('M j, Y g:i A', $annTimestamp) : htmlspecialchars($post['created_at'], ENT_QUOTES);
                            $annDateIso = $annTimestamp ? date('c', $annTimestamp) : '';
                            $annImgUrl = $hasImage ? ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) : '';
                        ?>
                            <button type="button"
                                class="office-announcement-card<?= $isPhotoOnly ? ' office-announcement-card--photo' : '' ?><?= $isUntitled ? ' is-untitled' : '' ?>"
                                data-ann-modal="annModalTpl-<?= (int) $post['id'] ?>"
                                aria-haspopup="dialog">

                                <?php if ($hasImage): ?>
                                    <span class="office-announcement-thumb">
                                        <img src="<?= $annImgUrl ?>" alt="">
                                    </span>
                                <?php endif; ?>

                                <?php if (!$isPhotoOnly): ?>
                                    <span class="office-announcement-body-wrap">
                                        <span class="office-announcement-title"><?= htmlspecialchars($annHeading, ENT_QUOTES) ?></span>
                                        <?php if ($annSnippet !== ''): ?>
                                            <span class="office-announcement-snippet"><?= htmlspecialchars($annSnippet, ENT_QUOTES) ?></span>
                                        <?php endif; ?>
                                        <time class="office-announcement-date" datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                    </span>
                                <?php else: ?>
                                    <time class="office-announcement-date office-announcement-date--photo" datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                <?php endif; ?>
                            </button>

                            <template id="annModalTpl-<?= (int) $post['id'] ?>">
                                <?php if ($hasImage): ?>
                                    <div class="announcement-modal-image-wrap">
                                        <img class="announcement-modal-image"
                                            src="<?= $annImgUrl ?>" alt="">
                                        <button type="button" class="image-zoom-btn" title="Zoom image" aria-label="Zoom image"
                                            data-zoom-src="<?= $annImgUrl ?>" data-zoom-alt="<?= htmlspecialchars($annHeading, ENT_QUOTES) ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <use href="#search-icon" />
                                            </svg>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hasTitle): ?>
                                    <h2 class="announcement-modal-title"><?= htmlspecialchars($annHeading, ENT_QUOTES) ?></h2>
                                <?php endif; ?>
                                <time class="announcement-modal-date" datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                <?php if ($hasBody): ?>
                                    <p class="announcement-modal-text"><?= nl2br(htmlspecialchars($annBody, ENT_QUOTES)) ?></p>
                                <?php endif; ?>
                            </template>
                        <?php endforeach; ?>
                    </div>

                    <div class="modal-backdrop" id="officeAnnouncementModal" role="dialog" aria-modal="true">
                        <div class="modal-card announcement-modal-card">
                            <button type="button" class="announcement-modal-close" id="officeAnnouncementModalClose" aria-label="Close">✕</button>
                            <div id="officeAnnouncementModalBody"></div>
                        </div>
                    </div>
                <?php endif; ?>

            </section>

            <section class="fb-cards">
                <h2>From Our Partner Pages</h2>

                <div class="fb-pages-grid">
                    <!-- BSU Research Services FB (Bsu Ors) -->
                    <section class="fb-page-section">
                        <div class="fb-page-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--green)" aria-hidden="true">
                                <path d="M24 12.073C24 5.406 18.627 0 12 0S0 5.406 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.27h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
                            </svg>
                            Office of the Vice President for Research and Extension - BSU
                        </div>
                        <div class="fb-embed-wrap">
                            <div class="fb-page"
                                data-href="https://www.facebook.com/bsuovpre"
                                data-tabs="timeline"
                                data-width=""
                                data-height="620"
                                data-small-header="false"
                                data-adapt-container-width="true"
                                data-hide-cover="false"
                                data-show-facepile="true">
                            </div>
                        </div>
                    </section>
                    <!-- BSU CCARD FB -->
                    <section class="fb-page-section">
                        <div class="fb-page-label">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--green)" aria-hidden="true">
                                <path d="M24 12.073C24 5.406 18.627 0 12 0S0 5.406 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.27h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
                            </svg>
                            BSU - Cordillera Center for Animal Research &amp; Development
                        </div>
                        <div class="fb-embed-wrap">
                            <div class="fb-page"
                                data-href="https://www.facebook.com/p/BSU-Cordillera-Center-for-Animal-Research-Development-100083273710247/"
                                data-tabs="timeline"
                                data-width=""
                                data-height="600"
                                data-small-header="false"
                                data-adapt-container-width="true"
                                data-hide-cover="false"
                                data-show-facepile="true">
                            </div>
                        </div>
                    </section>
                </div>
            </section>
        </div>

    </main>
</div>

<?php include "includes/footer.php"; ?>

<script>
    // See home.view.php for why this waits on DOMContentLoaded: modals.js
    // is deferred, so it isn't defined yet when this inline script runs.
    document.addEventListener('DOMContentLoaded', function() {
        initAnnouncementModal({
            modalId: 'officeAnnouncementModal',
            bodyId: 'officeAnnouncementModalBody',
            closeId: 'officeAnnouncementModalClose'
        });
    });
</script>