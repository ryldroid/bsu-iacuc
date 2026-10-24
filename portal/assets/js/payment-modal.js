// ===== Payment modal =====
// Shared by the submissions list and the protocol viewer. The page defines
// CSRF_TOKEN, ROOT_URL and MUST_PAY_IN_PERSON; the viewer also defines PROTOCOL_ID.

const paymentModal = document.getElementById("paymentModalBackdrop");
let currentPaymentProtocolId = null;

function getSelectedPaymentMethod() {
  if (MUST_PAY_IN_PERSON) return "in_person";
  const checked = document.querySelector(
    'input[name="payment_method"]:checked',
  );
  return checked ? checked.value : "in_person";
}

function handlePaymentMethodChange() {
  const method = getSelectedPaymentMethod();
  document.getElementById("paymentInPersonPanel").hidden =
    method !== "in_person";
  document.getElementById("paymentOnlinePanel").hidden = method !== "online";
  document.getElementById("paymentModalSubmitLabel").textContent =
    method === "in_person" ? "Verify Payment" : "Submit Proof of Payment";
  document.getElementById("paymentModalError").hidden = true;
}

function openPaymentModal(protocolId = PROTOCOL_ID) {
  currentPaymentProtocolId = protocolId;
  const title = document.querySelector(
    `#protocol-${protocolId} [data-research-title]`,
  )?.dataset.researchTitle;
  if (title) document.getElementById("paymentModalTitle").textContent = title;
  document.getElementById("payment_proof_file").value = "";
  resetPaymentFilePicker();
  document.getElementById("confirm_in_person_paid").checked = false;
  if (!MUST_PAY_IN_PERSON) {
    document.querySelector(
      'input[name="payment_method"][value="in_person"]',
    ).checked = true;
  }
  document.getElementById("paymentProofProgress").innerHTML = "";
  handlePaymentMethodChange();
  paymentModal.classList.add("open");
}

function closePaymentModal() {
  paymentModal.classList.remove("open");
  currentPaymentProtocolId = null;
}

paymentModal.addEventListener("click", (e) => {
  if (e.target === paymentModal) closePaymentModal();
});

document.addEventListener("keydown", (e) => {
  if (
    e.key === "Escape" &&
    !e.defaultPrevented &&
    paymentModal.classList.contains("open")
  )
    closePaymentModal();
});

function resetPaymentFilePicker() {
  document.getElementById("paymentFilePickerLabel").textContent = "Upload";
  const subtitle = document.getElementById("paymentFileSubtitle");
  subtitle.textContent = "PDF or image · max 10 MB";
  subtitle.classList.remove("done");
}

function handlePaymentFileChange(input) {
  const subtitle = document.getElementById("paymentFileSubtitle");
  if (input.files.length) {
    document.getElementById("paymentFilePickerLabel").textContent = "Replace";
    subtitle.textContent = input.files[0].name;
    subtitle.classList.add("done");
  } else {
    resetPaymentFilePicker();
  }
}

async function submitPaymentProof() {
  const errBox = document.getElementById("paymentModalError");
  const btn = document.getElementById("paymentModalSubmitBtn");
  const method = getSelectedPaymentMethod();

  const formData = new FormData();
  formData.append("protocol_id", currentPaymentProtocolId);
  formData.append("payment_method", method);
  formData.append("csrf_token", CSRF_TOKEN);

  let confirmMessage;

  if (method === "in_person") {
    if (!document.getElementById("confirm_in_person_paid").checked) {
      errBox.textContent = "Please confirm that you have paid in person.";
      errBox.hidden = false;
      return;
    }
    formData.append("confirm_in_person", "1");
    confirmMessage =
      "Confirm that you have paid the Php 100.00 fee in person? Administrative staff will verify this.";
  } else {
    const fileInput = document.getElementById("payment_proof_file");
    if (!fileInput.files.length) {
      errBox.textContent = "Please select a file.";
      errBox.hidden = false;
      return;
    }
    formData.append("payment_proof_file", fileInput.files[0]);
    confirmMessage =
      "Submit this as your proof of payment? Make sure the file clearly shows the receipt or payment before continuing.";
  }

  errBox.hidden = true;

  const ok = await confirmAction(confirmMessage, {
    okText: method === "in_person" ? "Verify Payment" : "Submit Proof",
    cancelText: "Cancel",
  });
  if (!ok) return;

  setButtonBusy(
    btn,
    true,
    method === "in_person" ? "Submitting..." : "Uploading...",
  );

  const progressContainer = document.getElementById("paymentProofProgress");
  progressContainer.innerHTML = "";
  const bar =
    method === "online" ? createUploadProgressBar(progressContainer) : null;

  try {
    const data = await uploadWithProgress(
      ROOT_URL + "/apply/payment_proof",
      formData,
      {
        headers: {
          "X-CSRF-Token": CSRF_TOKEN,
        },
        onProgress: bar ? (pct) => bar.update(pct) : undefined,
      },
    );

    if (data.success) {
      window.location.reload();
    } else {
      errBox.textContent =
        data.error ?? "Something went wrong. Please try again.";
      errBox.hidden = false;
      setButtonBusy(btn, false);
      if (bar) bar.remove();
    }
  } catch (err) {
    errBox.textContent = err.message || "Network error. Please try again.";
    errBox.hidden = false;
    setButtonBusy(btn, false);
    if (bar) bar.remove();
  }
}
