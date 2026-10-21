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

  // ===== Full-screen busy overlay (blocks the page during long tasks, e.g. PDF conversion) =====
  let busyOverlayEl = null;
  let busyOverlayTimer = null;

  window.showBusyOverlay = function (opts) {
    opts = opts || {};
    if (!busyOverlayEl) {
      busyOverlayEl = document.createElement("div");
      busyOverlayEl.className = "busy-overlay";
      busyOverlayEl.setAttribute("role", "alertdialog");
      busyOverlayEl.setAttribute("aria-live", "assertive");
      busyOverlayEl.setAttribute("aria-busy", "true");
      busyOverlayEl.innerHTML =
        '<div class="busy-overlay-card">' +
        '<div class="busy-overlay-spinner" aria-hidden="true"></div>' +
        '<p class="busy-overlay-title"></p>' +
        '<p class="busy-overlay-text"></p>' +
        '<div class="busy-overlay-track" aria-hidden="true"><div class="busy-overlay-fill"></div></div>' +
        '<p class="busy-overlay-elapsed"></p>' +
        "</div>";
      document.body.appendChild(busyOverlayEl);
    }

    window.updateBusyOverlay(opts);
    busyOverlayEl.classList.add("open");
    document.body.classList.add("busy-overlay-lock");

    const started = Date.now();
    const elapsedEl = busyOverlayEl.querySelector(".busy-overlay-elapsed");
    elapsedEl.textContent = "";
    clearInterval(busyOverlayTimer);
    busyOverlayTimer = setInterval(() => {
      const secs = Math.floor((Date.now() - started) / 1000);
      elapsedEl.textContent =
        secs >= 5 ? "Still working\u2026 " + secs + "s" : "";
    }, 1000);
  };

  window.updateBusyOverlay = function (opts) {
    if (!busyOverlayEl) return;
    opts = opts || {};
    if (opts.title !== undefined) {
      busyOverlayEl.querySelector(".busy-overlay-title").textContent =
        opts.title;
    }
    if (opts.text !== undefined) {
      busyOverlayEl.querySelector(".busy-overlay-text").textContent = opts.text;
    }
    const track = busyOverlayEl.querySelector(".busy-overlay-track");
    const fill = busyOverlayEl.querySelector(".busy-overlay-fill");
    if (typeof opts.percent === "number") {
      track.classList.add("is-determinate");
      fill.style.width = Math.max(0, Math.min(100, opts.percent)) + "%";
    } else if (opts.percent === null) {
      track.classList.remove("is-determinate");
      fill.style.width = "";
    }
  };

  window.hideBusyOverlay = function () {
    clearInterval(busyOverlayTimer);
    if (busyOverlayEl) busyOverlayEl.classList.remove("open");
    document.body.classList.remove("busy-overlay-lock");
  };

  window.isBusyOverlayOpen = function () {
    return !!(busyOverlayEl && busyOverlayEl.classList.contains("open"));
  };

  // ===== Protocol file conversion overlay (shared by apply, amendment and re-submit) =====
  window.showConversionOverlay = function () {
    window.showBusyOverlay({
      title: "Uploading your file\u2026",
      text: "Please keep this page open.",
      percent: 0,
    });
  };

  window.updateConversionOverlay = function (pct) {
    if (pct >= 100) {
      window.updateBusyOverlay({
        title: "Converting to PDF\u2026",
        text: "This usually takes a few seconds, but can take up to a minute for larger documents. Please don\u2019t close or refresh this page.",
        percent: null,
      });
    } else {
      window.updateBusyOverlay({ percent: pct });
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
      converting() {
        bar.classList.add("is-converting");
        pct.innerHTML = '<span class="btn-busy-spinner"></span>';
        if (subEl) subEl.textContent = "Converting to PDF, please wait\u2026";
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

  // ===== Guides (see includes/guide.php) =====
  const guideOpeners = {};

  document.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-guide-start]");
    const open = btn && guideOpeners[btn.dataset.guideStart];
    if (!open) return;
    closeMoreMenu();
    open();
  });

  function bindGuide(modal) {
    const guideId = modal.dataset.guide;
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

    const restart = bindGuideSteps(modal, close);

    function openGuide() {
      restart();
      modal.classList.add("open");
      closeBtn.focus();
    }

    guideOpeners[guideId] = openGuide;

    // Auto-open once per browser
    if (modal.hasAttribute("data-show-once")) {
      try {
        const seenKey = `iacuc-guide-seen-${guideId}`;
        if (!localStorage.getItem(seenKey)) {
          localStorage.setItem(seenKey, "1");
          openGuide();
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

  // Step-by-step spotlight guide; steps without a target stay centered. Returns a restart function.
  function bindGuideSteps(modal, close) {
    const mobileQuery = window.matchMedia("(max-width: 768px)");
    if (mobileQuery.matches) {
      modal
        .querySelectorAll("[data-guide-desktop-only]")
        .forEach((step) => step.remove());
    }
    const steps = modal.querySelectorAll("[data-welcome-step]");
    steps.forEach((step, i) => {
      step.querySelector(".welcome-step-label").textContent =
        `Step ${i + 1} of ${steps.length}`;
    });
    const card = modal.querySelector(".welcome-modal-card");
    const spotlight = modal.querySelector(".guide-spotlight");
    const dotsEl = modal.querySelector(".welcome-dots");
    const backBtn = modal.querySelector("[data-guide-back]");
    const nextBtn = modal.querySelector("[data-guide-next]");
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
        (mobileQuery.matches && step.dataset.guideTargetMobile) ||
        step.dataset.guideTarget
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
      modal.classList.toggle("guide-targeting", !!box);
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
      nextBtn.querySelector("[data-guide-next-label]").textContent =
        n === steps.length - 1 ? "Finish" : "Next";

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
  document.querySelectorAll(".guide-modal").forEach(bindGuide);
})();

// ===== Shake: feedback when a click can't dismiss something =====
window.shake = function (el) {
  if (!el) return;
  el.classList.remove("shake");
  void el.offsetWidth; // restarts the animation if it is already running
  el.classList.add("shake");
  el.addEventListener("animationend", () => el.classList.remove("shake"), {
    once: true,
  });
};

// ===== Modals marked data-static ignore clicks on the backdrop, and shake instead =====
document.addEventListener(
  "click",
  (e) => {
    if (!e.target.matches(".modal-backdrop[data-static]")) return;
    e.stopPropagation();
    shake(e.target.querySelector(".modal-card") ?? e.target.firstElementChild);
  },
  true,
);

// ===== Ask before closing a changed form (modals marked data-confirm-discard) =====
["input", "change"].forEach((type) => {
  document.addEventListener(type, (e) => {
    const modal =
      e.isTrusted && e.target.closest(".modal-backdrop[data-confirm-discard]");
    if (modal) modal.dataset.changed = "true";
  });
});

document.addEventListener(
  "click",
  async (e) => {
    const closeBtn = e.target.closest(".modal-close");
    const modal =
      closeBtn && closeBtn.closest(".modal-backdrop[data-confirm-discard]");
    if (!modal || modal.dataset.changed !== "true") return;
    e.stopPropagation();
    if (
      await confirmAction("Discard your changes?", {
        okText: "Discard",
        danger: true,
      })
    ) {
      delete modal.dataset.changed;
      closeBtn.click();
    }
  },
  true,
);

document
  .querySelectorAll(".modal-backdrop[data-confirm-discard]")
  .forEach((modal) => {
    new MutationObserver(() => delete modal.dataset.changed).observe(modal, {
      attributes: true,
      attributeFilter: ["class"],
    });
  });
