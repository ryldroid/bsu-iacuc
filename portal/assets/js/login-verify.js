// ===== Login page, "Verify Your Email" step =====
// Sends the link without leaving the page, then waits for the emailed link to be opened.
(() => {
  const form = document.querySelector("#pending-verification-form");
  if (!form) return;

  const alerts = form.querySelector("#verify-alerts");
  const button = form.querySelector("button[type=submit]");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const email = form.elements.email.value.trim();
    setButtonBusy(button, true, "Sending...");

    let ok = false;
    let message = "Something went wrong. Please try again.";
    try {
      const res = await fetch(form.action, {
        method: "POST",
        headers: { Accept: "application/json" },
        body: new FormData(form),
      });
      const data = await res.json();
      ok = res.ok;
      message = ok ? data.message : (data.errors ?? data.error);
    } catch (e) {}

    setButtonBusy(button, false);
    alerts.replaceChildren();
    showToast(
      ok
        ? `Link sent to ${email}. Kindly check your inbox or spam folder.`
        : message,
      ok ? "success" : "error",
      { into: alerts, autodismiss: ok ? 0 : undefined },
    );

    if (ok) {
      button.textContent = "Resend Link";
      button.dataset.originalHtml = button.innerHTML;
    }
  });

  // Reload once the link is opened; the server has signed this browser in by then, so the login page forwards on
  watchEmailVerification(form.dataset.statusUrl, () =>
    location.assign(`${location.origin}${location.pathname}`),
  );
})();
