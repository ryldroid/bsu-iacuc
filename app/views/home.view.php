<?php

require_once __DIR__ . '/../models/AnnouncementModel.php';
$homeAnnouncementModel = new AnnouncementModel();
$homeLatestAnnouncements = array_slice($homeAnnouncementModel->getAll(), 0, 3);


$title = "Home";

$siteSettings    = $siteSettings ?? [];
$bannerTitle     = $siteSettings['banner_title'] ?? 'Benguet State University - Institutional Animal Care and Use Committee';
$aboutParagraph1 = $siteSettings['about_paragraph_1'] ?? '';
$aboutParagraph2 = $siteSettings['about_paragraph_2'] ?? '';

// Map card (CCARD office location)
$ccardMapQuery = 'BSU Cordillera Center for Animal Research and Development, CVM Compound, Km. 5, La Trinidad, Benguet';
$ccardMapEmbed = 'https://www.google.com/maps?q=' . rawurlencode($ccardMapQuery) . '&z=16&output=embed';
$ccardMapLink  = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($ccardMapQuery);


include "includes/header.php";
include "includes/scroll-top.php";
?>

<link rel="stylesheet" href="<?= asset_css('home.css') ?>">

<div class="body">
    <?php include "includes/navigation.php"; ?>

    <main class="main-content" id="main-content" tabindex="-1">
        <!-- BANNER -->
        <div class="banner">
            <div class="logos">
                <img src="<?= IMGPATH ?>/bsu.webp" alt="BSU logo">
                <img src="<?= IMGPATH ?>/ovpre.webp" alt="OVPRE logo">
                <img src="<?= IMGPATH ?>/ccard.webp" alt="CCARD logo">
                <img src="<?= IMGPATH ?>/bai.webp" alt="BAI logo">
            </div>

            <h1><?= htmlspecialchars($bannerTitle, ENT_QUOTES) ?></h1>
        </div>

        <div class="articles">
            <div>
                <!-- ABOUT -->
                <article class="iacuc-section">
                    <div class="home-iacuc">
                        <h2>What is IACUC?</h2>
                        <p>
                            <?= nl2br(htmlspecialchars($aboutParagraph1, ENT_QUOTES)) ?>
                        </p>
                        <p>
                            <?= nl2br(htmlspecialchars($aboutParagraph2, ENT_QUOTES)) ?>
                        </p>
                        <p>
                            The IACUC reviews and endorses animal research protocols in line with
                            <strong><a href="https://www.lawphil.net/statutes/repacts/ra1998/ra_8485_1998.html" class="underlined" target="_blank">Republic Act 8485</a></strong> (Animal Welfare Act of 1998) as amended by
                            <strong><a href="https://www.lawphil.net/statutes/repacts/ra2013/ra_10631_2013.html" class="underlined" target="_blank">Republic Act 10631</a></strong>, which strengthens protections for animals used in scientific
                            and research activities. All researchers engaging animals must secure IACUC clearance before
                            commencing any study.
                        </p>
                        <div id="apply-actions">
                            <a href="<?= ROOT ?>/apply" class="button btn-apply">Click to Apply for IACUC Protocol Review</a>
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
                        <details class="faq-cont">
                            <summary class="faq-question">
                                Who may avail?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                Students and researchers from BSU and other institutions within the Cordillera Administrative Region.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                What are the requirements?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                Researchers (or Principal Investigators) must have prior IACUC training in order to apply for protocol review.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                When working in groups, should each member apply for an IACUC protocol review?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                No, only the Principal Investigator (PI) may submit the IACUC protocol for the group.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                What kind of IACUC training is required?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                Everyone working with animals must receive lecture and laboratory animal handling training. Please refer to the <a href="<?= ROOT ?>/announcements" class="underlined">announcements</a> page or inquire at the CCARD office to be updated with the scheduled trainings.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                What type of experiments need IACUC review?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                IACUC review is needed for all work involving direct interaction with <span class="italic">live animals only</span>.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                Do I need an IACUC protocol to use dead animals or animal parts?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                If you are obtaining animals or tissue that were already dead (rat livers from another laboratory, steaks from the supermarket, tissues from a slaughterhouse) then you do not need an IACUC protocol. However, all work with wild mammal tissue need an approval from the Department of Environment and Natural Resources (DENR).
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                How long does it take to get an IACUC review?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                Protocols are reviewed as soon as protocols are submitted. However, it may take 1-8 weeks for IACUC review and the issuance of the animal research clearance by BAI.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                Can the investigator begin animal work before receiving IACUC review?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                No. The IACUC review shall be part of the thesis proposal when using live animals.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                How much do I pay for an IACUC Protocol Review?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                There is no fee for CCARD's IACUC review. However, BAI requires a payment of Php 100.00 for the Animal Research Clearance, to be paid upon submission of the reviewed IACUC protocol.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                What if I amend my IACUC protocol to add/change procedures / personnel / animals?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                All revision must be communicated with the IACUC through the portal. Please note that even the most <strong>minor</strong> changes <strong>must</strong> be revised and reviewed for approval.
                            </div>
                        </details>

                        <details class="faq-cont">
                            <summary class="faq-question">
                                Who do I contact if I have questions regarding the animal care and use program or the IACUC?
                                <span class="faq-icon" aria-hidden="true">
                                    <span class="faq-icon-line faq-icon-line--v"></span>
                                    <span class="faq-icon-line faq-icon-line--h"></span>
                                </span>
                            </summary>
                            <div class="faq-answer">
                                In BSU, you may visit the CCARD office. You may also refer to the <a href="<?= ROOT ?>/contact" class="underlined">contact</a> page for additional contact information.
                            </div>
                        </details>

                        <!-- <details class="faq-cont">
                        <summary class="faq-question">
                            Where do I get an IACUC protocol from?
                            <span class="faq-icon" aria-hidden="true">
                                <span class="faq-icon-line faq-icon-line--v"></span>
                                <span class="faq-icon-line faq-icon-line--h"></span>
                            </span>
                        </summary>
                        <div class="faq-answer">
                            The protocol form can be requested from CCARD office or you can personally ask for a soft copy to be emailed to you. Please ensure to always use a new copy every time you submit a protocol.
                        </div>
                    </details> -->
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
                <?php if (!empty($homeLatestAnnouncements)): ?>
                    <section class="home-announcements-teaser">
                        <div class="home-announcements-teaser-header">
                            <h2>Latest Announcements</h2>
                            <a href="<?= ROOT ?>/announcements" class="underlined see_all">See all ></a>
                        </div>
                        <div class="home-announcements-list">
                            <?php foreach ($homeLatestAnnouncements as $post):
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
                                    $annHeading = '';
                                    $annSnippet = $annBody;
                                } else {
                                    $annHeading = 'Photo update';
                                    $annSnippet = '';
                                }
                                $isUntitled = !$hasTitle && !$hasBody;
                                $annTimestamp = strtotime($post['created_at']);
                                $annDateDisplay = $annTimestamp ? date('M j, Y g:i A', $annTimestamp) : htmlspecialchars($post['created_at'], ENT_QUOTES);
                                $annDateIso = $annTimestamp ? date('c', $annTimestamp) : '';
                            ?>
                                <?php if ($isPhotoOnly): ?>
                                    <button type="button"
                                        class="home-announcement-cont home-announcement-cont--photo"
                                        data-ann-modal="annModalTpl-<?= (int) $post['id'] ?>"
                                        aria-haspopup="dialog">
                                        <span class="home-announcement-photo">
                                            <img src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) ?>" alt="">
                                            <time datetime="<?= htmlspecialchars($annDateIso, ENT_QUOTES) ?>"><?= htmlspecialchars($annDateDisplay, ENT_QUOTES) ?></time>
                                        </span>
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                        class="home-announcement-cont<?= $isUntitled ? ' is-untitled' : '' ?>"
                                        data-ann-modal="annModalTpl-<?= (int) $post['id'] ?>"
                                        aria-haspopup="dialog">
                                        <span class="home-announcement-thumb<?= $hasImage ? '' : ' is-placeholder' ?>">
                                            <?php if ($hasImage): ?>
                                                <img src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) ?>" alt="">
                                            <?php else: ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <use href="#announcement-icon" />
                                                </svg>
                                            <?php endif; ?>
                                        </span>
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

                                <template id="annModalTpl-<?= (int) $post['id'] ?>">
                                    <?php if ($hasImage): ?>
                                        <div class="announcement-modal-image-wrap">
                                            <img class="announcement-modal-image"
                                                src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) ?>" alt="">
                                            <button type="button" class="image-zoom-btn" title="Zoom image" aria-label="Zoom image"
                                                data-zoom-src="<?= ROOT . '/assets/uploads/announcements/' . rawurlencode($post['image_path']) ?>" data-zoom-alt="<?= htmlspecialchars($annHeading, ENT_QUOTES) ?>">
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
                                    <h3>Apply</h3>
                                    <p>Go to the <a href="<?= ROOT ?>/apply" class="underlined">application page</a>. Follow the steps to submit your IACUC protocol form.</p>
                                </div>
                            </li>

                            <li class="timeline-step">
                                <span class="timeline-icon" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <use href="#timeline-track-icon" />
                                    </svg>
                                </span>
                                <div class="timeline-content">
                                    <h3>Track</h3>
                                    <p>Track your protocol's status through the dashboard. View reviewer comments and resubmit for as long as revisions are requested. You will be notified of every update through email.</p>
                                </div>
                            </li>

                            <li class="timeline-step">
                                <span class="timeline-icon" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <use href="#timeline-endorse-icon" />
                                    </svg>
                                </span>
                                <div class="timeline-content">
                                    <h3>Endorse and pay</h3>
                                    <p>Once the protocol has passed CCARD review, it will be endorsed to the Bureau of Animal Industry (BAI).</p>
                                    <p class="process-step-note">
                                        <span class="italic">Note:</span> BAI requires an animal research clearance processing fee of <strong>Php 100.00</strong>. Visit the BSU-CCARD office or <a href="<?= ROOT ?>/contact#director-contact" class="underlined">contact the CCARD Director</a> to process your payment.
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
                                    <h3>Receive clearance</h3>
                                    <p>Wait for your clearance to be released through your dashboard. You will also be notified through email.</p>
                                </div>
                            </li>
                        </ol>
                    </article>

                    <div class="modal-backdrop" id="homeAnnouncementModal" role="dialog" aria-modal="true">
                        <div class="modal-card announcement-modal-card">
                            <button type="button" class="announcement-modal-close" id="homeAnnouncementModalClose" aria-label="Close">✕</button>
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
        const isLoggedIn = <?= isset($_SESSION['user']['user_id']) ? 'true' : 'false' ?>;
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

    // modals.js is loaded with `defer`, so it only runs once the whole
    // document has been parsed. This inline script runs immediately, before
    // that happens, so calling initAnnouncementModal directly here throws
    // (function not defined yet) and the modal never gets wired up. Waiting
    // for DOMContentLoaded guarantees modals.js has already executed.
    document.addEventListener('DOMContentLoaded', function() {
        initAnnouncementModal({
            modalId: 'homeAnnouncementModal',
            bodyId: 'homeAnnouncementModalBody',
            closeId: 'homeAnnouncementModalClose'
        });
    });
</script>

<?php include "includes/footer.php"; ?>