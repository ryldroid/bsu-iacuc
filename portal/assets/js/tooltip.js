// ===== SHARED TOOLTIP =====
// One black tooltip for the whole site. Any element with data-tooltip="..." gets it,
// and so does any element with a plain title="..." (the title is moved into
// data-tooltip so the browser's own grey hover box never appears).
// Hover on desktop, keyboard focus, press-and-hold on touch. A click or tap on a non-clickable item
// keeps its tooltip open until the next click, tap, scroll or Escape.
(function () {
  "use strict";

  const SHOW_DELAY_MS = 400;
  const HOLD_MS = 450;
  const LINGER_MS = 1500;
  const GAP = 8;
  const EDGE = 8;
  const SKIP_TAGS = ["IFRAME", "OPTION", "OPTGROUP"];
  const INTERACTIVE =
    "a, button, input, select, textarea, label, summary, [role='button']";

  const canHover = window.matchMedia("(hover: hover)");

  let tip = null;
  let current = null;
  let showTimer = null;
  let holdTimer = null;
  let hideTimer = null;
  let lastX = 0;
  let pinned = null;
  let unpinOnClick = null;
  let heldShown = false;
  let suppressClick = false;

  function getTip() {
    if (tip) return tip;
    tip = document.createElement("div");
    tip.className = "app-tooltip";
    tip.id = "appTooltip";
    tip.setAttribute("role", "tooltip");
    document.body.appendChild(tip);
    return tip;
  }

  // Moves a native title into data-tooltip (keeps screen readers working too)
  function adopt(el) {
    if (!el.hasAttribute || !el.hasAttribute("title")) return;
    if (SKIP_TAGS.includes(el.tagName)) return;
    const text = (el.getAttribute("title") || "").trim();
    el.removeAttribute("title");
    if (!text) {
      el.removeAttribute("data-tooltip");
      return;
    }
    el.setAttribute("data-tooltip", text);
    if (
      !el.hasAttribute("aria-label") &&
      !el.hasAttribute("aria-labelledby") &&
      !el.textContent.trim()
    ) {
      el.setAttribute("aria-label", text);
    }
  }

  function findTarget(node) {
    if (!node || !node.closest) return null;
    const el = node.closest("[data-tooltip],[title]");
    if (!el) return null;
    adopt(el);
    return el.getAttribute("data-tooltip") ? el : null;
  }

  function place(el) {
    const t = getTip();
    const r = el.getBoundingClientRect();
    const tw = t.offsetWidth;
    const th = t.offsetHeight;
    const vw = document.documentElement.clientWidth;
    const vh = document.documentElement.clientHeight;

    // Long elements (titles, wide rows): anchor to the pointer instead of their middle
    const anchorX = r.width > 300 && lastX ? lastX : r.left + r.width / 2;
    let left = anchorX - tw / 2;
    left = Math.max(EDGE, Math.min(left, vw - tw - EDGE));

    let top = r.bottom + GAP;
    if (top + th > vh - EDGE) top = r.top - th - GAP;
    top = Math.max(EDGE, Math.min(top, vh - th - EDGE));

    t.style.left = Math.round(left) + "px";
    t.style.top = Math.round(top) + "px";
  }

  function show(el) {
    if (!el.isConnected) return;
    // Header dropdown buttons hide their tooltip while the menu is open
    if (el.getAttribute("aria-expanded") === "true") return;
    const text = el.getAttribute("data-tooltip");
    if (!text) return;

    const t = getTip();
    t.textContent = text;
    place(el);
    t.classList.add("is-visible");
    el.setAttribute("aria-describedby", t.id);
    current = el;
  }

  function hide() {
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
    if (tip) tip.classList.remove("is-visible");
    if (current) current.removeAttribute("aria-describedby");
    current = null;
    pinned = null;
  }

  // ----- Mouse -----
  document.addEventListener("mouseover", (e) => {
    if (!canHover.matches || pinned) return;
    lastX = e.clientX;
    const el = findTarget(e.target);
    if (el === current) return;
    hide();
    if (el) showTimer = setTimeout(() => show(el), SHOW_DELAY_MS);
  });

  document.addEventListener("mouseout", (e) => {
    if (!e.relatedTarget) hide();
  });

  document.addEventListener("mousedown", (e) => {
    unpinOnClick = findTarget(e.target) === pinned ? pinned : null;
    hide();
  });

  document.addEventListener("click", (e) => {
    const el = findTarget(e.target);
    const unpin = el === unpinOnClick;
    unpinOnClick = null;
    if (!el || unpin || el.matches(INTERACTIVE)) return;
    show(el);
    pinned = el;
  });
  window.addEventListener("scroll", hide, true);
  window.addEventListener("resize", hide);
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") hide();
  });

  // ----- Keyboard -----
  document.addEventListener("focusin", (e) => {
    const el = findTarget(e.target);
    if (!el || !el.matches(":focus-visible")) return;
    hide();
    show(el);
  });
  document.addEventListener("focusout", hide);

  // ----- Touch: press-and-hold anywhere (a tap on non-clickable items is handled by the click listener) -----
  document.addEventListener(
    "touchstart",
    (e) => {
      clearTimeout(holdTimer);
      heldShown = false;
      const el = findTarget(e.target);
      if (!el) return hide();
      hide();
      holdTimer = setTimeout(() => {
        show(el);
        heldShown = true;
        suppressClick = true;
      }, HOLD_MS);
    },
    { passive: true },
  );

  document.addEventListener("touchmove", () => clearTimeout(holdTimer), {
    passive: true,
  });

  document.addEventListener("touchend", () => {
    clearTimeout(holdTimer);
    if (heldShown) {
      hideTimer = setTimeout(hide, LINGER_MS);
      setTimeout(() => (suppressClick = false), 400);
    }
  });

  document.addEventListener("touchcancel", () => {
    clearTimeout(holdTimer);
    hide();
  });

  // A press-and-hold that showed a tooltip must not also click the button
  document.addEventListener(
    "click",
    (e) => {
      if (
        suppressClick &&
        e.target.closest &&
        e.target.closest("[data-tooltip]")
      ) {
        e.preventDefault();
        e.stopPropagation();
        suppressClick = false;
      }
    },
    true,
  );

  document.addEventListener("contextmenu", (e) => {
    if ((heldShown || holdTimer) && !canHover.matches) e.preventDefault();
  });

  // ----- Convert titles early (existing and added later) so the native box never shows -----
  function adoptAll(root) {
    if (root.nodeType !== 1) return;
    adopt(root);
    root.querySelectorAll("[title]").forEach(adopt);
  }

  function start() {
    adoptAll(document.body);
    new MutationObserver((mutations) => {
      for (const m of mutations) {
        if (m.type === "attributes") adopt(m.target);
        else m.addedNodes.forEach(adoptAll);
      }
    }).observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["title"],
    });
  }

  if (document.body) start();
  else document.addEventListener("DOMContentLoaded", start);
})();
