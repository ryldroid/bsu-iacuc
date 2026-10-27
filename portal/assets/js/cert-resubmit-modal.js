// ===== Resubmit training certificate modal =====
const certResubmitBackdrop = document.getElementById("certResubmitBackdrop");
const certResubmitForm = certResubmitBackdrop.querySelector("form");
const certResubmitSubmitBtn = document.getElementById("certResubmitSubmitBtn");

function resetCertResubmitPicker() {
  certResubmitForm.reset();
  document.getElementById("certResubmitPickerLabel").textContent = "Upload";
  const subtitle = document.getElementById("certResubmitFileSubtitle");
  subtitle.textContent = "PDF, JPG or PNG · max 10 MB";
  subtitle.classList.remove("done");
  document.getElementById("certResubmitPreviewBtn").classList.add("is-hidden");
  certResubmitSubmitBtn.disabled = true;
}

function handleCertResubmitFileChange(input) {
  syncPreviewButton(input, "certResubmitPreviewBtn");
  if (!input.files.length) {
    resetCertResubmitPicker();
    return;
  }
  const subtitle = document.getElementById("certResubmitFileSubtitle");
  document.getElementById("certResubmitPickerLabel").textContent = "Replace";
  subtitle.textContent = input.files[0].name;
  subtitle.classList.add("done");
  certResubmitSubmitBtn.disabled = false;
}

function openCertResubmitModal() {
  resetCertResubmitPicker();
  certResubmitBackdrop.classList.add("open");
}

function closeCertResubmitModal() {
  certResubmitBackdrop.classList.remove("open");
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeCertResubmitModal();
});
