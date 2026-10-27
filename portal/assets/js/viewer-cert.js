// ===== Viewer: training certificate check for personnel =====
// Opens the shared researcher modal when the certificate needs a decision. It also opens on the
// original submission when the certificate was rejected.
// While it is pending the modal cannot be dismissed, so someone must verify or reject it.
// The decision belongs to the researcher, so whoever makes it first settles it for everyone.
let certState = CERT_STATUS;
const finishReviewBtn = document.getElementById("btnFinishReview");
const verifiedBadge = document.getElementById("certVerifiedBadge");

const REJECT_NOTE = IS_STAFF
  ? "The researcher is asked to resubmit their certificate. While waiting, the reviewer can still review and comment on this protocol. Their comments are saved. Finishing the review is unavailable until the certificate is verified."
  : "The researcher is asked to resubmit their certificate. While waiting, you can still review and comment on this protocol. Your comments are saved. Finishing the review is unavailable until the certificate is verified.";

function syncFinishReviewButton() {
  if (!finishReviewBtn) return;
  const verified = certState === "verified";
  finishReviewBtn.setAttribute("aria-disabled", String(!verified));
  finishReviewBtn.title = verified
    ? ""
    : "The researcher's training certificate must be verified first.";
}

document.addEventListener("cert-reviewed", (e) => {
  certState = e.detail.status;
  syncFinishReviewButton();
  if (verifiedBadge) {
    verifiedBadge.hidden = certState !== "verified";
    verifiedBadge.title = "Verified just now.";
  }
});

document.addEventListener("researcher-modal-rendered", (e) => {
  const status = e.detail.status;
  if (!e.detail.review) return;

  if (status === "pending") {
    document
      .querySelector("#certRejectPanel .modal-actions")
      .insertAdjacentHTML(
        "beforebegin",
        '<p class="helper">' + escapeHtml(REJECT_NOTE) + "</p>",
      );
  }

  if (status !== "rejected") return;

  const label = IS_STAFF ? "Close" : "Proceed to review";
  document
    .getElementById("researcherModalBody")
    .insertAdjacentHTML(
      "beforeend",
      '<div class="modal-actions"><button class="confirm-modal-btn confirm-modal-ok" type="button" id="certModalContinueBtn">' +
        label +
        "</button></div>",
    );
  document
    .getElementById("certModalContinueBtn")
    .addEventListener("click", async () => {
      const proceed =
        IS_STAFF ||
        (await confirmAction(
          "You may still read and comment on this protocol, but you cannot finish the review or return it until the certificate is verified. Proceed?",
          { okText: "Yes" },
        ));
      if (proceed) closeResearcherModal();
    });
});

document.addEventListener("DOMContentLoaded", () => {
  if (certState === "pending") {
    setResearcherModalLocked(true);
    openResearcherModal(RESEARCHER_ID, RESEARCHER_NAME, { review: true });
  } else if (certState === "rejected" && IS_ORIGINAL_SUBMISSION) {
    openResearcherModal(RESEARCHER_ID, RESEARCHER_NAME, { review: true });
  }
});
