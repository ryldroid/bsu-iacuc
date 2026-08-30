<?php

$title = "Home";

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

            <h1>Benguet State University - Institutional Animal Care and Use Committee</h1>
        </div>

        <div class="articles">
            <div>
                <!-- ABOUT -->
                <article>
                    <div class="home-iacuc">
                        <h2>What is IACUC?</h2>
                        <p>
                            The Institutional Animal Care and Use Committee (IACUC) is mandated with the responsibility for ensuring adherence to appropriate University and National and International policies and regulations. The IACUC, under the Office of the Research and Extension (R and E) specifically the Cordillera Center for Animal Research and Development (CCARD), serves as the oversight committee in the care and use of live animals in research and teaching activities in Benguet State University (BSU).
                        </p>
                        <p>
                            Animal Use Protocols must be reviewed by the IACUC and endorse for issuance of Animal Research permit by the Bureau of Animal Industry (BAI).
                        </p>
                        <p>
                            The IACUC reviews and endorses animal research protocols in line with
                            <strong>Republic Act 8485</strong> (Animal Welfare Act of 1998) as amended by
                            <strong>Republic Act 10631</strong>, which strengthens protections for animals used in scientific
                            and research activities. All researchers engaging animals must secure IACUC clearance before
                            commencing any study.
                        </p>
                        <div id="apply-actions">
                            <a href="<?= ROOT ?>/apply" class="button btn-apply">Click to Apply for IACUC Protocol Review</a>
                        </div>
                    </div>
                </article>

                <!-- PROCESS -->
                <article class="process-section">
                    <h2>How to Get Your Animal Research Clearance?</h2>

                    <div class="steps-cont">
                        <div class="process-step">
                            <span>1</span>
                            <p>Go to the <a href="<?= ROOT ?>/apply" class="underlined">application page</a>. Follow the steps to submit your IACUC protocol form.</p>
                        </div>

                        <div class="process-step">
                            <span>2</span>
                            <p>Track your protocol's status through the dashboard. View reviewer comments and resubmit for as long as revisions are requested. You will be notified of every update through email.</p>
                        </div>

                        <div class="process-step">
                            <span>3</span>
                            <div>
                                <p>
                                    Once the protocol has passed CCARD review, it will be endorsed to the Bureau of Animal Industry (BAI).
                                </p>
                                <p class="process-step-note">
                                    <span class="italic">Note:</span> BAI requires an animal research clearance processing fee of <strong>Php 100.00</strong>. Visit the BSU-CCARD office or <a href="<?= ROOT ?>/contact#director-contact" class="underlined">contact the CCARD Director</a> to process your payment.
                                </p>
                            </div>
                        </div>

                        <div class="process-step">
                            <span>4</span>
                            <p>Wait for your clearance to be released through your dashboard. You will also be notified through email.</p>
                        </div>
                    </div>
                </article>
            </div>

            <!-- FAQ -->
            <article class="faq-section">
                <h2 class="faq-title">Frequently Asked Questions</h2>
                <div class="faq-list">
                    <details class="faq-cont">
                        <summary class="faq-question">
                            Who may avail?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            Students and researchers from BSU and other institutions within the Cordillera Administrative Region.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            What are the requirements?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            Researchers (or Principal Investigators) must have prior IACUC training in order to apply for protocol review.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            What kind of IACUC training is required?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            Everyone working with animals must receive lecture and laboratory animal handling training. Please refer to the <a href="<?= ROOT ?>/announcements" class="underlined">announcements</a> page or inquire at the CCARD office to be updated with the scheduled trainings.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            What type of experiments need IACUC review?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            IACUC review is needed for all work involving direct interaction with <span class="italic">live animals only</span>.
                        </div>
                    </details>
                    <details class="faq-cont">
                        <summary class="faq-question">
                            Do I need an IACUC protocol to use dead animals or animal parts?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            If you are obtaining animals or tissue that were already dead (rat livers from another laboratory, steaks from the supermarket, tissues from a slaughterhouse) then you do not need an IACUC protocol. However, all work with wild mammal tissue need an approval from the Department of Environment and Natural Resources (DENR).
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            How long does it take to get an IACUC review?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            Protocols are reviewed as soon as protocols are submitted. However, it may take 1-8 weeks for IACUC review and the issuance of the animal research clearance by BAI.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            Can the investigator begin animal work before receiving IACUC review?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            No. The IACUC review shall be part of the thesis proposal when using live animals.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            How much do I pay for an IACUC Protocol Review?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            There is no fee for CCARD's IACUC review. However, BAI requires a payment of Php 100.00 for the Animal Research Permit, to be paid upon submission of the reviewed IACUC protocol.
                        </div>
                    </details>

                    <details class="faq-cont">
                        <summary class="faq-question">
                            Can I let someone else fill out my Animal Use Protocol?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            We encourage Principal Investigators (PIs) to write and submit each IACUC protocol form; however, a staff member may fill out a protocol form with the permission of the PI. The individual listed as PI must sign the assurance form indicating full responsibility for the protocol. The assurance form is downloadable in the application process.
                        </div>
                    </details>
                    <details class="faq-cont">
                        <summary class="faq-question">
                            What if I amend my IACUC protocol to add/change procedures / personnel / animals?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            All revision must be communicated with the IACUC in writing. Please note that even the most <strong>minor</strong> changes <strong>must</strong> be revised and reviewed for approval.
                        </div>
                    </details>
                    <details class="faq-cont">
                        <summary class="faq-question">
                            Who do I contact if I have questions regarding the animal care and use program or the IACUC?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            In BSU, you may visit the CCARD office. You may also refer to the <a href="<?= ROOT ?>/contact" class="underlined">contact</a> page for additional contact information.
                        </div>
                    </details>

                    <!-- <details class="faq-cont">
                        <summary class="faq-question">
                            Where do I get an IACUC protocol from?
                            <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <use href="#chev-down-icon" />
                            </svg>
                        </summary>
                        <div class="faq-answer">
                            The protocol form can be requested from CCARD office or you can personally ask for a soft copy to be emailed to you. Please ensure to always use a new copy every time you submit a protocol.
                        </div>
                    </details> -->
                </div>
            </article>
        </div>
    </main>
</div>

<script>
    // ===== SMOOTH FAQ =====
    document.querySelectorAll('.faq-cont').forEach(details => {
        const summary = details.querySelector('.faq-question');
        const content = details.querySelector('.faq-answer');

        summary.addEventListener('click', e => {
            e.preventDefault();

            if (details.open) {
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
            } else {
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
        });
    });
    // ===== APPLY BUTTON:  Continue vs New =====
    (function() {
        const isLoggedIn = <?= isset($_SESSION['user']['user_id']) ? 'true' : 'false' ?>;
        const SAVE_KEY = 'bsu_iacuc_apply_v2_u<?= (int) ($_SESSION['user']['user_id'] ?? 0) ?>';
        const ROOT_URL = '<?= ROOT ?>';
        const applyUrl = ROOT_URL + '/apply';
        const container = document.getElementById('apply-actions');
        if (!container) return;

        if (!isLoggedIn) return;
        let saved = null;
        try {
            const raw = localStorage.getItem(SAVE_KEY);
            if (raw) saved = JSON.parse(raw);
        } catch (e) {}

        const inProgress = saved && !saved.submittedId && (
            saved.step > 0 ||
            saved.agreedTerms ||
            saved.agreedPrivacy ||
            saved.title ||
            saved.certName ||
            saved.authName ||
            saved.protocolName
        );

        if (!inProgress) {
            container.innerHTML =
                `<a href="${applyUrl}" class="button btn-apply">Click to Apply for IACUC Protocol Review</a>`;
            return;
        }

        container.innerHTML = `<a href="${applyUrl}" class="button btn-apply">Continue Application →</a>`;
    })();
</script>

<?php include "includes/footer.php"; ?>