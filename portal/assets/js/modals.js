// ===== Date formatters =====
function formatDate(value) {
  return new Date(value).toLocaleDateString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatTime(value) {
  return new Date(value)
    .toLocaleTimeString("en-PH", { hour: "numeric", minute: "2-digit" })
    .toUpperCase();
}

function formatDateTime(value) {
  return formatDate(value) + " \u00b7 " + formatTime(value);
}

(function () {
  let modalEl = null;
  let messageEl = null;
  let okBtn = null;
  let cancelBtn = null;
  let lastFocusedEl = null;
  let activeResolve = null;

  // ===== Confirm dialog =====
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
      const focusable = cancelBtn.hidden ? [okBtn] : [cancelBtn, okBtn];
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
    cancelBtn.hidden = !!options.hideCancel;
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

  // ===== Busy button state =====
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

  // ===== Announcement modal =====
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

  // ===== Image zoom =====
  let zoomBackdrop = null;
  let zoomImg = null;
  let zoomLastFocused = null;

  function buildZoomModal() {
    if (zoomBackdrop) return;

    zoomBackdrop = document.createElement("div");
    zoomBackdrop.className = "modal-backdrop image-zoom-backdrop";
    zoomBackdrop.innerHTML = `
      <div class="image-zoom-card">
        <button type="button" class="image-zoom-close" aria-label="Close"><svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon" /></svg></button>
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

  // ===== Auto-confirm links & forms =====
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

  // ===== Tours (see includes/tour.php) =====
  function bindTour(modal) {
    const tourId = modal.dataset.tour;
    const closeBtn = modal.querySelector(".modal-close");

    function close() {
      modal.classList.remove("open");
      setMobileMenu(false);
    }

    closeBtn.addEventListener("click", close);

    modal.addEventListener("click", (e) => {
      if (e.target === modal) close();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("open")) close();
    });

    const restart = bindTourSteps(modal, close);

    function openTour() {
      restart();
      modal.classList.add("open");
      closeBtn.focus();
    }

    document
      .querySelectorAll(`[data-tour-start="${tourId}"]`)
      .forEach((btn) => {
        btn.addEventListener("click", () => {
          document
            .getElementById("account-dropdown")
            ?.classList.remove("active");
          document
            .querySelector(".my-account-dropdown")
            ?.setAttribute("aria-expanded", "false");
          openTour();
        });
      });

    // Auto-open once per browser
    if (modal.hasAttribute("data-show-once")) {
      try {
        const seenKey = `iacuc-tour-seen-${tourId}`;
        if (!localStorage.getItem(seenKey)) {
          localStorage.setItem(seenKey, "1");
          openTour();
        }
      } catch (e) {}
    }

    if (modal.classList.contains("open")) closeBtn.focus();
  }

  // Open or close the mobile page menu (no-ops on desktop)
  function setMobileMenu(open) {
    const menuButton = document.querySelector(".mobile-menu");
    const isOpen = menuButton?.getAttribute("aria-expanded") === "true";
    if (menuButton && isOpen !== open) menuButton.click();
  }

  // Step-by-step spotlight tour; steps without a target stay centered. Returns a restart function.
  function bindTourSteps(modal, close) {
    const steps = modal.querySelectorAll("[data-welcome-step]");
    const card = modal.querySelector(".welcome-modal-card");
    const spotlight = modal.querySelector(".tour-spotlight");
    const dotsEl = modal.querySelector(".welcome-dots");
    const backBtn = modal.querySelector("[data-tour-back]");
    const nextBtn = modal.querySelector("[data-tour-next]");
    const mobileQuery = window.matchMedia("(max-width: 768px)");
    const SPOT_PAD = 6;
    const CARD_GAP = 12;
    const SETTLE_MS = 300;
    let current = 0;
    let settleTimer = null;

    dotsEl.innerHTML = Array.from(
      steps,
      () => '<span class="welcome-dot"></span>',
    ).join("");
    const dots = dotsEl.children;

    function targetSelector(step) {
      return (
        (mobileQuery.matches && step.dataset.tourTargetMobile) ||
        step.dataset.tourTarget
      );
    }

    // Visible elements the selector matches; ending it with ":first-visible" keeps only the first one
    function targetElements(selector) {
      if (!selector) return [];
      const found = Array.from(
        document.querySelectorAll(selector.replace(/:first-visible$/, "")),
      ).filter((el) => {
        const r = el.getBoundingClientRect();
        return r.width && r.height;
      });
      return selector.endsWith(":first-visible") ? found.slice(0, 1) : found;
    }

    // Bounding box covering every target element
    function targetBox(selector) {
      const rects = targetElements(selector).map((el) =>
        el.getBoundingClientRect(),
      );
      if (!rects.length) return null;
      return {
        top: Math.min(...rects.map((r) => r.top)) - SPOT_PAD,
        left: Math.min(...rects.map((r) => r.left)) - SPOT_PAD,
        right: Math.max(...rects.map((r) => r.right)) + SPOT_PAD,
        bottom: Math.max(...rects.map((r) => r.bottom)) + SPOT_PAD,
      };
    }

    function place() {
      const box = targetBox(targetSelector(steps[current]));
      modal.classList.toggle("tour-targeting", !!box);
      if (!box) {
        card.style.top = card.style.left = "";
        return;
      }

      spotlight.style.top = `${box.top}px`;
      spotlight.style.left = `${box.left}px`;
      spotlight.style.width = `${box.right - box.left}px`;
      spotlight.style.height = `${box.bottom - box.top}px`;

      // Card goes beside the target, else below it, else above it
      const cardBox = card.getBoundingClientRect();
      const clamp = (n, min, max) => Math.max(min, Math.min(n, max));
      let top;
      let left;
      if (
        box.right + CARD_GAP + cardBox.width <=
        window.innerWidth - CARD_GAP
      ) {
        left = box.right + CARD_GAP;
        top = box.top;
      } else {
        left = (box.left + box.right - cardBox.width) / 2;
        top =
          box.bottom + CARD_GAP + cardBox.height <=
          window.innerHeight - CARD_GAP
            ? box.bottom + CARD_GAP
            : box.top - CARD_GAP - cardBox.height;
      }
      card.style.left = `${clamp(left, CARD_GAP, window.innerWidth - cardBox.width - CARD_GAP)}px`;
      card.style.top = `${clamp(top, CARD_GAP, window.innerHeight - cardBox.height - CARD_GAP)}px`;
    }

    function show(n) {
      current = n;
      steps.forEach((el, i) => {
        el.hidden = i !== n;
      });
      Array.from(dots).forEach((d, i) => d.classList.toggle("active", i === n));
      backBtn.hidden = n === 0;
      nextBtn.textContent = n === steps.length - 1 ? "Finish" : "Next";

      // Header must be visible, the mobile menu open only for steps inside it, and the target on screen
      window.scrollTo({ top: 0, behavior: "instant" });
      const selector = targetSelector(steps[n]);
      const target = targetElements(selector)[0];
      setMobileMenu(!!target?.closest("#mobileNav"));
      if (target) {
        const rect = target.getBoundingClientRect();
        if (rect.top < 0 || rect.bottom > window.innerHeight) {
          target.scrollIntoView({ block: "center", behavior: "instant" });
        }
      }

      place();
      clearTimeout(settleTimer);
      settleTimer = setTimeout(place, SETTLE_MS);
      if (modal.classList.contains("open"))
        nextBtn.focus({ preventScroll: true });
    }

    backBtn.addEventListener("click", () => show(current - 1));
    nextBtn.addEventListener("click", () => {
      if (current === steps.length - 1) close();
      else show(current + 1);
    });
    window.addEventListener("resize", () => {
      if (modal.classList.contains("open")) place();
    });

    show(0);
    return () => show(0);
  }

  // ===== Start up =====
  bindAutoConfirm();
  bindImageZoomTriggers();
  document.querySelectorAll(".tour-modal").forEach(bindTour);
})();
