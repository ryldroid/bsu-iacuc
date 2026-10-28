<?php

$previewMode = $previewMode ?? false;
$pubRoot     = $previewMode ? ROOT . '/preview' : ROOT;
$loginLink    = $previewMode ? '#' : ROOT . '/user/login';
$registerLink = $previewMode ? '#' : ROOT . '/user/register';
$applyLink    = $previewMode ? '#' : ROOT . '/apply';
$disabledLink = $previewMode ? ' aria-disabled="true" onclick="return false;"' : '';

$siteSettings    = $siteSettings ?? [];
$bannerTitle     = $siteSettings['banner_title'] ?? 'Benguet State University - Institutional Animal Care and Use Committee';
$aboutText       = $siteSettings['about_text'] ?? '';

$ccardMapEmbed = 'https://www.google.com/maps?cid=10697978370393166203&z=18&output=embed';
$ccardMapLink  = 'https://www.google.com/maps/place/Cordillera+Center+for+Animal+Research+and+Development+(CCARD)/data=!4m2!3m1!1s0x0:0x9476daa5c618d17b';


include "includes/header.php";
include "includes/scroll-top.php";
?>

<link rel="stylesheet" href="<?= asset_css('home.css') ?>">

<div class="body">
    <?php include "includes/navigation.php"; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <!-- BANNER -->
        <div class="banner page-banner">
            <div class="logos">
                <img src="<?= IMGPATH ?>/bsu.webp" alt="BSU logo">
                <img src="<?= IMGPATH ?>/ovpre.webp" alt="OVPRE logo">
                <img src="<?= IMGPATH ?>/ccard.webp" alt="CCARD logo">
                <!-- <img src="<?= IMGPATH ?>/bai.webp" alt="BAI logo"> -->
            </div>

            <h1><?= htmlspecialchars($bannerTitle, ENT_QUOTES) ?></h1>
        </div>

        <div class="articles">
            <div>
                <!-- ABOUT -->
                <article class="iacuc-section">
                    <div class="home-iacuc">
                        <h2>What is IACUC?</h2>
                        <?php foreach (preg_split('/\R+/', $aboutText, -1, PREG_SPLIT_NO_EMPTY) as $aboutLine): ?>
                            <p>
                                <?= render_text_with_links($aboutLine) ?>
                            </p>
                        <?php endforeach; ?>
                        <div id="apply-actions">
                            <a href="<?= $applyLink ?>" class="button btn-apply" <?= $disabledLink ?>>Click to Apply for IACUC Protocol Review</a>
                        </div>
                    </div>
                </article>



                <!-- FAQ -->
                <article class="faq-section">
                    <div class="faq-header">
                        <h2 class="faq-title">Frequently Asked Questions</h2>
                        <button type="button" class="faq-toggle-all" id="faqToggleAll">
                            + expand all
                        </button>
                    </div>

                    <div class="faq-list">
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $faqItem): ?>
                                <details class="faq-cont">
                                    <summary class="faq-question">
                                        <?= htmlspecialchars($faqItem['question'], ENT_QUOTES) ?>
                                        <span class="faq-icon" aria-hidden="true">
                                            <span class="faq-icon-line faq-icon-line--v"></span>
                                            <span class="faq-icon-line faq-icon-line--h"></span>
                                        </span>
                                    </summary>
                                    <div class="faq-answer">
                                        <?= nl2br(htmlspecialchars($faqItem['answer'], ENT_QUOTES)) ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="faq-empty">No FAQs have been added yet.</p>
                        <?php endif; ?>
                    </div>
                </article>

                <!-- LOCATION MAP -->
                <article class="map-section">
                    <div class="map-header">
                        <h2>Where to Find Us</h2>
                        <a href="<?= htmlspecialchars($ccardMapLink, ENT_QUOTES) ?>"
                            class="underlined see_all"
                            target="_blank"
                            rel="noopener noreferrer">Open in Google Maps ></a>
                    </div>

                    <!-- ===== Map card ===== -->
                    <div class="map-card">
                        <div class="map-embed">
                            <iframe
                                src="<?= htmlspecialchars($ccardMapEmbed, ENT_QUOTES) ?>"
                                title="Google map showing the BSU-CCARD office in La Trinidad, Benguet"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen></iframe>
                        </div>

                        <div class="map-details">
                            <svg class="row-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#location-icon" />
                            </svg>
                            <div>
                                <p class="map-place">BSU-CCARD Office</p>
                                <p class="map-address">CCARD Bldg., CVM Compound, Km. 5, La Trinidad, Benguet, 2601 Philippines</p>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="faq-column">
                <!-- ===== Latest announcements teaser ===== -->
                <section class="home-announcements-teaser">
                    <div class="home-announcements-teaser-header">
                        <h2>Latest Announcements</h2>
                        <a href="<?= $pubRoot ?>/announcements" class="underlined see_all">See all ></a>
                    </div>
                    <?php if (empty($latestAnnouncements)): ?>
                        <p class="announcements-empty">No announcements yet. Check back soon.</p>
                    <?php else: ?>
                        <div class="home-announcements-list">
                            <?php foreach ($latestAnnouncements as $post):
                                $annTitle = normalize_pasted_text(trim($post['title'] ?? ''));
                                $annBody = normalize_pasted_text(trim($post['body'] ?? ''));
                                $hasImage = !empty($post['image_path']);
                                $annImgUrl = $hasImage ? ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) : '';
                                $hasTitle = $annTitle !== '';
                                $hasBody = $annBody !== '';
                                $isPhotoOnly = $hasImage && !$hasTitle && !$hasBody;

                                if ($hasTitle) {
                                    $annHeading = $annTitle;
                                    $annSnippet = $hasBody ? $annBody : '';
                                } elseif ($hasBody) {
                                    $annHeading = '';
                                    $annSnippet = $annBody;
                                } else {
                                    $annHeading = 'Photo update';
                                    $annSnippet = '';
                                }
                                $isUntitled = !$hasTitle && !$hasBody;
                                $annTimestamp = strtotime($post['created_at']);
                                $annDateDisplay = $annTimestamp ? date(DATETIME_FORMAT, $annTimestamp) : htmlspecialchars($post['created_at'], ENT_QUOTES);
                                $annDateIso = $annTimestamp ? date('c', $annTimestamp) : '';
                                $isNew = $annTimestamp && $annTimestamp >= strtotime('-' . ANNOUNCEMENT_NEW_DAYS . ' days');
                            ?>
                                <?php if ($isPhotoOnly): ?>
                                    <button type="button"
                                        class="home-announcement-cont home-announcement-cont--photo<?= $isNew ? ' is-new' : '' ?>"
                                        data-ann-modal="annModalTpl-<?= (int) $post['id'] ?>"
                                        aria-haspopup="dialog">
                                        <span class="home-announcement-photo">
                                            <img src="<?= $annImgUrl ?>" alt="<?= htmlspecialchars($annHeading, ENT_QUOTES) ?>">
                                            <time datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                            <?php if ($isNew): ?><span class="new-badge">New</span><?php endif; ?>
                                        </span>
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                        class="home-announcement-cont<?= $isUntitled ? ' is-untitled' : '' ?><?= $isNew ? ' is-new' : '' ?>"
                                        data-ann-modal="annModalTpl-<?= (int) $post['id'] ?>"
                                        aria-haspopup="dialog">
                                        <span class="home-announcement-thumb<?= $hasImage ? '' : ' is-placeholder' ?>">
                                            <?php if ($hasImage): ?>
                                                <img src="<?= $annImgUrl ?>" alt="">
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <use href="#announcement-icon" />
                                                </svg>
                                            <?php endif; ?>
                                        </span>
                                        <?php if ($isNew): ?><span class="new-badge">New</span><?php endif; ?>
                                        <span class="home-announcement-text">
                                            <span class="home-announcement-top-row">
                                                <span class="home-announcement-teaser-title"><?= htmlspecialchars($annHeading, ENT_QUOTES) ?></span>
                                                <time datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                            </span>
                                            <?php if ($annSnippet !== ''): ?>
                                                <span class="home-announcement-snippet<?= $hasTitle ? '' : ' home-announcement-snippet--primary' ?>"><?= htmlspecialchars($annSnippet, ENT_QUOTES) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </button>
                                <?php endif; ?>

                                <!-- ===== Announcement modal templates ===== -->
                                <template id="annModalTpl-<?= (int) $post['id'] ?>">
                                    <?php if ($hasImage): ?>
                                        <?php include "includes/announcement-modal-image.php"; ?>
                                    <?php endif; ?>
                                    <?php if ($hasTitle): ?>
                                        <h2 class="announcement-modal-title"><?= htmlspecialchars($annHeading, ENT_QUOTES) ?></h2>
                                    <?php endif; ?>
                                    <time class="announcement-modal-date" datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                    <?php if ($hasBody): ?>
                                        <p class="announcement-modal-text"><?= htmlspecialchars($annBody, ENT_QUOTES) ?></p>
                                    <?php endif; ?>
                                </template>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- PROCESS -->
                <article class="process-section">
                    <h2>How to Get Your Animal Research Clearance?</h2>

                    <ol class="timeline">
                        <li class="timeline-step">
                            <span class="timeline-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <use href="#timeline-apply-icon" />
                                </svg>
                            </span>
                            <div class="timeline-content">
                                <h3>Apply for Review</h3>
                                <p>
                                    <a href="<?= $registerLink ?>" class="underlined" <?= $disabledLink ?>>Create an account</a>
                                    or
                                    <a href="<?= $loginLink ?>" class="underlined" <?= $disabledLink ?>>sign in</a>.
                                    View the
                                    <a href="<?= $applyLink ?>" class="underlined" <?= $disabledLink ?>>application form</a>
                                    and follow the steps to submit your IACUC protocol form.
                                </p>
                            </div>
                        </li>

                        <li class="timeline-step">
                            <span class="timeline-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <use href="#timeline-track-icon" />
                                </svg>
                            </span>
                            <div class="timeline-content">
                                <h3>Track Protocol</h3>
                                <p>Track your protocol's status through your dashboard. As long as there are comments or requested revisions on your protocol, revise and resubmit. You will be notified of every update via your verified email.</p>
                            </div>
                        </li>

                        <li class="timeline-step">
                            <span class="timeline-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <use href="#timeline-endorse-icon" />
                                </svg>
                            </span>
                            <div class="timeline-content">
                                <h3>Pay BAI's Processing Fee</h3>
                                <p>Once the protocol has passed CCARD review, it will be endorsed to the Bureau of Animal Industry (BAI).</p>
                                <p class="process-step-note">
                                    <span class="italic">Note:</span> BAI requires an animal research clearance processing fee of <strong>Php 100.00</strong>. If within BSU-La Trinidad Campus, submit your payment to the CCARD office. Otherwise, you may <a href="<?= ROOT ?>/contact#director-contact" class="underlined">contact the CCARD Director</a> to process your payment.
                                </p>
                            </div>
                        </li>

                        <li class="timeline-step">
                            <span class="timeline-icon" aria-hidden="true">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <use href="#timeline-receive-icon" />
                                </svg>
                            </span>
                            <div class="timeline-content">
                                <h3>Receive Clearance</h3>
                                <p>When your protocol is approved, your digital animal research clearance (ARC) will be released through your dashboard.</p>
                                <p class="process-step-note">
                                    Visit the CCARD office to claim the embossed hard copy of your ARC.
                                </p>
                            </div>
                        </li>
                    </ol>
                </article>

                <?php if (!empty($latestAnnouncements)): ?>
                    <div class="modal-backdrop" id="homeAnnouncementModal" role="dialog" aria-modal="true">
                        <div class="modal-card announcement-modal-card">
                            <button type="button" class="modal-close" id="homeAnnouncementModalClose" aria-label="Close">
                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <use href="#close-icon" />
                                </svg>
                            </button>
                            <div id="homeAnnouncementModalBody"></div>
                        </div>
                    </div>
                <?php endif; ?>


            </div>
        </div>
    </main>
</div>

<script>
    // ===== SMOOTH FAQ =====
    const faqItems = document.querySelectorAll('.faq-list .faq-cont');
    const faqToggleAllBtn = document.getElementById('faqToggleAll');

    function openFaq(details) {
        const content = details.querySelector('.faq-answer');
        details.setAttribute('open', '');
        details.classList.add('is-open');
        const targetHeight = content.scrollHeight;
        content.style.height = '0';
        content.style.paddingTop = '0';
        content.style.paddingBottom = '0';
        getComputedStyle(content).height;
        content.style.transition = 'height 0.25s ease, padding 0.25s ease';
        content.style.height = targetHeight + 'px';
        content.style.paddingTop = '13px';
        content.style.paddingBottom = '15px';
        content.addEventListener('transitionend', () => {
            content.style.cssText = '';
        }, {
            once: true
        });
    }

    function closeFaq(details) {
        const content = details.querySelector('.faq-answer');
        details.classList.remove('is-open');
        const startHeight = content.offsetHeight;
        content.style.height = startHeight + 'px';
        getComputedStyle(content).height;
        content.style.transition = 'height 0.25s ease, padding 0.25s ease';
        content.style.height = '0';
        content.style.paddingTop = '0';
        content.style.paddingBottom = '0';
        content.addEventListener('transitionend', () => {
            details.removeAttribute('open');
            content.style.cssText = '';
        }, {
            once: true
        });
    }

    function updateToggleAllLabel() {
        if (!faqToggleAllBtn) return;
        const allOpen = Array.from(faqItems).every(details => details.classList.contains('is-open'));
        faqToggleAllBtn.textContent = allOpen ? '- collapse all' : '+ expand all';
    }

    faqItems.forEach(details => {
        const summary = details.querySelector('.faq-question');

        summary.addEventListener('click', e => {
            e.preventDefault();

            if (details.open) {
                closeFaq(details);
            } else {
                openFaq(details);
            }

            updateToggleAllLabel();
        });
    });

    if (faqToggleAllBtn) {
        faqToggleAllBtn.addEventListener('click', () => {
            const allOpen = Array.from(faqItems).every(details => details.classList.contains('is-open'));

            faqItems.forEach(details => {
                if (allOpen) {
                    closeFaq(details);
                } else if (!details.classList.contains('is-open')) {
                    openFaq(details);
                }
            });

            updateToggleAllLabel();
        });
    }
    // ===== APPLY BUTTON:  Continue vs New =====
    (function() {
        const isLoggedIn = <?= !$previewMode && isset($_SESSION['user']['user_id']) ? 'true' : 'false' ?>;
        const ROOT_URL = '<?= ROOT ?>';
        const applyUrl = ROOT_URL + '/apply';
        const container = document.getElementById('apply-actions');
        if (!container) return;

        if (!isLoggedIn) return;

        fetch(ROOT_URL + '/apply/draft')
            .then(r => r.json())
            .then(d => {
                const inProgress = d.exists && (
                    d.step > 0 ||
                    d.agreedTerms ||
                    d.agreedPrivacy ||
                    d.title ||
                    d.cert ||
                    d.auth ||
                    d.protocol
                );

                if (!inProgress) {
                    container.innerHTML =
                        `<a href="${applyUrl}" class="button btn-apply">Click to Apply for IACUC Protocol Review</a>`;
                    return;
                }

                container.innerHTML = `<a href="${applyUrl}" class="button btn-apply">Continue Application →</a>`;
            })
            .catch(() => {
                container.innerHTML =
                    `<a href="${applyUrl}" class="button btn-apply">Click to Apply for IACUC Protocol Review</a>`;
            });
    })();

    document.addEventListener('DOMContentLoaded', function() {
        initAnnouncementModal({
            modalId: 'homeAnnouncementModal',
            bodyId: 'homeAnnouncementModalBody',
            closeId: 'homeAnnouncementModalClose'
        });
    });
</script>

<?php include "includes/footer.php"; ?>