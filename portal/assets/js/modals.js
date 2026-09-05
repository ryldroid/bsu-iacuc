(function () {
  let modalEl = null;
  let messageEl = null;
  let okBtn = null;
  let cancelBtn = null;
  let lastFocusedEl = null;
  let activeResolve = null;

  function buildModal() {
    if (modalEl) return;

    modalEl = document.createElement("div");
    modalEl.className = "confirm-modal-overlay";
    modalEl.setAttribute("aria-hidden", "true");

    modalEl.innerHTML = `
      <div class="confirm-modal" role="alertdialog" aria-modal="true"
           aria-labelledby="confirm-modal-message" tabindex="-1">
        <p id="confirm-modal-message" class="confirm-modal-message"></p>
        <div class="confirm-modal-actions">
          <button type="button" class="confirm-modal-btn confirm-modal-cancel">Cancel</button>
          <button type="button" class="confirm-modal-btn confirm-modal-ok">Yes</button>
        </div>
      </div>
    `;

    document.body.appendChild(modalEl);

    messageEl = modalEl.querySelector(".confirm-modal-message");
    okBtn = modalEl.querySelector(".confirm-modal-ok");
    cancelBtn = modalEl.querySelector(".confirm-modal-cancel");

    okBtn.addEventListener("click", () => settle(true));
    cancelBtn.addEventListener("click", () => settle(false));

    modalEl.addEventListener("click", (e) => {
      if (e.target === modalEl) settle(false);
    });

    modalEl.addEventListener("keydown", onKeydown);
  }

  function onKeydown(e) {
    if (e.key === "Escape") {
      e.preventDefault();
      settle(false);
      return;
    }

    if (e.key === "Tab") {
      const focusable = [cancelBtn, okBtn];
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }

  function settle(result) {
    if (!activeResolve) return;

    modalEl.classList.remove("is-open");
    modalEl.setAttribute("aria-hidden", "true");

    const resolve = activeResolve;
    activeResolve = null;

    if (lastFocusedEl && typeof lastFocusedEl.focus === "function") {
      lastFocusedEl.focus();
    }

    resolve(result);
  }

  window.confirmAction = function (message, options) {
    buildModal();

    options = options || {};
    messageEl.textContent = message;
    okBtn.textContent = options.okText || "Yes";
    cancelBtn.textContent = options.cancelText || "Cancel";
    okBtn.classList.toggle("confirm-modal-ok-danger", !!options.danger);

    lastFocusedEl = document.activeElement;

    return new Promise((resolve) => {
      activeResolve = resolve;
      modalEl.setAttribute("aria-hidden", "false");
      modalEl.classList.add("is-open");
      requestAnimationFrame(() => {
        (options.danger ? cancelBtn : okBtn).focus();
      });
    });
  };

  function bindAutoConfirm() {
    document.addEventListener("click", async (e) => {
      const link = e.target.closest("a[data-confirm-message]");
      if (link) {
        e.preventDefault();
        const ok = await confirmAction(link.dataset.confirmMessage, {
          okText: link.dataset.confirmOkText,
          cancelText: link.dataset.confirmCancelText,
          danger: link.dataset.confirmDanger === "true",
        });
        if (ok) window.location.href = link.href;
        return;
      }

      const btn = e.target.closest("button[data-confirm-message]");
      if (btn) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const ok = await confirmAction(btn.dataset.confirmMessage, {
          okText: btn.dataset.confirmOkText,
          cancelText: btn.dataset.confirmCancelText,
          danger: btn.dataset.confirmDanger === "true",
        });
        if (ok) {
          btn.dispatchEvent(
            new CustomEvent("confirm:accepted", { bubbles: true }),
          );
        }
        return;
      }
    });

    document.addEventListener("submit", async (e) => {
      const form = e.target;
      if (!form.matches || !form.matches("form[data-confirm-message]")) return;
      if (form.dataset.confirmed === "true") return;

      e.preventDefault();
      const ok = await confirmAction(form.dataset.confirmMessage, {
        okText: form.dataset.confirmOkText,
        cancelText: form.dataset.confirmCancelText,
        danger: form.dataset.confirmDanger === "true",
      });
      if (ok) {
        form.dataset.confirmed = "true";
        form.requestSubmit ? form.requestSubmit() : form.submit();
      }
    });
  }

  bindAutoConfirm();
})();
