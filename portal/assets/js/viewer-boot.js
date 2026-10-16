// ===== Viewer boot =====
// Runs last: opens modals from notification links, loads the PDF, wires resize.

// ===== Auto-open from notification/email links =====
(function handleAutoOpenFromLink() {
  const openTarget = new URLSearchParams(window.location.search).get("open");
  if (
    openTarget === "deletion_request" &&
    typeof openDeletionReviewModal === "function"
  ) {
    openDeletionReviewModal();
  } else if (
    openTarget === "deletion_rejected" &&
    typeof openDeletionRejectedModal === "function"
  ) {
    openDeletionRejectedModal();
  } else if (openTarget === "signed_scan" && !IS_SIGNED_SCAN) {
    if (SIGNED_SCAN_VIEWER_URL) window.location.replace(SIGNED_SCAN_VIEWER_URL);
  }
})();

loadPdf();

let _resizeTimer;
window.addEventListener("resize", () => {
  clearTimeout(_resizeTimer);
  _resizeTimer = setTimeout(renderAnnotations, 100);
});

// ===== Download Clearance: also open images in a new tab =====
document.querySelectorAll(".download-clearance-btn").forEach((btn) => {
  btn.addEventListener("click", () => {
    if (btn.dataset.isImage === "1" && btn.dataset.viewHref) {
      window.open(btn.dataset.viewHref, "_blank", "noopener");
    }
  });
});
