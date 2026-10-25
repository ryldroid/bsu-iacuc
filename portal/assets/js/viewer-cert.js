// ===== Viewer: training certificate check for personnel =====
// Opens the shared researcher modal when the certificate needs a decision. It also opens on the
// original submission to say whether the certificate was verified or rejected.
// While it is pending the modal cannot be dismissed, so someone must verify or reject it.
// The decision belongs to the researcher, so whoever makes it first settles it for everyone.
let certState = CERT_STATUS;
const finishReviewBtn = document.getElementById("btnFinishReview");

const CERT_MODAL_INTRO = {
  pending:
    "Review this training certificate before you continue. The researcher is notified of the result.",
  rejected: IS_STAFF
    ? "This training certificate was rejected. The researcher has been asked to resubmit it. You can still view this protocol."
    : "This training certificate was rejected. The researcher has been asked to resubmit it. You can still read and comment on this protocol, but you cannot finish the review or return it until the certificate is verified.",
  verified: "This training certificate is verified.",
};

function syncFinishReviewButton() {
  if (!finishReviewBtn) return;
  const verified = certState === "verified";
  finishReviewBtn.disabled = !verified;
  finishReviewBtn.title = verified
    ? ""
    : "The researcher's training certificate must be verified first.";
}

document.addEventListener("cert-reviewed", (e) => {
  certState = e.detail.status;
  syncFinishReviewButton();
});

document.addEventListener("researcher-modal-rendered", (e) => {
  const status = e.detail.status;
  if (!CERT_MODAL_INTRO[status]) return;

  const body = document.getElementById("researcherModalBody");
  body.insertAdjacentHTML(
    "afterbegin",
    '<p class="helper">' + escapeHtml(CERT_MODAL_INTRO[status]) + "</p>",
  );

  if (status === "pending") return;

  const label =
    status === "rejected" && !IS_STAFF
      ? "Proceed to review"
      : status === "verified"
        ? "Continue"
        : "Close";
  body.insertAdjacentHTML(
    "beforeend",
    '<div class="modal-actions"><button class="confirm-modal-btn confirm-modal-ok" type="button" id="certModalContinueBtn">' +
      escapeHtml(label) +
      "</button></div>",
  );
  document
    .getElementById("certModalContinueBtn")
    .addEventListener("click", closeResearcherModal);
});

document.addEventListener("DOMContentLoaded", () => {
  if (certState === "pending") {
    setResearcherModalLocked(true);
    openResearcherModal(RESEARCHER_ID, RESEARCHER_NAME);
  } else if (certState && IS_ORIGINAL_SUBMISSION) {
    openResearcherModal(RESEARCHER_ID, RESEARCHER_NAME);
  }
});
