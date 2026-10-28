// ===== Researcher details modal =====
const researcherBackdrop = document.getElementById("researcherModalBackdrop");
const researcherModalBody = document.getElementById("researcherModalBody");
let currentResearcherId = null;
let certReviewMode = false;

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
  currentResearcherId = userId;
  certReviewMode = !!options.review;
  document.getElementById("researcherModalLabel").textContent = certReviewMode
    ? "Training certificate"
    : "Researcher";
  document.getElementById("researcherModalName").textContent = certReviewMode
    ? ""
    : fallbackName || "";
  document.getElementById("researcherModalBody").innerHTML =
    '<p class="helper history-loading">Loading…</p>';
  researcherBackdrop.classList.add("open");

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

function renderDetailRows(rows) {
  return `<div class="researcher-detail-rows">${rows
    .map(
      ([label, value]) => `
        <div class="researcher-detail-row">
            <span class="researcher-detail-label">${escapeHtml(label)}</span>
            <span class="researcher-detail-value">${escapeHtml(value)}</span>
        </div>
    `,
    )
    .join("")}</div>`;
}

function capitalize(text) {
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : "N/A";
}

function renderResearcherDetails(d) {
  const fullName = (d.first_name + " " + d.last_name).trim();

  if (certReviewMode && d.cert) {
    renderCertReview(d, fullName);
    return;
  }

  document.getElementById("researcherModalName").textContent = fullName;

  const joined = d.created_at ? formatDate(d.created_at) : "N/A";

  const rows = renderDetailRows([
    ["Username", d.username],
    ["Email", d.email],
    ["Phone", formatPhone(d.phone_number) || "N/A"],
    ["School", d.school || "N/A"],
    ["Role", capitalize(d.role)],
    ["Account status", capitalize(d.status)],
    ["Joined", joined],
  ]);

  const protocolsHtml =
    d.protocols && d.protocols.length
      ? d.protocols
          .map(
            (p) => `
            <li class="researcher-protocol-item">
                <span class="researcher-protocol-title">${escapeHtml(p.research_title)}</span>
                <span class="researcher-protocol-meta">
                    ${[p.reference_no, p.status, p.submitted_at && formatDate(p.submitted_at)].filter(Boolean).map(escapeHtml).join(" &middot; ")}
                </span>
            </li>
        `,
          )
          .join("")
      : '<li class="researcher-protocol-item researcher-protocol-empty">No protocols submitted yet.</li>';

  document.getElementById("researcherModalBody").innerHTML = `
        ${rows}
        ${renderCertSection(d.cert)}
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Protocols (${d.protocol_count})</p>
            <ul class="researcher-protocol-list">${protocolsHtml}</ul>
        </div>
    `;

  bindCertActions();
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
        ${renderDetailRows([
          ["Researcher Name", fullName],
          ["School", d.school || "N/A"],
          ["Role", capitalize(d.role)],
        ])}
        ${renderCertPreview(d.cert, fullName)}
        ${renderCertActions(d.cert)}
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

function renderCertSection(cert) {
  if (!cert) {
    return `
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Training certificate</p>
            <ul class="researcher-protocol-list">
                <li class="researcher-protocol-item researcher-protocol-empty">No certificate on file.</li>
            </ul>
        </div>
    `;
  }

  return `
        <div class="researcher-protocol-section">
            <p class="researcher-protocol-heading">Training certificate</p>
            <div class="researcher-detail-rows">
                <div class="researcher-detail-row">
                    <span class="researcher-detail-label">Status</span>
                    <span class="researcher-detail-value"><span class="payment-badge ${CERT_STATUS_BADGES[cert.status] || ""}">${escapeHtml(CERT_STATUS_LABELS[cert.status] || cert.status)}</span></span>
                </div>
                <div class="researcher-detail-row">
                    <span class="researcher-detail-label">Current file</span>
                    <span class="researcher-detail-value"><a class="researcher-name-link" href="${escapeHtml(cert.file_url)}" target="_blank" rel="noopener">View certificate</a></span>
                </div>
            </div>
            ${renderCertActions(cert)}
        </div>
    `;
}

function bindCertActions() {
  const actions = document.getElementById("certActions");
  if (!actions) return;

  const rejectPanel = document.getElementById("certRejectPanel");
  const noteInput = document.getElementById("certRejectNote");

  document.getElementById("certVerifyBtn").addEventListener("click", (e) => {
    rejectPanel.hidden = true;
    confirmAction("Verify this training certificate?", {
      okText: "Verify",
      cancelText: "Cancel",
    }).then((ok) => ok && submitCertReview("certverify", "", e.target));
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

// ===== Close researcher modal =====
function closeResearcherModal() {
  researcherBackdrop.classList.remove("open");
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
