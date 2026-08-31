<?php
$title = 'Announcements';

include "includes/header.php";
include "includes/scroll-top.php";

// [ADDED SPM - pull admin-managed "From Our Office" posts.
require_once dirname(__DIR__) . '/models/AnnouncementModel.php';
$announcementModel = new AnnouncementModel();
$officeAnnouncements = $announcementModel->getAll();
// END ADDED
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
                <p class="announcements-subtitle">Latest updates from BSU-CCARD.</p>

                <!-- ADDED SPM - renders posts managed by admin -->
                <?php if (empty($officeAnnouncements)): ?>
                    <p class="announcements-empty">No announcements yet. Check back soon.</p>
                <?php else: ?>
                    <div class="office-announcements-list">
                        <?php foreach ($officeAnnouncements as $post): ?>
                            <article class="office-announcement-card">
                                <h3 class="office-announcement-title"><?= htmlspecialchars($post['title'], ENT_QUOTES) ?></h3>
                                <time class="office-announcement-date"><?= htmlspecialchars($post['created_at'], ENT_QUOTES) ?></time>
                                <p class="office-announcement-body"><?= nl2br(htmlspecialchars($post['body'], ENT_QUOTES)) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <!-- END ADDED -->
            </section>

            <section class="fb-cards">
                <h2>From Our Partner Pages</h2>
                <p class="announcements-subtitle">Instant access to related Facebook pages.</p>

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