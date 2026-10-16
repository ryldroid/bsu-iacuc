// ===== DROPDOWNS AND SIDEBAR NAVIGATION =====

const accountButton = document.querySelector(".header-account");
const moreButton = document.querySelector(".header-more");
const moreMenu = document.querySelector("#header-menu");
const sidebar = document.querySelector(".nav-sidebar");
const mobileMenu = document.querySelector(".mobile-menu");
const backdrop = document.querySelector("#sidebar-backdrop");
const mobileNav = document.querySelector('nav[aria-label="Mobile navigation"]');
const media = window.matchMedia("(max-width: 768px)");
const accountDrawer = document.querySelector("#account-drawer");
const accountFrame = accountDrawer?.querySelector("iframe");
const accountRequired = new URLSearchParams(location.search).has("required");
window.accountRequired = accountRequired;
document.documentElement.classList.toggle("account-required", accountRequired);

function setBackdrop(active) {
  if (!active && accountRequired) return;
  backdrop?.classList.toggle("active", active);
}
window.setBackdrop = setBackdrop;

backdrop?.addEventListener("click", () => {
  if (accountRequired) return;
  closeSidebar();
  closeAccountDrawer();
  closeMoreMenu();
});
mobileMenu?.addEventListener("click", showSidebar);
media.addEventListener("change", (e) => updateNavbar(e));

// ===== Sidebar open / close =====
function openSidebar() {
  sidebar.classList.add("show");
  sidebar.removeAttribute("inert");
  sidebar.querySelector("a").focus();
  setBackdrop(true);

  mobileMenu.setAttribute("aria-expanded", "true");
  mobileNav.removeAttribute("aria-hidden");
}

function closeSidebar() {
  if (!sidebar || !mobileMenu) return;
  sidebar.classList.remove("show");
  sidebar.setAttribute("inert", "");
  setBackdrop(false);

  mobileMenu.setAttribute("aria-expanded", "false");
  mobileNav.setAttribute("aria-hidden", "true");
  mobileMenu.focus();
}

function showSidebar() {
  const isOpen = sidebar.classList.contains("show");

  closeMoreMenu();

  if (isOpen) {
    closeSidebar();
  } else {
    document.dispatchEvent(
      new CustomEvent("header-dropdown-open", { detail: "sidebar" }),
    );
    openSidebar();
  }
}

// ===== Account drawer =====
function openAccountDrawer() {
  document.dispatchEvent(
    new CustomEvent("header-dropdown-open", { detail: "account" }),
  );
  setHeaderHidden(false);

  accountFrame.src = accountFrame.dataset.src;
  accountDrawer.classList.add("show");
  accountDrawer.removeAttribute("inert");
  accountButton?.setAttribute("aria-expanded", "true");
  setBackdrop(true);
  accountFrame.focus();
}

function closeAccountDrawer(restoreFocus = true) {
  if (accountRequired || !accountDrawer?.classList.contains("show")) return;
  accountDrawer.classList.remove("show");
  accountDrawer.setAttribute("inert", "");
  accountButton?.setAttribute("aria-expanded", "false");
  accountFrame.src = "about:blank";
  setBackdrop(false);
  if (restoreFocus) accountButton.focus();
}

window.closeAccountDrawer = closeAccountDrawer;

accountButton?.addEventListener("click", () =>
  accountDrawer.classList.contains("show")
    ? closeAccountDrawer()
    : openAccountDrawer(),
);

// Clicking anywhere outside the panel closes it, like the other dropdowns
document.addEventListener("click", (event) => {
  if (
    accountButton?.contains(event.target) ||
    accountDrawer?.contains(event.target)
  )
    return;
  closeAccountDrawer(false);
});

// ===== More menu =====
document.addEventListener("header-dropdown-open", (event) => {
  if (event.detail !== "sidebar") closeSidebar();
  if (event.detail !== "more") closeMoreMenu();
  if (event.detail !== "account") closeAccountDrawer(false);
});

function closeMoreMenu() {
  if (!moreMenu?.classList.contains("active")) return;
  moreMenu.classList.remove("active");
  moreButton?.setAttribute("aria-expanded", "false");
  setBackdrop(false);
}

if (moreButton && moreMenu) {
  moreButton.addEventListener("click", () => {
    const isOpen = moreMenu.classList.toggle("active");
    moreButton.setAttribute("aria-expanded", isOpen);
    if (isOpen) {
      document.dispatchEvent(
        new CustomEvent("header-dropdown-open", { detail: "more" }),
      );
      setBackdrop(true);
    } else {
      setBackdrop(false);
    }
  });

  document.addEventListener("click", (event) => {
    if (
      !moreButton.contains(event.target) &&
      !moreMenu.contains(event.target)
    ) {
      closeMoreMenu();
    }
  });
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeSidebar();
    closeAccountDrawer();
    closeMoreMenu();
  }
});

// ===== Edge-aware dropdown positioning =====
function positionEdgeAwareDropdown(anchorEl, panelEl, margin = 12) {
  if (!anchorEl || !panelEl) return;
  panelEl.style.left = "0px";
  panelEl.style.right = "auto";
  const anchorRect = anchorEl.getBoundingClientRect();
  const panelRect = panelEl.getBoundingClientRect();
  const maxLeft = window.innerWidth - margin - panelRect.width;
  const minLeft = margin;
  const desiredLeft = anchorRect.left;
  const clampedLeft = Math.min(Math.max(desiredLeft, minLeft), maxLeft);
  panelEl.style.left = `${clampedLeft - anchorRect.left}px`;
}
window.positionEdgeAwareDropdown = positionEdgeAwareDropdown;

// ===== Highlight current page link =====
document
  .querySelectorAll("header nav a, aside nav a, #mobileNav a")
  .forEach((link) => {
    const linkPath = link.pathname.replace(/\/+$/, "");
    const currentPath = window.location.pathname.replace(/\/+$/, "");
    if (linkPath === currentPath) {
      link.classList.add("active-link");
      link.setAttribute("aria-current", "page");
    }
  });

// ===== VERIFY EMAIL BANNER =====

const verifyBanner = document.getElementById("verifyEmailBanner");
const PINNED_BASE_OFFSET = 16;

function syncPinnedOffset() {
  if (!verifyBanner || !verifyBanner.isConnected) {
    document.documentElement.style.removeProperty("--pinned-top-offset");
    return;
  }
  const offset = verifyBanner.offsetHeight + PINNED_BASE_OFFSET;
  document.documentElement.style.setProperty(
    "--pinned-top-offset",
    `${offset}px`,
  );
}

document
  .getElementById("verifyEmailBannerClose")
  ?.addEventListener("click", () => {
    document.getElementById("verifyEmailBanner")?.remove();
    syncPinnedOffset();
  });

if (verifyBanner) {
  syncPinnedOffset();
  window.addEventListener("resize", syncPinnedOffset);
}

// ===== Mobile / desktop navbar switch =====
function updateNavbar(e) {
  if (!sidebar || !mobileMenu) return;
  const isMobile = e.matches;
  if (isMobile) {
    sidebar.setAttribute("inert", "");
  } else {
    sidebar.removeAttribute("inert");
    closeSidebar();
  }
}

updateNavbar(media);

// ===== HIDE HEADER ON SCROLL =====

const mainHeader = document.querySelector("header");
let lastScrollY = window.scrollY;
const SCROLL_THRESHOLD = 10;

function syncHeaderOffset() {
  const visible =
    mainHeader && !mainHeader.classList.contains("header--hidden")
      ? mainHeader.offsetHeight
      : 0;
  document.documentElement.style.setProperty("--header-offset", `${visible}px`);
}

function setHeaderHidden(hidden) {
  if (!mainHeader || mainHeader.classList.contains("header--hidden") === hidden)
    return;
  mainHeader.classList.toggle("header--hidden", hidden);
  syncHeaderOffset();
}

function handleHeaderScroll() {
  if (!mainHeader) return;

  if (sidebar?.classList.contains("show")) return;

  // Keep the header (and the dropdowns anchored to it) in place while one is open
  if (
    accountDrawer?.classList.contains("show") ||
    moreMenu?.classList.contains("active") ||
    document.querySelector("#notif-dropdown.active")
  ) {
    lastScrollY = window.scrollY;
    return;
  }

  const currentScrollY = window.scrollY;
  const diff = currentScrollY - lastScrollY;

  if (Math.abs(diff) < SCROLL_THRESHOLD) return;

  if (currentScrollY <= 0) {
    setHeaderHidden(false);
  } else if (diff > 0) {
    setHeaderHidden(true);
  } else {
    setHeaderHidden(false);
  }

  lastScrollY = currentScrollY;
}

if (mainHeader) {
  syncHeaderOffset();
  window.addEventListener("scroll", handleHeaderScroll, { passive: true });
  window.addEventListener("resize", syncHeaderOffset);

  mainHeader.addEventListener("focusin", () => {
    setHeaderHidden(false);
    lastScrollY = window.scrollY;
  });
} else {
  syncHeaderOffset();
}

// ===== HEADER ICON TOOLTIPS: press-and-hold on touch screens =====
(function () {
  const HOLD_MS = 450;
  const LINGER_MS = 1500;
  let holdTimer = null;
  let hideTimer = null;
  let activeEl = null;
  let suppressClick = false;

  function clearTip() {
    clearTimeout(holdTimer);
    clearTimeout(hideTimer);
    if (activeEl) activeEl.classList.remove("tooltip-show");
    activeEl = null;
  }

  document.addEventListener(
    "touchstart",
    (e) => {
      const el = e.target.closest("header [data-tooltip]");
      if (!el) return clearTip();
      clearTip();
      holdTimer = setTimeout(() => {
        activeEl = el;
        el.classList.add("tooltip-show");
        suppressClick = true;
      }, HOLD_MS);
    },
    { passive: true },
  );

  function release() {
    clearTimeout(holdTimer);
    if (activeEl) {
      hideTimer = setTimeout(clearTip, LINGER_MS);
      setTimeout(() => (suppressClick = false), 400);
    }
  }
  document.addEventListener("touchend", release);
  document.addEventListener("touchcancel", clearTip);
  document.addEventListener("touchmove", () => clearTimeout(holdTimer), {
    passive: true,
  });

  // A long press shouldn't also trigger the button / link
  document.addEventListener(
    "click",
    (e) => {
      if (suppressClick && e.target.closest("header [data-tooltip]")) {
        e.preventDefault();
        e.stopPropagation();
        suppressClick = false;
      }
    },
    true,
  );

  // Block the browser's long-press menu on header icons
  document.addEventListener("contextmenu", (e) => {
    if (e.target.closest("header [data-tooltip]") && "ontouchstart" in window) {
      e.preventDefault();
    }
  });
})();

// Opens the panel when a redirect lands here with ?account=1
if (accountButton && new URLSearchParams(location.search).has("account")) {
  openAccountDrawer();
  history.replaceState(null, "", location.pathname);
}
