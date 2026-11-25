// ===== Researcher details modal =====
const researcherBackdrop = document.getElementById("researcherModalBackdrop");
const researcherModalBody = document.getElementById("researcherModalBody");
let currentResearcherId = null;
let accountStatusChanged = false;
let currentResearcherName = "";
let certReviewMode = false;
let researcherLastFocus = null;

const CERT_REVIEW_TITLES = {
  pending: "Review before you continue",
  rejected: "Resubmission requested",
  verified: "Verified",
};

const CERT_STATUS_LABELS = {
  pending: "Pending",
  verified: "Verified",
  rejected: "Resubmission requested",
};
const CERT_REVIEW_LABELS = {
  verified: ["Verified on"],
  rejected: ["Reviewed on"],
};
const CERT_STATUS_BADGES = {
  pending: "payment-badge--proof_submitted",
  verified: "payment-badge--paid",
  rejected: "payment-badge--rejected",
};

function announceCertState(status) {
  document.dispatchEvent(
    new CustomEvent("cert-reviewed", { detail: { status } }),
  );
}

// ===== Open & render researcher details =====
function openResearcherModal(userId, fallbackName, options = {}) {
  if (!researcherBackdrop.classList.contains("open"))
    researcherLastFocus = document.activeElement;
  currentResearcherId = userId;
  certReviewMode = !!options.review;
  const modalLabel = document.getElementById("researcherModalLabel");
  modalLabel.textContent = "Training certificate";
  modalLabel.hidden = !certReviewMode;
  document.getElementById("researcherModalName").textContent = certReviewMode
    ? ""
    : "Researcher details";
  document.getElementById("researcherModalBody").innerHTML =
    '<p class="helper history-loading">Loading…</p>';
  researcherBackdrop.classList.add("open");
  document.getElementById("researcherModalClose").focus();

  if (!userId) {
    document.getElementById("researcherModalBody").innerHTML =
      '<p class="helper history-error">No account information available for this researcher.</p>';
    return;
  }

  fetch(
    ROOT_URL + "/personnel/researcher_details?id=" + encodeURIComponent(userId),
  )
    .then((r) => r.json())
    .then((data) => {
      if (!data.ok) {
        document.getElementById("researcherModalBody").innerHTML =
          '<p class="helper history-error">' +
          escapeHtml(data.message || "Could not load researcher details.") +
          "</p>";
        return;
      }
      renderResearcherDetails(data.data);
    })
    .catch(() => {
      document.getElementById("researcherModalBody").innerHTML =
        '<p class="helper history-offline">Researcher details are not available offline. It will load once you reconnect.</p>';
    });
}

function infoRow(icon, label, valueHtml) {
  return `
        <div class="researcher-info-row">
            <dt>
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#${icon}" /></svg>
                ${escapeHtml(label)}
            </dt>
            <dd>${valueHtml}</dd>
        </div>
    `;
}

function infoValue(text) {
  return text
    ? escapeHtml(text)
    : '<span class="researcher-info-empty">N/A</span>';
}

function contactLink(scheme, target, text) {
  return target
    ? `<a href="${scheme}:${escapeHtml(target)}">${escapeHtml(text)}</a>`
    : infoValue("");
}

function renderInfoRows(rows) {
  return `<dl class="researcher-info">${rows
    .map(([icon, label, valueHtml]) => infoRow(icon, label, valueHtml))
    .join("")}</dl>`;
}

function accountRows(d) {
  return d.status === "deactivated"
    ? [
        [
          "alert-triangle-icon",
          "Account",
          `<span class="status-dot status-dot--danger">${escapeHtml(d.status_label || "Deactivated")}</span>`,
        ],
      ]
    : [];
}

function renderResearcherDetails(d) {
  const fullName = (d.first_name + " " + d.last_name).trim();
  currentResearcherName = fullName;

  if (certReviewMode && d.cert) {
    renderCertReview(d, fullName);
    return;
  }

  document.getElementById("researcherModalBody").innerHTML = `
        ${renderInfoRows([
          ["account-icon", "Name", infoValue(fullName)],
          ["records-icon", "Researcher type", infoValue(d.researcher_type)],
          ["email-icon", "Email", contactLink("mailto", d.email, d.email)],
          [
            "phone-icon",
            "Phone",
            contactLink(
              "tel",
              (d.phone_number || "").replace(/[^\d+]/g, ""),
              formatPhone(d.phone_number),
            ),
          ],
          ["location-icon", "School/Affiliation", infoValue(d.school)],
          ...accountRows(d),
        ])}
        ${renderCertSection(d.cert, fullName)}
        ${renderAccountActions(d)}
    `;

  bindCertActions();
  bindAccountActions(d);
  document.dispatchEvent(
    new CustomEvent("researcher-modal-rendered", {
      detail: { status: d.cert ? d.cert.status : null, review: false },
    }),
  );
}

// ===== Certificate review (opened from the viewer) =====
function renderCertReview(d, fullName) {
  document.getElementById("researcherModalName").textContent =
    CERT_REVIEW_TITLES[d.cert.status] || "";

  document.getElementById("researcherModalBody").innerHTML = `
        ${renderInfoRows([
          ["account-icon", "Name", infoValue(fullName)],
          ["records-icon", "Researcher type", infoValue(d.researcher_type)],
          ["location-icon", "School/Affiliation", infoValue(d.school)],
          ...accountRows(d),
        ])}
        <section class="researcher-cert">
            ${renderCertPreview(d.cert, fullName)}
            ${renderCertActions(d.cert)}
        </section>
    `;

  bindCertActions();
  document.dispatchEvent(
    new CustomEvent("researcher-modal-rendered", {
      detail: { status: d.cert.status, review: true },
    }),
  );
}

function renderCertPreview(cert, fullName) {
  const url = escapeHtml(cert.file_url);
  const alt = escapeHtml("Training certificate of " + fullName);

  return cert.is_image
    ? `<button type="button" class="cert-preview" data-zoom-src="${url}" data-zoom-alt="${alt}" aria-label="Zoom in on the training certificate">
            <img src="${url}" alt="${alt}">
        </button>`
    : `<p class="cert-preview-link"><a class="researcher-name-link" href="${url}" target="_blank" rel="noopener">Open certificate (PDF)</a></p>`;
}

// ===== Training certificate =====
function renderCertActions(cert) {
  return cert.status === "pending"
    ? `
        <div class="modal-actions" id="certActions">
            <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="certRejectStartBtn">Request resubmission</button>
            <button class="confirm-modal-btn confirm-modal-ok" type="button" id="certVerifyBtn">Verify</button>
        </div>
        <div id="certRejectPanel" class="cert-reject-panel" hidden>
            <label for="certRejectNote">Reason <span class="required-asterisk">*</span></label>
            <textarea id="certRejectNote" class="return-comment-textarea" rows="3" maxlength="500" placeholder="e.g. the certificate is expired or the name does not match" required></textarea>
            <div class="modal-actions">
                <button class="confirm-modal-btn confirm-modal-cancel" type="button" id="certRejectBackBtn">Cancel</button>
                <button class="confirm-modal-btn confirm-modal-ok confirm-modal-ok-danger" type="button" id="certRejectSubmitBtn">Send request</button>
            </div>
        </div>
    `
    : "";
}

function renderCertSection(cert, fullName) {
  const badge =
    cert && cert.status !== "verified"
      ? `<span class="status-dot payment-badge ${CERT_STATUS_BADGES[cert.status] || ""}">${escapeHtml(CERT_STATUS_LABELS[cert.status] || cert.status)}</span>`
      : "";
  const content = cert
    ? `${renderCertPreview(cert, fullName)}${renderCertReviewNote(cert)}${renderCertActions(cert)}`
    : '<p class="researcher-cert-empty">No certificate on file.</p>';

  return `
        <section class="researcher-cert" aria-labelledby="researcherCertTitle">
            <div class="researcher-cert-head">
                <h3 class="researcher-section-title" id="researcherCertTitle">Training certificate</h3>
                ${badge}
            </div>
            ${content}
        </section>
    `;
}

function renderCertReviewNote(cert) {
  const labels = CERT_REVIEW_LABELS[cert.status];
  if (!labels || !cert.reviewed_at) return "";

  const reviewer = formatActor(cert.reviewer_name, cert.reviewer_role) || "N/A";
  return `<p class="researcher-cert-note">${escapeHtml(labels[0])} ${escapeHtml(formatDateTime(cert.reviewed_at))} by ${escapeHtml(reviewer)}</p>`;
}

function bindCertActions() {
  const actions = document.getElementById("certActions");
  if (!actions) return;

  const rejectPanel = document.getElementById("certRejectPanel");
  const noteInput = document.getElementById("certRejectNote");

  document.getElementById("certVerifyBtn").addEventListener("click", (e) => {
    rejectPanel.hidden = true;
    confirmAction(
      `Verify the training certificate of ${confirmName(currentResearcherName)}?`,
      {
        okText: "Verify",
        cancelText: "Cancel",
      },
    ).then((ok) => ok && submitCertReview("certverify", "", e.target));
  });

  document
    .getElementById("certRejectStartBtn")
    .addEventListener("click", () => {
      rejectPanel.hidden = false;
      rejectPanel.scrollIntoView({ block: "nearest", behavior: "smooth" });
      noteInput.focus({ preventScroll: true });
    });

  document.getElementById("certRejectBackBtn").addEventListener("click", () => {
    rejectPanel.hidden = true;
  });

  document
    .getElementById("certRejectSubmitBtn")
    .addEventListener("click", (e) => {
      const note = noteInput.value.trim();
      if (!note) {
        showToast("Please tell the researcher what is wrong.", "error");
        noteInput.focus();
        return;
      }
      submitCertReview("certreject", note, e.target);
    });
}

function settleCertDecision(status) {
  if (certReviewMode && status === "verified") closeResearcherModal();
  else openResearcherModal(currentResearcherId, "", { review: certReviewMode });
  announceCertState(status);
}

async function submitCertReview(endpoint, note, btn) {
  const formData = new FormData();
  formData.append("csrf_token", CSRF_TOKEN);
  formData.append("user_id", currentResearcherId);
  formData.append("note", note);

  setButtonBusy(btn, true, "Saving...");
  try {
    const res = await fetch(ROOT_URL + "/personnel/" + endpoint, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
    });
    const data = await res.json();
    if (data.queued) {
      setButtonBusy(btn, false);
      notifyQueued();
      return;
    }
    if (!data.ok) {
      setButtonBusy(btn, false);
      showToast(data.message ?? data.error ?? "Could not save.", "error");
      if (data.cert_status) settleCertDecision(data.cert_status);
      return;
    }
    showToast(data.message, "success");
    settleCertDecision(data.cert_status);
  } catch (err) {
    setButtonBusy(btn, false);
    showToast("Network error. Please try again.", "error");
  }
}

// ===== Account status (administrative staff only) =====
function renderAccountActions(d) {
  if (!d.can_manage) return "";

  const deactivated = d.status === "deactivated";
  return `
        <div class="modal-actions researcher-actions" id="accountActions">
            <button class="confirm-modal-btn confirm-modal-ok${deactivated ? "" : " confirm-modal-ok-danger"}" type="button" id="accountStatusBtn">${deactivated ? "Reactivate account" : "Deactivate account"}</button>
        </div>
    `;
}

function bindAccountActions(d) {
  const btn = document.getElementById("accountStatusBtn");
  if (!btn) return;

  const deactivating = d.status !== "deactivated";
  btn.addEventListener("click", () => {
    const name = confirmName(currentResearcherName);
    confirmAction(
      deactivating
        ? `Deactivate the account of ${name}? They will be signed out and cannot sign in until administrative staff reactivate it. Their protocols and records stay as they are.`
        : `Reactivate the account of ${name}? They can sign in again.`,
      {
        okText: deactivating ? "Deactivate" : "Reactivate",
        cancelText: "Cancel",
        danger: deactivating,
      },
    ).then(
      (ok) =>
        ok &&
        submitAccountStatus(
          deactivating ? "deactivate_account" : "reactivate_account",
          btn,
        ),
    );
  });
}

async function submitAccountStatus(endpoint, btn) {
  const formData = new FormData();
  formData.append("csrf_token", CSRF_TOKEN);
  formData.append("user_id", currentResearcherId);

  setButtonBusy(btn, true, "Saving...");
  try {
    const res = await fetch(ROOT_URL + "/personnel/" + endpoint, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: formData,
    });
    const data = await res.json();
    if (!data.ok) {
      setButtonBusy(btn, false);
      showToast(data.message ?? data.error ?? "Could not save.", "error");
      return;
    }
    showToast(data.message, "success");
    accountStatusChanged = true;
    openResearcherModal(currentResearcherId, currentResearcherName);
  } catch (err) {
    setButtonBusy(btn, false);
    showToast("Network error. Please try again.", "error");
  }
}

// ===== Close researcher modal (reloads after an account change so Archived regroups) =====
function closeResearcherModal() {
  if (!researcherBackdrop.classList.contains("open")) return;
  researcherBackdrop.classList.remove("open");
  if (accountStatusChanged) window.location.reload();
  else if (researcherLastFocus) researcherLastFocus.focus();
}

researcherBackdrop.addEventListener("click", (e) => {
  if (e.target === researcherBackdrop) closeResearcherModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeResearcherModal();
});

document.querySelectorAll(".researcher-name-link").forEach((btn) => {
  btn.addEventListener("click", () => {
    openResearcherModal(btn.dataset.userId, btn.dataset.researcherName);
  });
});

// ===== Open from a notification or email link (?open_researcher=ID) =====
(function openResearcherFromUrl() {
  const url = new URL(window.location);
  const userId = url.searchParams.get("open_researcher");
  if (!/^\d+$/.test(userId || "")) return;

  url.searchParams.delete("open_researcher");
  history.replaceState(null, "", url);
  openResearcherModal(userId);
})();
