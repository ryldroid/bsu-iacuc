<?php

/** @var array|null $user */
$title = "Submit Protocol";
$hideWelcomeGuide = true;

include "includes/header.php";
include "includes/scroll-top.php";
?>

<link rel="stylesheet" href="<?= asset_css('application.css') ?>">
<link rel="stylesheet" href="<?= asset_css('consent.css') ?>">
<link rel="stylesheet" href="<?= asset_css('protocol-modals.css') ?>">

<div class="body">
    <main class="main-content" id="main-content" tabindex="-1">

        <div class="page-banner">
            <div class="page-banner-text">
                <div class="page-banner-title">
                    <h1>IACUC Application Portal</h1>
                    <?php $pageGuideId = 'apply';
                    include 'includes/page-guide.php'; ?>
                </div>
                <p class="page-subtitle">Submit your protocol and training certificate for IACUC review</p>
            </div>
        </div>

        <a class="button btn-back" id="btn-home" href="<?= ROOT ?>/submissions" onclick="askLeave(event, '<?= ROOT ?>/submissions')">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon">
            </svg>
            Back to Dashboard
        </a>

        <div class="portal">
            <div class="stepper" id="stepper"></div>

            <div class="card" id="page-content"></div>
        </div>

    </main>
</div>

<?php
$guideId    = 'apply';
$guideSteps = [
    ['Application', 'Applying for protocol review takes a few short steps. Let us walk through this page.'],
    ['Progress', 'Move through the requirements, terms and conditions, one-time upload of your IACUC training certificate, download of the official IACUC protocol review form, and finally the filling of protocol details and submission.', '#stepper'],
    ['Current step', 'Thoroughly read and follow the instructions. You can navigate the steps with **Back** and **Continue**.', '#page-content'],
    ['Leave and resume', 'Press to go back to your dashboard. Your progress will be saved, and you can resume the application from any time.', '#btn-home'],
];
include 'includes/guide.php';
?>

<?php include "includes/file-popup.php"; ?>
<?php include "includes/person-options.php"; ?>
<script src="<?= asset_js('file-popup.js') ?>"></script>
<script src="<?= asset_js('doc-modal.js') ?>"></script>

<script>
    // ===== CONSTANTS =====
    const ROOT = '<?= ROOT ?>';
    const STEPS = ['Requirements', 'Terms', 'Documents', 'Download form', 'Upload and submit', 'Done'];
    const PREVIOUS_DETAILS = <?= json_encode($previousDetails ?? []) ?>;
    const ANIMAL_TYPES = <?= json_encode(RecordModel::ANIMAL_TYPES) ?>;
    window.EMOJI_PATTERN = new RegExp(<?= json_encode('[' . emoji_class_body('\\u') . ']') ?>, 'gu');

    // ===== STATE =====
    let state = {
        step: 0,
        agreedTerms: false,
        agreedPrivacy: false,
        certAlready: false,
        certStatus: null,
        certBlock: null,
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
        researchDurationStart: '',
        researchDurationEnd: '',
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
                headers: csrfHeaders({
                    'Content-Type': 'application/json'
                }),
                body: JSON.stringify({
                    step: state.step,
                    agreedTerms: state.agreedTerms,
                    agreedPrivacy: state.agreedPrivacy,
                    title: state.title,
                    animalType: state.animalType,
                    animalCount: state.animalCount,
                    researchAdviser: state.researchAdviser,
                    veterinarian: state.veterinarian,
                    researchDurationStart: state.researchDurationStart,
                    researchDurationEnd: state.researchDurationEnd
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
            state.researchDurationStart = d.researchDurationStart;
            state.researchDurationEnd = d.researchDurationEnd;

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
        if (n === 2 && state.certAlready) n = state.step < 2 ? 3 : 1;
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
    function shownSteps() {
        return STEPS.map((label, i) => i).filter(i => !(i === 2 && state.certAlready));
    }

    function stepTag(stepIndex) {
        const shown = shownSteps();
        return `<div class="page-tag">Step ${shown.indexOf(stepIndex) + 1} of ${shown.length - 1}</div>`;
    }

    function renderStepper() {
        const el = document.getElementById('stepper');
        const checkSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>`;
        let html = '';
        const shown = shownSteps();
        shown.forEach((stepIndex, position) => {
            const cls = stepIndex < state.step ? 'done' : stepIndex === state.step ? 'active' : '';
            const inner = stepIndex < state.step ? checkSvg : (position + 1);
            html += `<div class="step-dot ${cls}" title="${STEPS[stepIndex]}">${inner}</div>`;
            if (position < shown.length - 1)
                html += `<div class="step-line ${stepIndex < state.step ? 'done' : ''}"></div>`;
        });
        el.innerHTML = html;
    }

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
            <span class="file-badge-name">${escapeHtml(name)}${size ? ` <span class="file-badge-size">(${size})</span>` : ''}</span>
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
        const title = `${opts.title}${opts.required ? ' <span class="req">*</span>' : ''}`;

        if (name && !uploadProgress[key] && !opts.alreadyOnFile) {
            return uploadedDocRow({
                key,
                title: opts.title,
                required: opts.required,
                name,
                size: state[key + 'Size'],
                accept,
                uploadHandler: 'handleUpload',
                removeHandler: 'removeFile',
                previewHandler: 'previewUpload'
            });
        }

        let subLine, action;

        if (uploadProgress[key]) {
            subLine = `<span class="doc-row-sub">Uploading&hellip;</span>
            <div class="upload-progress-container" id="upload-progress-${key}"></div>`;
            action = '';
        } else if (opts.alreadyOnFile) {
            subLine = `<span class="doc-row-sub done">${opts.alreadyNote}</span>`;
            action = `<div class="doc-row-done">
            <svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#check-circle-icon" />
            </svg>
            <span>Submitted</span>
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

        return `<div class="doc-list">
        <div class="doc-row">
            <div class="doc-row-info">
                <div class="doc-row-title">${title}</div>
                ${subLine}
            </div>
            <div class="doc-row-action">${action}</div>
        </div>
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
        <span><strong>Converted to PDF:</strong> Word files are converted to PDF for submission. Please review the converted file, since conversion can shift the layout slightly.</span>
    </div>`;
    }

    // ===== STEP 0 :  Requirements & Process =====
    function step0() {
        const checkSvgSm = `<svg width="12" height="12" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#check-circle-icon" />
        </svg>`;

        const requirements = [{
            title: 'IACUC training certificate',
            subtitle: !state.certAlready ?
                'Required for first-time submitters.' : state.certStatus === 'verified' ?
                'Your certificate is verified.' : state.certStatus === 'rejected' ?
                'Your certificate was rejected. Upload a new one from My Account before submitting.' : 'Your certificate is waiting for verification. You can submit once it is verified.',
            pill: state.certStatus === 'verified' ?
                `<span class="status-pill status-pill-done">${checkSvgSm}Verified</span>` : `<span class="status-pill status-pill-required">${!state.certAlready ? 'Required' : state.certStatus === 'rejected' ? 'Resubmit' : 'Pending'}</span>`,
        }, ];

        const process = [
            ...(state.certAlready ? [] : [`Submit your IACUC training certificate. <br>
                <span class="process-note">
                This is a one-time requirement and will be referenced for subsequent IACUC protocol review applications.
                </span>`]),
            `Download and complete the official IACUC protocol form. <br>
                <span class="process-note">
                Only the Principal Investigator (PI) may submit an IACUC protocol for review.
                Multiple submissions of the same protocol will be rejected.
                </span>`,
            `Submit your completed form. You will be notified via email on updates.`,
        ];

        return `
    ${stepTag(0)}
    <div class="page-title">Requirements and process</div>
    ${state.certBlock ? `<div class="error-messages" role="alert">${escapeHtml(state.certBlock)}</div>` : ''}

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
    ${stepTag(1)}
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

    <?php include __DIR__ . '/includes/terms-privacy-modals.php'; ?>`;
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
    ${stepTag(2)}
    <div class="page-title">Attach training certificate</div>

    ${!state.certAlready ? `
    <p class="step-intro-text">
        The training certificate is issued upon completion of your IACUC (animal care and use) course or seminar.
        If you have not completed this training yet, contact CCARD for a schedule before continuing.
    </p>` : ''}

    ${rows.join('')}

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
    ${stepTag(3)}
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
            limit: 255,
            capitalize: true,
        },
        {
            id: 'inp-animal-type',
            key: 'animalType',
            label: 'Animal type',
            options: ANIMAL_TYPES
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
        {
            id: 'inp-duration-start',
            key: 'researchDurationStart',
            label: 'Research start month',
            type: 'month',
            attrs: ''
        },
        {
            id: 'inp-duration-end',
            key: 'researchDurationEnd',
            label: 'Research end month',
            type: 'month',
            attrs: ''
        },
    ];

    function detailField(f) {
        const control = f.options ?
            `<select id="${f.id}"><option value="">Select</option>${f.options.map(option => `<option${state[f.key] === option ? ' selected' : ''}>${escapeHtml(option)}</option>`).join('')}</select>` :
            `<input type="${f.type || 'text'}" id="${f.id}" value="${escapeHtml(state[f.key])}" ${f.attrs}>`;
        return `
    <div class="field">
        <label for="${f.id}">${f.label} <span class="req">*</span></label>
        ${control}
    </div>
    ${f.limit ? `<p class="helper field-hint"><span id="${f.id}-count">${[...state[f.key]].length}/${f.limit}</span></p>` : ''}`;
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
    ${stepTag(4)}
    <div class="page-title">Submit your protocol</div>

    <div class="section-label">Part 1: Upload completed form</div>
    <p class="step-intro-text">Print your protocol, then collect the required signatures. Afterwards, scan and upload the signed copy. Make sure your uploads are legible, accurate, and complete.</p>

    ${docRow('protocol', {
        title: 'Completed IACUC protocol form',
        subtitle: 'PDF, DOC, or DOCX · max 10 MB',
        required: true
    })}

    <div id="protocol-error" class="error-messages is-hidden"></div>

    ${state.protocolName && state.protocolConverted && !uploadProgress.protocol ? convertedNotice() : ''}

    <hr class="part-1-2-hr">

    <div class="section-label section-label--lg-top">Part 2: Protocol details</div>
    <p class="step-intro-text">Make sure to enter these exactly as written on your form.</p>

    ${DETAIL_FIELDS.map(detailField).join('')}

    <div class="submit-reminders" role="note">
        <p class="submit-reminders-title">
            <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#info-icon" />
            </svg>
            Before you submit
        </p>
        <ol>
            <li>Check that your files and details are correct and complete.</li>
            <li>Only the Principal Investigator (PI) should submit. If you are part of a group, the other members should not submit the same protocol.</li>
            <li>Do not submit a protocol you already submitted. To send a revised version, resubmit through your dashboard. Duplicate submissions will be rejected.</li>
        </ol>
    </div>

    <div id="upload-error" class="error-messages is-hidden"></div>

    <div class="btn-row">
        <button class="btn-secondary" onclick="goTo(3)">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#back-icon" />
            </svg>
            Back
        </button>
        <button class="btn-success" id="btn-submit" onclick="submitProtocol()">
            <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <use href="#check-icon" />
            </svg>
            Submit protocol
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
            Your protocol has been received and will be assigned to a reviewer at CCARD. Expect feedback within <strong>5–7 business days</strong>. You will be notified via email.
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
        if (state.step === 2 && state.certAlready) state.step = 3;
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
                if (f.capitalize) capitalizeInputFirstLetter(e.target);
                const clean = e.target.value.replace(window.EMOJI_PATTERN, '');
                if (clean !== e.target.value) e.target.value = clean;
                state[f.key] = clean;
                const count = document.getElementById(f.id + '-count');
                if (count) count.textContent = `${[...clean].length}/${f.limit}`;
                saveState();
            });
        });

        const durationStart = document.getElementById('inp-duration-start');
        const durationEnd = document.getElementById('inp-duration-end');
        if (durationStart && durationEnd) {
            const syncDurationBounds = () => {
                durationEnd.min = durationStart.value;
                durationStart.max = durationEnd.value;
            };
            const enforceDurationOrder = e => {
                const errBox = document.getElementById('upload-error');
                if (durationStart.value && durationEnd.value && durationEnd.value < durationStart.value) {
                    e.target.value = '';
                    state[DETAIL_FIELDS.find(f => f.id === e.target.id).key] = '';
                    saveState();
                    showError(errBox, 'The research end month cannot be before the start month.');
                } else {
                    hideError(errBox);
                }
                syncDurationBounds();
            };
            durationStart.addEventListener('change', enforceDurationOrder);
            durationEnd.addEventListener('change', enforceDurationOrder);
            syncDurationBounds();
        }
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
        hideError(errBox);

        if (!state.certAlready && !state.certName) {
            showError(errBox, 'Please upload your IACUC training certificate.');
            return;
        }

        goTo(3);
    }

    function showError(box, msg) {
        box.textContent = msg;
        box.classList.remove('is-hidden');
        box.style.display = 'flex';
    }

    function hideError(box) {
        box.classList.add('is-hidden');
    }

    function showUploadError(key, msg) {
        showError(document.getElementById(key === 'protocol' ? 'protocol-error' : 'doc-error'), msg);
    }

    async function handleUpload(event, key) {
        const file = event.target.files[0];
        if (!file) return;

        ['protocol-error', 'doc-error'].forEach(id => {
            const box = document.getElementById(id);
            if (box) hideError(box);
        });

        const isWord = key === 'protocol' && !file.name.toLowerCase().endsWith('.pdf');

        if (key === 'protocol') {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'doc', 'docx'].includes(ext)) {
                showUploadError(key, 'Please upload your IACUC protocol form as a PDF, DOC, or DOCX file.');
                event.target.value = '';
                return;
            }
        } else {
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showUploadError(key, 'Only PDF, JPG, or PNG files are accepted.');
                event.target.value = '';
                return;
            }
            const ext = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(ext)) {
                showUploadError(key, 'Only PDF, JPG, or PNG files are accepted.');
                event.target.value = '';
                return;
            }
        }

        if (file.size > 10 * 1024 * 1024) {
            showUploadError(key, 'File is too large. Maximum size is 10 MB.');
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
        const bar = isWord ? null : createUploadProgressBar(progressContainer);
        if (isWord) showConversionOverlay();

        try {
            const json = await uploadWithProgress(ROOT + '/apply/draftupload', fd, {
                headers: csrfHeaders(),
                onProgress: pct => isWord ? updateConversionOverlay(pct) : bar.update(pct)
            });

            if (json.error) {
                delete uploadProgress[key];
                render();
                showUploadError(key, json.error);
                return;
            }

            state[key + 'Name'] = json.name;
            state[key + 'Size'] = json.size;
            state.protocolConverted = key === 'protocol' && !!json.converted;
            delete uploadProgress[key];
            render();
        } catch (e) {
            delete uploadProgress[key];
            render();
            showUploadError(key, e.message || 'Network error. Please check your connection and try again.');
        } finally {
            if (isWord) hideBusyOverlay();
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

    function previewUpload(key) {
        openFilePopup(ROOT + '/apply/draftfile/' + key, state[key + 'Name']);
    }

    async function removeDraftFile(key) {
        try {
            await fetch(ROOT + '/apply/draftremovefile', {
                method: 'POST',
                headers: csrfHeaders({
                    'Content-Type': 'application/json'
                }),
                body: JSON.stringify({
                    key
                })
            });
        } catch (e) {}
    }


    // ===== SUBMIT =====
    async function submitProtocol() {
        const errBox = document.getElementById('upload-error');
        const btn = document.getElementById('btn-submit');

        const failSubmit = msg => {
            hideBusyOverlay();
            showError(errBox, msg);
            btn.disabled = false;
        };

        hideError(errBox);

        captureDetails();

        const missing = DETAIL_FIELDS.find(f => !state[f.key]);
        if (missing) {
            failSubmit(`Please enter the ${missing.label.toLowerCase()}.`);
            return;
        }

        if (!Number.isInteger(Number(state.animalCount)) || Number(state.animalCount) < 1) {
            failSubmit('Animal count must be a whole number of 1 or more.');
            return;
        }

        if (state.researchDurationEnd < state.researchDurationStart) {
            failSubmit('The research end month cannot be before the start month.');
            return;
        }

        if (!state.protocolName) {
            failSubmit('Please upload your completed IACUC protocol form.');
            return;
        }

        btn.disabled = true;
        showBusyOverlay({
            title: 'Submitting your protocol\u2026',
            text: 'Please don\u2019t close or refresh this page.',
            percent: null
        });

        clearTimeout(_saveTimer);
        await pushDraftFields();

        try {
            const res = await fetch(ROOT + '/apply/submit', {
                method: 'POST',
                headers: csrfHeaders()
            });
            const json = await res.json();

            if (!res.ok || json.error) {
                failSubmit(json.error ?? 'Submission failed. Please try again.');
                return;
            }

            state.submittedId = json.protocolId ?? null;
            disarmGuard();

            hideBusyOverlay();
            state.step = 5;
            render();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

        } catch (err) {
            failSubmit('Network error. Please check your connection and try again.');
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
            state.certStatus = d.cert_status;
            state.certBlock = d.cert_block;
            render();
        })
        .catch(() => {
            state.certAlready = false;
            render();
        });

    armGuard();
</script>

<?php include "includes/footer.php"; ?>