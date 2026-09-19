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

  window.setButtonBusy = function (btn, busy, busyText) {
    if (!btn) return;

    if (busy) {
      if (btn.dataset.originalHtml === undefined) {
        btn.dataset.originalHtml = btn.innerHTML;
      }
      btn.disabled = true;
      btn.classList.add("btn-busy");
      btn.innerHTML =
        '<span class="btn-busy-spinner"></span>' +
        (busyText || "Processing...");
    } else {
      btn.disabled = false;
      btn.classList.remove("btn-busy");
      if (btn.dataset.originalHtml !== undefined) {
        btn.innerHTML = btn.dataset.originalHtml;
        delete btn.dataset.originalHtml;
      }
    }
  };

  // ===== Upload progress (real % for file uploads, via XHR since fetch
  // can't report upload progress) =====

  // Renders a progress bar into `container` (an existing, normally-empty
  // element). Returns { update(percent), remove() }.
  window.createUploadProgressBar = function (container) {
    if (!container) return { update() {}, remove() {} };

    const bar = document.createElement("div");
    bar.className = "upload-progress";
    bar.innerHTML =
      '<div class="upload-progress-track"><div class="upload-progress-fill"></div></div>' +
      '<span class="upload-progress-pct">0%</span>';
    container.appendChild(bar);

    const fill = bar.querySelector(".upload-progress-fill");
    const pct = bar.querySelector(".upload-progress-pct");

    return {
      update(percent) {
        const clamped = Math.max(0, Math.min(100, Math.round(percent)));
        fill.style.width = clamped + "%";
        pct.textContent = clamped + "%";
      },
      remove() {
        bar.remove();
      },
    };
  };

  // Drop-in replacement for `fetch(url, {method:'POST', body:formData}).then(r=>r.json())`
  // that reports real upload progress via XMLHttpRequest. Resolves with the
  // parsed JSON body; rejects with an Error (message safe to show the user)
  // on network failure or a non-JSON response.
  window.uploadWithProgress = function (url, formData, options) {
    options = options || {};
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();
      xhr.open("POST", url);

      const headers = options.headers || {};
      Object.keys(headers).forEach((key) => {
        xhr.setRequestHeader(key, headers[key]);
      });

      if (typeof options.onProgress === "function") {
        xhr.upload.addEventListener("progress", (e) => {
          if (e.lengthComputable) {
            options.onProgress((e.loaded / e.total) * 100);
          }
        });
      }

      xhr.addEventListener("load", () => {
        let data;
        try {
          data = JSON.parse(xhr.responseText);
        } catch (e) {
          reject(new Error("Unexpected server response. Please try again."));
          return;
        }
        resolve(data);
      });

      xhr.addEventListener("error", () => {
        reject(
          new Error(
            "Network error. Please check your connection and try again.",
          ),
        );
      });

      xhr.addEventListener("abort", () => {
        reject(new Error("Upload cancelled."));
      });

      xhr.send(formData);
    });
  };

  window.initAnnouncementModal = function (config) {
    const modal = document.getElementById(config.modalId);
    if (!modal) return;

    const modalBody = document.getElementById(config.bodyId);
    const closeBtn = document.getElementById(config.closeId);
    let lastFocused = null;

    function open(trigger) {
      const tpl = document.getElementById(trigger.dataset.annModal);
      if (!tpl) return;

      modalBody.innerHTML = "";
      modalBody.appendChild(tpl.content.cloneNode(true));

      const heading = modalBody.querySelector(".announcement-modal-title");
      modal.setAttribute(
        "aria-label",
        heading ? heading.textContent : "Announcement",
      );

      lastFocused = document.activeElement;
      modal.classList.add("open");
      closeBtn.focus();
    }

    function close() {
      modal.classList.remove("open");
      modalBody.innerHTML = "";
      if (lastFocused && typeof lastFocused.focus === "function")
        lastFocused.focus();
    }

    (config.triggers || document.querySelectorAll("[data-ann-modal]")).forEach(
      (btn) => {
        btn.addEventListener("click", () => open(btn));
      },
    );

    closeBtn.addEventListener("click", close);

    modal.addEventListener("click", (e) => {
      if (e.target === modal) close();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("open")) close();
    });
  };

  let zoomBackdrop = null;
  let zoomImg = null;
  let zoomLastFocused = null;

  function buildZoomModal() {
    if (zoomBackdrop) return;

    zoomBackdrop = document.createElement("div");
    zoomBackdrop.className = "modal-backdrop image-zoom-backdrop";
    zoomBackdrop.innerHTML = `
      <div class="image-zoom-card">
        <button type="button" class="image-zoom-close" aria-label="Close">&times;</button>
        <img class="image-zoom-img" src="" alt="">
      </div>
    `;
    document.body.appendChild(zoomBackdrop);

    zoomImg = zoomBackdrop.querySelector(".image-zoom-img");
    zoomBackdrop
      .querySelector(".image-zoom-close")
      .addEventListener("click", closeImageZoom);

    zoomBackdrop.addEventListener("click", (e) => {
      if (e.target === zoomBackdrop) closeImageZoom();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && zoomBackdrop.classList.contains("open"))
        closeImageZoom();
    });
  }

  function closeImageZoom() {
    if (!zoomBackdrop) return;
    zoomBackdrop.classList.remove("open");
    zoomImg.src = "";
    if (zoomLastFocused && typeof zoomLastFocused.focus === "function")
      zoomLastFocused.focus();
  }

  window.openImageZoom = function (url, alt) {
    if (!url) return;
    buildZoomModal();
    zoomImg.src = url;
    zoomImg.alt = alt || "";
    zoomLastFocused = document.activeElement;
    zoomBackdrop.classList.add("open");
    zoomBackdrop.querySelector(".image-zoom-close").focus();
  };

  window.closeImageZoom = closeImageZoom;

  function bindImageZoomTriggers() {
    document.addEventListener("click", (e) => {
      const trigger = e.target.closest("[data-zoom-src]");
      if (!trigger) return;
      e.preventDefault();
      e.stopPropagation();
      openImageZoom(trigger.dataset.zoomSrc, trigger.dataset.zoomAlt || "");
    });
  }

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
          setButtonBusy(btn, true);
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
  bindImageZoomTriggers();
})();
