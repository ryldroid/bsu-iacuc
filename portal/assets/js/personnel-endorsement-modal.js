// ===== Endorsement proof modal =====
// One photo of the endorsement can cover one protocol (row button) or several
// (bulk button). Needs the inbox page globals: protocolsData, ROOT_URL, CSRF_TOKEN
// and rowProtocol() from personnel-protocol-actions.js.

const ENDORSE_API = ROOT_URL + "/apply/endorse";
const endorsementModal = document.getElementById("endorsementModalBackdrop");
let endorsementProtocolIds = [];

function openEndorsementModal(protocolIds, skippedCount = 0) {
  endorsementProtocolIds = protocolIds;

  const list = document.getElementById("endorsementProtocolList");
  list.replaceChildren(
    ...protocolIds.map((id) => {
      const protocol = rowProtocol(id);
      const item = document.createElement("li");
      item.textContent = protocol?.research_title ?? "Untitled Protocol";
      const researcher = document.createElement("span");
      researcher.className = "endorsement-protocol-researcher";
      researcher.textContent = [protocol?.first_name, protocol?.last_name]
        .filter(Boolean)
        .join(" ");
      item.appendChild(researcher);
      return item;
    }),
  );

  const many = protocolIds.length > 1;
  const skippedNote =
    skippedCount > 0
      ? ` ${skippedCount} selected protocol${skippedCount === 1 ? " is" : "s are"} not ready to endorse and will be skipped.`
      : "";
  document.getElementById("endorsementNotice").textContent =
    `Upload a photo or screenshot showing that ${many ? "these protocols were" : "this protocol was"} endorsed to DA-RFO-CAR. ${many ? "They move" : "It moves"} to Endorsed once the proof is uploaded.${skippedNote}`;

  document.getElementById("endorsement_proof_file").value = "";
  resetEndorsementFilePicker();
  document.getElementById("endorsementError").hidden = true;
  document.getElementById("endorsementProgress").innerHTML = "";
  endorsementModal.classList.add("open");
}

function closeEndorsementModal() {
  endorsementModal.classList.remove("open");
  endorsementProtocolIds = [];
}

endorsementModal?.addEventListener("click", (e) => {
  if (e.target === endorsementModal) closeEndorsementModal();
});

document.addEventListener("keydown", (e) => {
  if (
    e.key === "Escape" &&
    !e.defaultPrevented &&
    endorsementModal?.classList.contains("open")
  )
    closeEndorsementModal();
});

function resetEndorsementFilePicker() {
  document
    .getElementById("endorsementFilePreviewBtn")
    .classList.add("is-hidden");
  document.getElementById("endorsementFilePickerLabel").textContent = "Upload";
  const subtitle = document.getElementById("endorsementFileSubtitle");
  subtitle.textContent = "Photo or screenshot \u00b7 JPG or PNG \u00b7 max 10 MB";
  subtitle.classList.remove("done");
}

function handleEndorsementFileChange(input) {
  syncPreviewButton(input, "endorsementFilePreviewBtn");
  const subtitle = document.getElementById("endorsementFileSubtitle");
  if (input.files.length) {
    document.getElementById("endorsementFilePickerLabel").textContent =
      "Replace";
    subtitle.textContent = input.files[0].name;
    subtitle.classList.add("done");
  } else {
    resetEndorsementFilePicker();
  }
}

async function submitEndorsement() {
  const fileInput = document.getElementById("endorsement_proof_file");
  const errBox = document.getElementById("endorsementError");
  const btn = document.getElementById("endorsementSubmitBtn");

  if (!fileInput.files.length) {
    errBox.textContent = "Please select the proof of endorsement.";
    errBox.hidden = false;
    return;
  }
  errBox.hidden = true;

  const count = endorsementProtocolIds.length;
  const target =
    count === 1
      ? confirmName(rowProtocol(endorsementProtocolIds[0])?.research_title ?? "") ||
        "this protocol"
      : `${count} protocols`;
  const ok = await confirmAction(
    `Mark ${target} as endorsed using this proof? Double-check that payment is verified, the IPN is correct and the image clearly shows the endorsement before proceeding.`,
    { okText: "Mark as Endorsed", cancelText: "Cancel" },
  );
  if (!ok) return;

  setButtonBusy(btn, true, "Uploading...");
  const progressContainer = document.getElementById("endorsementProgress");
  progressContainer.innerHTML = "";
  const bar = createUploadProgressBar(progressContainer);

  const formData = new FormData();
  formData.append("protocol_ids", endorsementProtocolIds.join(","));
  if (count === 1) formData.append("protocol_id", endorsementProtocolIds[0]);
  formData.append("endorsement_proof_file", fileInput.files[0]);
  formData.append("csrf_token", CSRF_TOKEN);

  try {
    const data = await uploadWithProgress(ENDORSE_API, formData, {
      headers: { ...csrfHeaders() },
      onProgress: (pct) => bar.update(pct),
    });

    if (data.queued) {
      closeEndorsementModal();
      notifyQueued();
      setButtonBusy(btn, false);
      bar.remove();
    } else if (data.success) {
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
