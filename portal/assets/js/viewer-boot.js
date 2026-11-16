// ===== Viewer boot =====
// Runs last: loads the PDF, wires resize.

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
