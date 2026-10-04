(function () {
  // ===== Show / hide toggle =====
  function attachToggle(input) {
    if (input.dataset.toggleAttached === "1") return;
    input.dataset.toggleAttached = "1";

    const wrap = document.createElement("div");
    wrap.className = "password-field-wrap";

    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "password-toggle-btn";
    btn.setAttribute("aria-label", "Show password");
    btn.setAttribute("aria-pressed", "false");
    btn.innerHTML =
      '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<use href="#eye-icon"></use>' +
      "</svg>";
    wrap.appendChild(btn);

    btn.addEventListener("click", () => {
      const isShowing = input.type === "text";
      input.type = isShowing ? "password" : "text";
      btn.setAttribute(
        "aria-label",
        isShowing ? "Show password" : "Hide password",
      );
      btn.setAttribute("aria-pressed", isShowing ? "false" : "true");
      btn
        .querySelector("use")
        .setAttribute("href", isShowing ? "#eye-icon" : "#eye-off-icon");
      input.focus();
    });
  }

  // ===== Init =====
  function init() {
    document.querySelectorAll('input[type="password"]').forEach(attachToggle);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
