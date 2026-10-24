// ===== ALERTS & TOASTS (includes/alert.php, includes/flash.php) =====
// Every toast fades after its data-autodismiss delay (5s for errors, 4s for success) or when dismissed.

(function () {
  function dismiss(el) {
    el.style.maxHeight = el.offsetHeight + "px";
    void el.offsetHeight; // lock the current height so it can animate down to 0
    el.classList.add("is-leaving");
    setTimeout(() => el.remove(), 350);
  }

  function setupAlert(el) {
    el.querySelector(".form-alert-close")?.addEventListener("click", () =>
      dismiss(el),
    );

    const delay = parseInt(el.dataset.autodismiss, 10);
    if (delay) setTimeout(() => dismiss(el), delay);
  }

  document.querySelectorAll(".form-alert").forEach(setupAlert);

  // `into` puts the alert inside a form instead of the toast stack; `autodismiss: 0` keeps it until dismissed
  window.showToast = function (
    message,
    type = "success",
    { into, autodismiss } = {},
  ) {
    const stack = into ?? document.getElementById("toast-stack");
    const messages = [].concat(message).filter(Boolean);
    if (!stack || !messages.length) return;

    const isError = type === "error";
    const el = document.createElement("div");
    el.className = "form-alert form-alert--" + (isError ? "error" : "success");
    el.setAttribute("role", isError ? "alert" : "status");
    // Same delays as includes/alert.php: 5s for errors, 4s for success
    el.dataset.autodismiss = autodismiss ?? (isError ? 5000 : 4000);
    el.innerHTML =
      '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#' +
      (isError ? "alert-triangle-icon" : "check-circle-icon") +
      '" /></svg>' +
      '<div class="form-alert-body"></div>' +
      '<button type="button" class="form-alert-close" aria-label="Dismiss message">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg></button>';
    const body = el.querySelector(".form-alert-body");
    const isList = messages.length > 1;
    const list = isList ? body.appendChild(document.createElement("ul")) : body;
    messages.forEach((text) => {
      const item = document.createElement(isList ? "li" : "p");
      item.textContent = text;
      list.appendChild(item);
    });
    stack.append(el);
    setupAlert(el);
  };
})();
