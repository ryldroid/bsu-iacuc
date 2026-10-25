// ===== Signed scan modal =====
const signedScanModal = document.getElementById("signedScanModalBackdrop");
let currentSignedScanProtocolId = null;

// ===== Signed scan modal script =====
function openSignedScanModal(protocolId, title) {
  currentSignedScanProtocolId = protocolId;
  document.getElementById("signedScanSubtitle").textContent = title;
  document.getElementById("signed_scan_file").value = "";
  resetSignedScanFilePicker();
  document.getElementById("signedScanError").hidden = true;
  document.getElementById("signedScanProgress").innerHTML = "";
  signedScanModal.classList.add("open");
}

function closeSignedScanModal() {
  signedScanModal.classList.remove("open");
  currentSignedScanProtocolId = null;
}

signedScanModal.addEventListener("click", (e) => {
  if (e.target === signedScanModal) closeSignedScanModal();
});

function resetSignedScanFilePicker() {
  document
    .getElementById("signedScanFilePreviewBtn")
    .classList.add("is-hidden");
  document.getElementById("signedScanFilePickerLabel").textContent = "Upload";
  const subtitle = document.getElementById("signedScanFileSubtitle");
  subtitle.textContent = "PDF only · max 10 MB";
  subtitle.classList.remove("done");
}

function handleSignedScanFileChange(input) {
  syncPreviewButton(input, "signedScanFilePreviewBtn");
  const subtitle = document.getElementById("signedScanFileSubtitle");
  if (input.files.length) {
    document.getElementById("signedScanFilePickerLabel").textContent =
      "Replace";
    subtitle.textContent = input.files[0].name;
    subtitle.classList.add("done");
  } else {
    resetSignedScanFilePicker();
  }
}

async function submitSignedScanUpload() {
  const fileInput = document.getElementById("signed_scan_file");
  const errBox = document.getElementById("signedScanError");
  const btn = document.getElementById("signedScanSubmitBtn");

  if (!fileInput.files.length) {
    errBox.textContent = "Please select a file.";
    errBox.hidden = false;
    return;
  }

  setButtonBusy(btn, true, "Uploading...");
  errBox.hidden = true;

  const progressContainer = document.getElementById("signedScanProgress");
  progressContainer.innerHTML = "";
  const bar = createUploadProgressBar(progressContainer);

  const formData = new FormData();
  formData.append("protocol_id", currentSignedScanProtocolId);
  formData.append("signed_scan_file", fileInput.files[0]);
  formData.append("csrf_token", CSRF_TOKEN);

  try {
    const data = await uploadWithProgress(SIGNED_SCAN_UPLOAD_API, formData, {
      headers: {
        "X-CSRF-Token": CSRF_TOKEN,
      },
      onProgress: (pct) => bar.update(pct),
    });

    if (data.success) {
      window.location.reload();
    } else {
      errBox.textContent = data.error ?? "Upload failed. Please try again.";
      errBox.hidden = false;
      setButtonBusy(btn, false);
      bar.remove();
    }
  } catch (err) {
    errBox.textContent = err.message || "Network error. Please try again.";
    errBox.hidden = false;
    setButtonBusy(btn, false);
    bar.remove();
  }
}
