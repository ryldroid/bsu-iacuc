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
            </div>

            <div class="stepper" id="stepper"></div>

            <div class="card" id="page-content"></div>
        </div>

    </main>
</div>

<script>
    // ===== CONSTANTS =====
    const ROOT = '<?= ROOT ?>';
    const STEPS = ['Requirements', 'Terms', 'Documents', 'Download form', 'Upload and submit', 'Done'];
    const DRAFT_CSRF = NOTIF_CSRF_TOKEN;

    // ===== STATE =====
    let state = {
        step: 0,
        agreedTerms: false,
        agreedPrivacy: false,
        isPi: null,
        certAlready: false,
        certName: null,
        certSize: null,
        authName: null,
        authSize: null,
        protocolName: null,
        protocolSize: null,
        title: '',
        submittedId: null,
    };

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
                    isPi: state.isPi,
                    title: state.title
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
            state.isPi = d.isPi;
            state.title = d.title;

            ['protocol', 'cert', 'auth'].forEach(key => {
                const f = d[key];
                state[key + 'Name'] = f ? f.name : null;
                state[key + 'Size'] = f ? f.size : null;
            });
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
            'Leave the form? Your progress has been saved. You can continue from any device.', {
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

    function uploadBox(key, label, subtitle, required = false) {
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
        const accept = key === 'protocol' ? 'application/pdf,.pdf' : '.pdf,.jpg,.jpeg,.png';
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
        const accept = key === 'protocol' ? 'application/pdf,.pdf' : '.pdf,.jpg,.jpeg,.png';
        const checkSvg = `<svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <use href="#check-circle-icon" />
        </svg>`;

        let subLine, action;

        if (opts.alreadyOnFile) {
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
        <span><span class="bold">All uploads will be thoroughly examined.</span> Make sure documents are legible, complete, and accurate before submitting.</span>
    </div>`;

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
                    `<span class="status-pill status-pill-done">${checkSvgSm}On file</span>` : `<span class="status-pill status-pill-required">Required</span>`,
            },
            {
                title: 'Authorization letter by the Principal Investigator',
                subtitle: 'Only needed if you are not the PI of the study.',
                pill: `<span class="status-pill status-pill-muted">If applicable</span>`,
            },
        ];

        const process = [
            `Attach requirements if applicable. <br>
         <span class="process-note">
           Your training certificate is required unless you have already submitted one previously.
           If you are not the Principal Investigator, attach an authorization letter.
         </span>`,
            `Download the official IACUC protocol form, fill it in, then upload it in the next step.`,
            `Submit your completed form. You will be notified via email on updates on your protocol.`,
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
                By submitting this application, you agree to comply with all applicable laws, regulations, and
                institutional policies governing the use of animals in research, teaching, and testing.
                Information provided must be accurate and complete. Any misrepresentation may result in denial
                or revocation of approval.<br><br>
                All animal use activities must be conducted exactly as described and approved. Changes to the
                approved protocol must be submitted and approved before implementation. You are responsible for
                ensuring all personnel are appropriately trained and listed on this protocol.<br><br>
                The IACUC reserves the right to inspect and audit animal use activities at any time.
                Non-compliance may result in suspension or termination of the protocol and reporting to
                relevant regulatory authorities.
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
                Personal information collected through this application will be used solely for processing your
                IACUC protocol submission. Data may be shared with relevant institutional offices, the Bureau
                of Animal Industry, and accrediting bodies as required by law.<br><br>
                Submitted protocols are confidential institutional documents and will not be disclosed to
                unauthorized parties. You have the right to request access to your personal data and correct
                any inaccuracies by contacting the IACUC office.
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
            `<div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">Are you the Principal Investigator?</div>
        </div>
        <div class="doc-row-action">
            <div class="toggle-group">
                <button type="button" class="toggle-btn ${state.isPi === true ? 'active' : ''}"
                        onclick="setIsPi(true)">Yes</button>
                <button type="button" class="toggle-btn ${state.isPi === false ? 'active' : ''}"
                        onclick="setIsPi(false)">No</button>
            </div>
        </div>
    </div>`,
        ];

        if (state.isPi === false) {
            rows.push(docRow('auth', {
                title: 'Authorization letter by PI',
                subtitle: 'PDF, JPG, or PNG · max 10 MB',
                required: true
            }));
        }

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
        const formPdfUrl = ROOT + '/assets/forms/BSU-IACUC_Application_for_Protocol_Review_Form.pdf';
        const formDocxUrl = ROOT + '/assets/forms/BSU-IACUC_Application_for_Protocol_Review_Form.docx';

        const formats = [{
                title: 'Word document (.DOCX)',
                subtitle: 'Convert to PDF once complete.',
                url: formDocxUrl,
            },
            {
                title: 'Fillable PDF',
                subtitle: 'Fill in directly and save as PDF.',
                url: formPdfUrl,
            },
        ];

        return `
    <div class="page-tag">Step 4 of 5</div>
    <div class="page-title">Download protocol form</div>

    <p class="step-intro-text">
        Download and complete the official BSU-IACUC protocol form in either format below, then upload
        the finished PDF in the next step.
    </p>

    <div class="section-label">Official protocol form</div>
    <div class="doc-list">
    ${formats.map(f => `
    <div class="doc-row">
        <div class="doc-row-info">
            <div class="doc-row-title">${f.title}</div>
            <span class="doc-row-sub">${f.subtitle}</span>
        </div>
        <div class="doc-row-action">
            <a href="${f.url}" download class="btn-upload-inline">
                <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <use href="#download-icon" />
                </svg>
                Download
            </a>
        </div>
    </div>`).join('')}
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

    // ===== STEP 4:  Upload Protocol Form & Enter Title =====
    function step4() {
        return `
    <div class="page-tag">Step 5 of 5</div>
    <div class="page-title">Upload completed form</div>

    <div class="section-label">Protocol title <span class="req">*</span></div>
    <div class="field">
        <input type="text" id="inp-title" value="${esc(state.title)}"
               placeholder="e.g. Effects of X on Y in Z model" maxlength="255">
    </div>
    <p class="helper field-hint">Make sure this title matches the Protocol Title on your form exactly.</p>

    <div class="section-label">Completed protocol form</div>
    <div class="doc-list">
    ${docRow('protocol', {
        title: 'Protocol form (PDF)',
        subtitle: 'Filename: Surname_ProtocolTitle.pdf · max 10 MB',
        required: true
    })}
    </div>

    <div id="upload-error" class="error-messages is-hidden"></div>

    ${uploadWarningNotice}

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

        const ti = document.getElementById('inp-title');
        if (ti) ti.addEventListener('input', e => {
            state.title = e.target.value;
            saveState();
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

    function setIsPi(val) {
        state.isPi = val;
        saveState();
        if (val === true && state.authName) {
            state.authName = null;
            state.authSize = null;
            removeDraftFile('auth');
        }
        render();
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
        if (state.isPi === null) {
            showErr('Please indicate if you are the Principal Investigator.');
            return;
        }
        if (state.isPi === false && !state.authName) {
            showErr('Please upload the authorization letter of the Principal Investigator.');
            return;
        }

        goTo(3);
    }

    async function handleUpload(event, key) {
        const file = event.target.files[0];
        if (!file) return;

        if (key === 'protocol') {
            if (file.type !== 'application/pdf') {
                alert('Only PDF files are accepted for the protocol form.');
                event.target.value = '';
                return;
            }
            const ext = file.name.split('.').pop().toLowerCase();
            if (ext !== 'pdf') {
                alert('Only PDF files are accepted for the protocol form.');
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

        const titleInp = document.getElementById('inp-title');
        if (titleInp) {
            state.title = titleInp.value;
            saveState();
        }

        event.target.disabled = true;

        const fieldMap = {
            protocol: 'protocol_file',
            cert: 'cert',
            auth: 'auth'
        };
        const fd = new FormData();
        fd.append('key', key);
        fd.append(fieldMap[key], file);

        try {
            const res = await fetch(ROOT + '/apply/draftupload', {
                method: 'POST',
                body: fd
            });
            const json = await res.json();

            if (!res.ok || json.error) {
                alert(json.error ?? 'Upload failed. Please try again.');
                event.target.disabled = false;
                event.target.value = '';
                return;
            }

            state[key + 'Name'] = json.name;
            state[key + 'Size'] = json.size;
            render();
        } catch (e) {
            alert('Network error. Please check your connection and try again.');
            event.target.disabled = false;
            event.target.value = '';
        }
    }

    function removeFile(key) {
        state[key + 'Name'] = null;
        state[key + 'Size'] = null;
        render();
        removeDraftFile(key);
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

        const titleInp = document.getElementById('inp-title');
        if (titleInp) {
            state.title = titleInp.value.trim();
        }

        if (!state.title) {
            errBox.textContent = 'Please enter a protocol title.';
            errBox.style.display = 'flex';
            return;
        }

        if (!state.protocolName) {
            errBox.textContent = 'Please upload your completed protocol form.';
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

    loadDraft().then(render);

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