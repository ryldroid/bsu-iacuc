// ===== Rejected training certificate modal =====
const certRejectedBackdrop = document.getElementById("certRejectedBackdrop");

function openCertRejectedModal() {
  certRejectedBackdrop.classList.add("open");
}

function closeCertRejectedModal() {
  certRejectedBackdrop.classList.remove("open");
}

function resubmitCertFromRejectedModal() {
  closeCertRejectedModal();
  openCertResubmitModal();
}

certRejectedBackdrop.addEventListener("click", (e) => {
  if (e.target === certRejectedBackdrop) closeCertRejectedModal();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeCertRejectedModal();
});

// ===== Open from a notification or email link (?open=cert) =====
(function openCertRejectedFromUrl() {
  const url = new URL(window.location);
  if (url.searchParams.get("open") !== "cert") return;

  url.searchParams.delete("open");
  history.replaceState(null, "", url);
  openCertRejectedModal();
})();
