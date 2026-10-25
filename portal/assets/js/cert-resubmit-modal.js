// ===== Resubmit training certificate modal =====
const certResubmitBackdrop = document.getElementById("certResubmitBackdrop");

function openCertResubmitModal() {
  certResubmitBackdrop.classList.add("open");
}

function closeCertResubmitModal() {
  certResubmitBackdrop.classList.remove("open");
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeCertResubmitModal();
});
