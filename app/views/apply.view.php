<?php

/** @var array|null $user */
$title = "Submit Protocol";

include "includes/header.php";
include "includes/scroll-top.php";
?>

<link rel="stylesheet" href="<?= asset_css('application.css') ?>">

<div class="body">
    <main class="main-content" id="main-content" tabindex="-1">

        <a class="button btn-back" id="btn-home" href="<?= ROOT ?>/submissions" onclick="askLeave(event, '<?= ROOT ?>/submissions')">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Home
        </a>

        <div class="portal">
            <div class="portal-header">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#beaker-icon" />
                </svg>
                <h1>IACUC Application Portal</h1>
                <?php $pageGuideId = 'apply';
                include 'includes/page-guide.php'; ?>
            </div>

            <div class="stepper" id="stepper"></div>

            <div class="card" id="page-content"></div>
        </div>

    </main>
</div>

<?php
$guideId    = 'apply';
$guideSteps = [
    ['Application', 'Applying for protocol review takes a few short steps. Let us walk through this page.'],
    ['Progress', 'Each dot is a step: Requirements, Terms, Documents, Download form, Upload and submit, and Done. Finished steps show a check mark.', '#stepper'],
    ['Current step', 'The step you are on appears here. Read it, finish what it asks, then press **Continue**. Press **Back** to return to an earlier step.', '#page-content'],
    ['Leave and resume', 'Press **Home** to go back to My Protocols. Your progress is saved, and you can resume the application from there anytime.', '#btn-home'],
];
include 'includes/guide.php';
?>

<?php include "includes/file-popup.php"; ?>
<?php include "includes/animal-type-options.php"; ?>
<?php include "includes/person-options.php"; ?>
<script src="<?= asset_js('file-popup.js') ?>"></script>

<script>
    // ===== CONSTANTS =====
    const ROOT = '<?= ROOT ?>';
    const STEPS = ['Requirements', 'Terms', 'Documents', 'Download form', 'Upload and submit', 'Done'];
    const DRAFT_CSRF = NOTIF_CSRF_TOKEN;
    const PREVIOUS_DETAILS = <?= json_encode($previousDetails ?? []) ?>;

    // ===== STATE =====
    let state = {
        step: 0,
        agreedTerms: false,
        agreedPrivacy: false,
        certAlready: false,
        certName: null,
        certSize: null,
        protocolName: null,
        protocolSize: null,
        protocolConverted: false,
        title: '',
        animalType: '',
        animalCount: '',
        researchAdviser: '',
        veterinarian: '',
        submittedId: null,
    };

    let uploadProgress = {};

    // ===== DRAFT SYNC =====
    let _saveTimer = null;

    function saveState() {
        clearTimeout(_saveTimer);
        _saveTimer = setTimeout(pushDraftFields, 500);
    }

    async function pushDraftFields() {
        try {
            await fetch(ROOT + '/apply/draftsave', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': DRAFT_CSRF
                },
                body: JSON.stringify({
                    step: state.step,
                    agreedTerms: state.agreedTerms,
                    agreedPrivacy: state.agreedPrivacy,
                    title: state.title,
                    animalType: state.animalType,
                    animalCount: state.animalCount,
                    researchAdviser: state.researchAdviser,
                    veterinarian: state.veterinarian
                })
            });
        } catch (e) {}
    }

    async function loadDraft() {
        try {
            const res = await fetch(ROOT + '/apply/draft');
            const d = await res.json();
            if (!d.exists) return;

            state.step = d.step;
            state.agreedTerms = d.agreedTerms;
            state.agreedPrivacy = d.agreedPrivacy;
            state.title = d.title;
            state.animalType = d.animalType;
            state.animalCount = d.animalCount;
            state.researchAdviser = d.researchAdviser;
            state.veterinarian = d.veterinarian;

            ['protocol', 'cert'].forEach(key => {
                const f = d[key];
                state[key + 'Name'] = f ? f.name : null;
                state[key + 'Size'] = f ? f.size : null;
            });
            state.protocolConverted = !!(d.protocol && d.protocol.converted);
        } catch (e) {}
    }


    // ===== NAVIGATION =====
    function goTo(n) {
        state.step = Math.max(0, Math.min(STEPS.length - 1, n));
        saveState();
        render();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    // ===== LEAVE GUARD =====
    let _guardArmed = false;

    function _beforeUnloadHandler(e) {
        e.preventDefault();
        return (e.returnValue = '');
    }

    function armGuard() {
        if (_guardArmed) return;
        _guardArmed = true;
        window.addEventListener('beforeunload', _beforeUnloadHandler);
        document.addEventListener('click', _interceptClicks, true);
    }

    function disarmGuard() {
        if (!_guardArmed) return;
        _guardArmed = false;
        window.removeEventListener('beforeunload', _beforeUnloadHandler);
        document.removeEventListener('click', _interceptClicks, true);
    }

    function _interceptClicks(e) {
        const anchor = e.target.closest('a[href]');
        if (!anchor) return;

        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
        if (anchor.hasAttribute('download')) return;
        if (anchor.id === 'btn-home') return;

        e.preventDefault();
        e.stopImmediatePropagation();
        askLeave(e, href);
    }

    // ===== LEAVE DIALOG =====
    let _leaveHref = ROOT + '/submissions';

    async function askLeave(e, overrideHref) {
        if (e && typeof e.preventDefault === 'function') e.preventDefault();
        const destination = overrideHref || _leaveHref;
        clearTimeout(_saveTimer);
        await pushDraftFields();
        confirmAction(
            'Leave the form? Your progress has been saved.', {
                okText: 'Leave',
                cancelText: 'Stay'
            }
        ).then((ok) => {
            if (ok) {
                disarmGuard();
                window.location.href = destination;
            }
        });
    }

    // ===== STEPPER =====
    function renderStepper() {
        const el = document.getElementById('stepper');
        const checkSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>`;
        let html = '';
        STEPS.forEach((label, i) => {
            const cls = i < state.step ? 'done' : i === state.step ? 'active' : '';
            const inner = i < state.step ? checkSvg : (i + 1);
            html += `<div class="step-dot ${cls}" title="${label}">${inner}</div>`;
            if (i < STEPS.length - 1)
                html += `<div class="step-line ${i < state.step ? 'done' : ''}"></div>`;
        });
        el.innerHTML = html;
    }

    // ===== HELPERS =====
    const esc = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    function formatFileSize(bytes) {
        if (bytes === null || bytes === undefined) return '';
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    // ===== TERMS / PRIVACY MODAL =====
    function openDocModal(e, id) {
        if (e) e.preventDefault();
        const el = document.getElementById(id);
        if (el) el.classList.add('open');
    }

    function closeDocModal(id) {
        const el = document.getElementById(id);
        if (el) el.classList.remove('open');
    }

    document.addEventListener('click', e => {
        if (e.target.classList && e.target.classList.contains('modal-backdrop') && e.target.classList.contains('open')) {
            e.target.classList.remove('open');
        }
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-backdrop.open').forEach(el => el.classList.remove('open'));
        }
    });

    // ===== Upload box & document row builders =====
    function uploadBox(key, label, subtitle, required = false) {
        if (uploadProgress[key]) {
            return `<div class="info-bar upload-in-progress">
            <span class="file-badge-name">Uploading&hellip;</span>
            <div class="upload-progress-container" id="upload-progress-${key}"></div>
        </div>`;
        }
        const name = state[key + 'Name'];
        if (name) {
            const size = formatFileSize(state[key + 'Size']);
            return `<div class="info-bar">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#check-circle-icon" />
            </svg>
            <span class="file-badge-name">${esc(name)}${size ? ` <span class="file-badge-size">(${size})</span>` : ''}</span>
            <button class="file-badge-remove" onclick="removeFile('${key}')" title="Remove">
                <svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#close-icon" />
                </svg>
            </button>
        </div>`;
        }
        const accept = key === 'protocol' ? '.pdf,.doc,.docx' : '.pdf,.jpg,.jpeg,.png';
        return `<label class="upload-box">
        <input type="file" accept="${accept}" onchange="handleUpload(event,'${key}')">
        <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#upload-icon" />
        </svg>
        <div class="upload-label">${label}${required ? ' <span class="req">*</span>' : ''}</div>
        <div class="upload-sub">${subtitle}</div>
    </label>`;
    }

    function docRow(key, opts) {
        const name = state[key + 'Name'];
        const accept = key === 'protocol' ? '.pdf,.doc,.docx' : '.pdf,.jpg,.jpeg,.png';
        const checkSvg = `<svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#check-circle-icon" />
        </svg>`;

        let subLine, action;

        if (uploadProgress[key]) {
            subLine = `<span class="doc-row-sub">Uploading&hellip;</span>
            <div class="upload-progress-container" id="upload-progress-${key}"></div>`;
            action = '';
        } else if (opts.alreadyOnFile) {
            subLine = `<span class="doc-row-sub done">${opts.alreadyNote}</span>`;
            action = `<div class="doc-row-done">${checkSvg}<span>Submitted</span></div>`;
        } else if (name) {
            const size = formatFileSize(state[key + 'Size']);
            subLine = `<span class="doc-row-sub done">${esc(name)}${size ? ` · ${size}` : ''}</span>`;
            action = `<div class="doc-row-done">
            ${checkSvg}
            <label class="doc-row-replace">
                Replace
                <input type="file" accept="${accept}" onchange="handleUpload(event,'${key}')">
            </label>
        </div>`;
        } else {
            subLine = `<span class="doc-row-sub">${opts.subtitle}</span>`;
            action = `<label class="btn-upload-inline">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#upload-icon" />
            </svg>
            Upload
            <input type="file" accept="${accept}" onchange="handleUpload(event,'${key}')">
        </label>`;
        }

        return `<div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">${opts.title}${opts.required ? ' <span class="req">*</span>' : ''}</div>
            ${subLine}
        </div>
        <div class="doc-row-action">${action}</div>
    </div>`;
    }

    const uploadWarningNotice = `
    <div class="notice notice-warning">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#alert-triangle-icon" />
        </svg>
        <span>Make sure your uploads are legible, accurate, and complete before submitting.</span>
    </div>`;

    function convertedNotice() {
        return `
    <div class="notice notice-info">
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#info-icon" />
        </svg>
        <span><strong>Converted to PDF:</strong> Word files are converted to PDF for submission. Please review the converted file, since conversion can shift the layout slightly. <a href="#" class="underlined" onclick="previewDraftFile(event,'protocol')">Preview PDF</a></span>
    </div>`;
    }

    // ===== STEP 0 :  Requirements & Process =====
    function step0() {
        const checkSvgSm = `<svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#check-circle-icon" />
        </svg>`;

        const requirements = [{
            title: 'IACUC training certificate',
            subtitle: state.certAlready ?
                'You have already submitted your certificate.' : 'Required for first-time submitters.',
            pill: state.certAlready ?
                `<span class="status-pill status-pill-done">${checkSvgSm}Submitted</span>` : `<span class="status-pill status-pill-required">Required</span>`,
        }, ];

        const process = [
            `Submit your IACUC training certificate. <br>
                <span class="process-note">
                This is a one-time requirement and will be referenced for subsequent IACUC protocol review applications.
                </span>`,
            `Download and complete the official IACUC protocol form. <br>
                <span class="process-note">
                Only the Principal Investigator (PI) may submit an IACUC protocol for review.
                Multiple submissions of the same protocol will be rejected.
                </span>`,
            `Submit your completed form. You will be notified via email on updates.`,
        ];

        return `
    <div class="page-tag">Step 1 of 5</div>
    <div class="page-title">Requirements and process</div>

    <div class="section-label">You'll need</div>
    <div class="doc-list">
    ${requirements.map(r => `
    <div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">${r.title}</div>
            <span class="doc-row-sub">${r.subtitle}</span>
        </div>
        <div class="doc-row-action">${r.pill}</div>
    </div>`).join('')}
    </div>

    <div class="section-label section-label--lg-top">How it works</div>
    ${process.map((s, i) => `
    <div class="process-step">
        <div class="process-num">${i + 1}</div>
        <div class="process-text">${s}</div>
    </div>`).join('')}

    <div class="btn-row btn-row--lg-top">
        <button class="btn-primary" onclick="goTo(1)">
            Continue
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#arrow-right-icon" />
            </svg>
        </button>
    </div>`;
    }

    // ===== STEP 1:  Terms & Conditions =====
    function step1() {
        return `
    <div class="page-tag">Step 2 of 5</div>
    <div class="page-title">Terms and conditions</div>
    <p class="step-intro-text">Please review the following before continuing with your application.</p>

    <div class="consent-list">
        <label class="consent-item">
            <input type="checkbox" class="consent-checkbox" id="chk-terms" ${state.agreedTerms ? 'checked' : ''}>
            <span>I have read and agree to the
                <a href="#" class="underlined" onclick="openDocModal(event,'terms-modal')">terms of use</a>
            </span>
        </label>
        <label class="consent-item">
            <input type="checkbox" class="consent-checkbox" id="chk-privacy" ${state.agreedPrivacy ? 'checked' : ''}>
            <span>I have read and agree to the
                <a href="#" class="underlined" onclick="openDocModal(event,'privacy-modal')">privacy policy</a>
            </span>
        </label>
    </div>

    <div id="terms-error" class="error-messages is-hidden"></div>

    <div class="btn-row">
        <button class="btn-secondary" onclick="goTo(0)">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon" />
            </svg>
            Back
        </button>
        <button class="btn-primary" onclick="proceedFromTerms()">
            Continue
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#arrow-right-icon" />
            </svg>
        </button>
    </div>

    <div class="modal-backdrop" id="terms-modal">
        <div class="modal-card">
            <h2>Terms of use</h2>
            <div class="tc-scroll">
                <h3>1. Acceptance of terms</h3>
                By submitting this application, you confirm that you have read, understood, and agree to be bound by these terms. You also confirm that you are the Principal Investigator of this protocol and that you are authorized to submit it.

                <h3>2. Governing laws and policies</h3>
                Animal use activities covered by this application must comply with Republic Act No. 8485 (Animal Welfare Act of 1998), as amended by Republic Act No. 10631, Department of Agriculture Administrative Order No. 40, Series of 1999, and the policies of Benguet State University. The processing of personal data through this system is governed by Republic Act No. 10173 (Data Privacy Act of 2012), its Implementing Rules and Regulations, and issuances of the National Privacy Commission.

                <h3>3. Accuracy of information</h3>
                Information and documents you submit must be true, accurate, and complete. Any misrepresentation may result in denial, suspension, or revocation of approval.

                <h3>4. Conduct of approved activities</h3>
                Animal use activities must be conducted exactly as described and approved. Changes to an approved protocol must be submitted and approved before implementation. You are responsible for ensuring that all personnel are appropriately trained and listed on this protocol.

                <h3>5. Monitoring and non-compliance</h3>
                The IACUC reserves the right to inspect and audit animal use activities at any time. Non-compliance may result in suspension or termination of the protocol and reporting to the relevant regulatory authorities.

                <h3>6. Account responsibility</h3>
                You are responsible for keeping your login credentials confidential and for all activity under your account. Notify the CCARD office immediately if you suspect unauthorized access.

                <h3>7. Personal data</h3>
                Your personal data is handled as described in the privacy policy, which forms part of these terms.

                <h3>8. Changes to these terms</h3>
                The IACUC may update these terms from time to time. Continued use of this system after an update means you accept the revised terms. These terms are governed by the laws of the Republic of the Philippines.
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-primary" onclick="closeDocModal('terms-modal')">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-backdrop" id="privacy-modal">
        <div class="modal-card">
            <h2>Privacy policy</h2>
            <div class="tc-scroll">
                <h3>1. Who we are</h3>
                The Benguet State University CCARD Institutional Animal Care and Use Committee (IACUC) operates this system and is the personal information controller for the data collected through it. This policy is issued in accordance with Republic Act No. 10173 (Data Privacy Act of 2012), its Implementing Rules and Regulations, and the issuances of the National Privacy Commission (NPC).

                <h3>2. Personal data we collect</h3>
                Account details: your name, username, email address, mobile number, sex, and school or college. Application records: your IACUC protocol form, IACUC training certificate, proof of payment, and related submissions, reviewer comments, and status history. System records: audit logs of actions taken in the system and session information needed to keep your account secure.

                <h3>3. Why we process your data</h3>
                We process your data only to create and manage your account, receive and review your protocol, communicate with you about its status, issue clearances, keep institutional records, and prepare aggregated statistics. We do not use your data for marketing and we do not sell it.

                <h3>4. Legal basis</h3>
                We process your personal data based on your consent, given by ticking the box on this page, and to comply with the legal and regulatory obligations of the IACUC under the laws cited in the terms of use. You may withdraw your consent at any time by contacting the CCARD office. Withdrawal does not affect processing already done, and we may be unable to continue reviewing your protocol without the data needed for it.

                <h3>5. Who can see your data</h3>
                Your data is accessible only to authorized CCARD administrative staff and IACUC reviewers, and to the relevant offices of the University who need it for your protocol. It may be disclosed to the Bureau of Animal Industry and other government or accrediting bodies when required by law. Submitted protocols are confidential institutional documents and will not be disclosed to unauthorized parties.

                <h3>6. How long we keep it</h3>
                We keep personal data only for as long as needed for the purposes above and as required by law and University records policies. After that it is securely disposed of or anonymized.

                <h3>7. How we protect it</h3>
                We use reasonable and appropriate organizational, physical, and technical measures, including role-based access, secure sessions with automatic timeout, and audit logging. In case of a personal data breach, we will notify you and the NPC as required by law.

                <h3>8. Your rights</h3>
                Under the Data Privacy Act you have the right to be informed about how your data is processed, to access your data, to object to its processing, to correct inaccurate data, to request erasure or blocking of your data, to data portability, to be indemnified for damages caused by inaccurate, unlawfully obtained, or unauthorized use of your data, and to file a complaint with the National Privacy Commission (privacy.gov.ph). You may update your details or deactivate or delete your account from your account page. Some records may be retained where the law requires it.

                <h3>9. Contact us</h3>
                To exercise your rights or ask questions about this policy, contact the CCARD office or the University's Data Protection Officer.

                <h3>10. Changes to this policy</h3>
                We may update this policy and will post the revised version here.
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-primary" onclick="closeDocModal('privacy-modal')">Close</button>
            </div>
        </div>
    </div>`;
    }

    // ===== STEP 2:  Attach Documents =====
    function step2() {
        const certRequired = !state.certAlready;

        const rows = [
            docRow('cert', {
                title: 'IACUC training certificate',
                subtitle: 'PDF, JPG, or PNG · max 10 MB',
                required: certRequired,
                alreadyOnFile: state.certAlready,
                alreadyNote: 'You have already submitted your certificate.'
            }),
        ];

        return `
    <div class="page-tag">Step 3 of 5</div>
    <div class="page-title">Attach documents</div>

    ${!state.certAlready ? `
    <p class="step-intro-text">
        The training certificate is issued upon completion of your IACUC (animal care and use) course or seminar.
        If you haven't completed this training yet, contact the IACUC office before continuing.
    </p>` : ''}

    <div class="doc-list">${rows.join('')}</div>

    <div id="doc-error" class="error-messages is-hidden"></div>

    ${uploadWarningNotice}

    <div class="btn-row">
        <button class="btn-secondary" onclick="goTo(1)">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon" />
            </svg>
            Back
        </button>
        <button class="btn-primary" onclick="proceedFromDocs()">
            Continue
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#arrow-right-icon" />
            </svg>
        </button>
    </div>`;
    }

    // ===== STEP 3:  Download Protocol Form =====
    function step3() {
        const formUrl = ROOT + '/assets/forms/BSU-IACUC_Application_for_Protocol_Review_Form.docx';

        return `
    <div class="page-tag">Step 4 of 5</div>
    <div class="page-title">Download the official IACUC protocol form</div>

    <p class="step-intro-text">
        Complete this form, then upload in the next step.
    </p>

    <div class="doc-list">
    <div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">Official IACUC protocol form</div>
            <span class="doc-row-sub">Word document (.DOCX)</span>
        </div>
        <div class="doc-row-action">
            <a href="${formUrl}" download class="btn-upload-inline">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#download-icon" />
                </svg>
                Download
            </a>
        </div>
    </div>
    </div>

    <div class="btn-row">
        <button class="btn-secondary" onclick="goTo(2)">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon" />
            </svg>
            Back
        </button>
        <button class="btn-primary" onclick="goTo(4)">
            Continue
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#arrow-right-icon" />
            </svg>
        </button>
    </div>`;
    }

    // ===== STEP 4:  Upload Protocol Form & Enter Protocol Details =====
    const DETAIL_FIELDS = [{
            id: 'inp-title',
            key: 'title',
            label: 'Protocol title',
            attrs: 'maxlength="255" placeholder="e.g. Effects of X on Y in Z model"',
        },
        {
            id: 'inp-animal-type',
            key: 'animalType',
            label: 'Animal type',
            attrs: 'list="animal-type-options" maxlength="100" placeholder="e.g. Mice"'
        },
        {
            id: 'inp-animal-count',
            key: 'animalCount',
            label: 'Animal count',
            type: 'number',
            attrs: 'min="1" step="1" inputmode="numeric" placeholder="e.g. 30"'
        },
        {
            id: 'inp-adviser',
            key: 'researchAdviser',
            label: 'Research adviser',
            attrs: 'list="adviser-options" maxlength="255" placeholder="Full name"'
        },
        {
            id: 'inp-veterinarian',
            key: 'veterinarian',
            label: 'Veterinarian',
            attrs: 'list="veterinarian-options" maxlength="255" placeholder="Full name"'
        },
    ];

    function detailField(f) {
        return `
    <div class="field">
        <label for="${f.id}">${f.label} <span class="req">*</span></label>
        <input type="${f.type || 'text'}" id="${f.id}" value="${esc(state[f.key])}" ${f.attrs}>
    </div>
    ${f.hint ? `<p class="helper field-hint">${f.hint}</p>` : ''}`;
    }

    function captureDetails() {
        DETAIL_FIELDS.forEach(f => {
            const el = document.getElementById(f.id);
            if (el) state[f.key] = el.value.trim();
        });
    }

    function prefillFromPrevious() {
        state.researchAdviser = state.researchAdviser || PREVIOUS_DETAILS.research_adviser || '';
        state.veterinarian = state.veterinarian || PREVIOUS_DETAILS.veterinarian || '';
    }

    function step4() {
        return `
    <div class="page-tag">Step 5 of 5</div>
    <div class="page-title">Submit your protocol</div>

    <div class="section-label">Part 1: Upload completed form</div>
    <p class="step-intro-text">Print, sign, and scan the updated protocol, then upload the signed copy. Make sure your uploads are legible, accurate, and complete.</p>

    <div class="doc-list">
    ${docRow('protocol', {
        title: 'Completed IACUC protocol form',
        subtitle: 'PDF, DOC, or DOCX · max 10 MB',
        required: true
    })}
    </div>

    ${state.protocolName && state.protocolConverted ? convertedNotice() : ''}

    <div class="section-label section-label--lg-top">Part 2: Protocol details</div>
    <p class="step-intro-text">Make sure to enter these exactly as written on your form.</p>

    ${DETAIL_FIELDS.map(detailField).join('')}

    <div id="upload-error" class="error-messages is-hidden"></div>

    <div class="btn-row">
        <button class="btn-secondary" onclick="goTo(3)">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon" />
            </svg>
            Back
        </button>
        <button class="btn-success" id="btn-submit" onclick="submitProtocol()">
            <span id="submit-label">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#check-icon" />
                </svg>
                Submit protocol
            </span>
            <span id="submit-spinner" class="is-hidden">Submitting…</span>
        </button>
    </div>`;
    }

    // ===== STEP 5:  Done =====
    function step5() {
        return `
    <div class="success-center">
        <div class="success-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#check-circle-icon" />
            </svg>
        </div>
        <div class="page-title page-title--center">Protocol submitted</div>
        <p class="success-desc">
            Your protocol has been received and will be assigned to a reviewer at BSU-CCARD. Expect feedback within <strong>5–7 business days</strong>. You will be notified via email.
        </p>
        <div class="success-btn-row">
            <a href="${ROOT}/submissions?submitted=1" class="btn-link" onclick="disarmGuard();">
                Go to My Protocols
            </a>
        </div>
    </div>`;
    }

    // ===== RENDER =====
    function render() {
        renderStepper();
        const pages = [step0, step1, step2, step3, step4, step5];
        document.getElementById('page-content').innerHTML = pages[state.step]();
        attachStepListeners();
    }

    function attachStepListeners() {
        const ct = document.getElementById('chk-terms');
        const cp = document.getElementById('chk-privacy');
        if (ct) ct.addEventListener('change', e => {
            state.agreedTerms = e.target.checked;
            saveState();
        });
        if (cp) cp.addEventListener('change', e => {
            state.agreedPrivacy = e.target.checked;
            saveState();
        });

        DETAIL_FIELDS.forEach(f => {
            const el = document.getElementById(f.id);
            if (el) el.addEventListener('input', e => {
                state[f.key] = e.target.value;
                saveState();
            });
        });
    }

    // ===== STEP LOGIC =====
    function proceedFromTerms() {
        const t = document.getElementById('chk-terms');
        const p = document.getElementById('chk-privacy');
        const errBox = document.getElementById('terms-error');
        state.agreedTerms = t && t.checked;
        state.agreedPrivacy = p && p.checked;
        saveState();
        if (!state.agreedTerms || !state.agreedPrivacy) {
            errBox.textContent = 'Please accept both the terms of use and the privacy policy to continue.';
            errBox.classList.remove('is-hidden');
            return;
        }
        errBox.classList.add('is-hidden');
        goTo(2);
    }

    function proceedFromDocs() {
        const errBox = document.getElementById('doc-error');
        const showErr = msg => {
            errBox.textContent = msg;
            errBox.style.display = 'flex';
        };
        errBox.style.display = 'none';

        if (!state.certAlready && !state.certName) {
            showErr('Please upload your IACUC training certificate.');
            return;
        }

        goTo(3);
    }

    async function handleUpload(event, key) {
        const file = event.target.files[0];
        if (!file) return;

        const isWord = key === 'protocol' && !file.name.toLowerCase().endsWith('.pdf');

        if (key === 'protocol') {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'doc', 'docx'].includes(ext)) {
                alert('Please upload your IACUC protocol form as a PDF, DOC, or DOCX file.');
                event.target.value = '';
                return;
            }
        } else {
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only PDF, JPG, or PNG files are accepted.');
                event.target.value = '';
                return;
            }
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
                alert('Only PDF, JPG, or PNG files are accepted.');
                event.target.value = '';
                return;
            }
        }

        if (file.size > 10 * 1024 * 1024) {
            alert('File is too large. Maximum size is 10 MB.');
            event.target.value = '';
            return;
        }

        captureDetails();
        saveState();

        const fieldMap = {
            protocol: 'protocol_file',
            cert: 'cert'
        };
        const fd = new FormData();
        fd.append('key', key);
        fd.append(fieldMap[key], file);

        uploadProgress[key] = true;
        render();
        const progressContainer = document.getElementById('upload-progress-' + key);
        const bar = createUploadProgressBar(progressContainer);

        try {
            const json = await uploadWithProgress(ROOT + '/apply/draftupload', fd, {
                onProgress: pct => {
                    bar.update(pct);
                    if (pct >= 100 && isWord && progressContainer) {
                        progressContainer.closest('.doc-row-info').querySelector('.doc-row-sub').textContent = 'Converting to PDF, please wait…';
                    }
                }
            });

            if (json.error) {
                alert(json.error ?? 'Upload failed. Please try again.');
                delete uploadProgress[key];
                render();
                return;
            }

            state[key + 'Name'] = json.name;
            state[key + 'Size'] = json.size;
            state.protocolConverted = key === 'protocol' && !!json.converted;
            delete uploadProgress[key];
            render();
        } catch (e) {
            alert(e.message || 'Network error. Please check your connection and try again.');
            delete uploadProgress[key];
            render();
        }
    }

    // ===== Remove uploaded file =====
    function removeFile(key) {
        state[key + 'Name'] = null;
        state[key + 'Size'] = null;
        if (key === 'protocol') state.protocolConverted = false;
        render();
        removeDraftFile(key);
    }

    function previewDraftFile(event, key) {
        event.preventDefault();
        openFilePopup(ROOT + '/apply/draftfile/' + key, 'Converted protocol form');
    }

    async function removeDraftFile(key) {
        try {
            await fetch(ROOT + '/apply/draftremovefile', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': DRAFT_CSRF
                },
                body: JSON.stringify({
                    key
                })
            });
        } catch (e) {}
    }


    // ===== SUBMIT =====
    async function submitProtocol() {
        const errBox = document.getElementById('upload-error');
        const btnLabel = document.getElementById('submit-label');
        const spinner = document.getElementById('submit-spinner');
        const btn = document.getElementById('btn-submit');

        errBox.style.display = 'none';

        captureDetails();

        const missing = DETAIL_FIELDS.find(f => !state[f.key]);
        if (missing) {
            errBox.textContent = `Please enter the ${missing.label.toLowerCase()}.`;
            errBox.style.display = 'flex';
            return;
        }

        if (!Number.isInteger(Number(state.animalCount)) || Number(state.animalCount) < 1) {
            errBox.textContent = 'Animal count must be a whole number of 1 or more.';
            errBox.style.display = 'flex';
            return;
        }

        if (!state.protocolName) {
            errBox.textContent = 'Please upload your completed IACUC protocol form.';
            errBox.style.display = 'flex';
            return;
        }

        btn.disabled = true;
        btnLabel.style.display = 'none';
        spinner.style.display = 'inline';

        clearTimeout(_saveTimer);
        await pushDraftFields();

        try {
            const res = await fetch(ROOT + '/apply/submit', {
                method: 'POST'
            });
            const json = await res.json();

            if (!res.ok || json.error) {
                errBox.textContent = json.error ?? 'Submission failed. Please try again.';
                errBox.style.display = 'flex';
                btn.disabled = false;
                btnLabel.style.display = 'inline-flex';
                spinner.style.display = 'none';
                return;
            }

            state.submittedId = json.protocolId ?? null;
            disarmGuard();

            state.step = 5;
            render();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

        } catch (err) {
            errBox.textContent = 'Network error. Please check your connection and try again.';
            errBox.style.display = 'flex';
            btn.disabled = false;
            btnLabel.style.display = 'inline-flex';
            spinner.style.display = 'none';
        }
    }

    // ===== INIT =====
    render();

    loadDraft().then(() => {
        prefillFromPrevious();
        render();
    });

    fetch(ROOT + '/apply/hascert')
        .then(r => r.json())
        .then(d => {
            state.certAlready = !!d.has_cert;
            render();
        })
        .catch(() => {
            state.certAlready = false;
            render();
        });

    armGuard();
</script>

<?php include "includes/footer.php"; ?>