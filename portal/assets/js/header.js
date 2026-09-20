// ===== DROPDOWNS AND SIDEBAR NAVIGATION =====

const accountButton = document.querySelector(".my-account-dropdown");
const dropdown = document.querySelector("#account-dropdown");
const sidebar = document.querySelector(".nav-sidebar");
const mobileMenu = document.querySelector(".mobile-menu");
const backdrop = document.querySelector("#sidebar-backdrop");
const mobileNav = document.querySelector('nav[aria-label="Mobile navigation"]');
const media = window.matchMedia("(max-width: 768px)");

backdrop?.addEventListener("click", closeSidebar);
mobileMenu?.addEventListener("click", showSidebar);
media.addEventListener("change", (e) => updateNavbar(e));

function openSidebar() {
  sidebar.classList.add("show");
  sidebar.removeAttribute("inert");
  sidebar.querySelector("a").focus();
  backdrop.classList.add("active");

  mobileMenu.setAttribute("aria-expanded", "true");
  mobileNav.removeAttribute("aria-hidden");
}

function closeSidebar() {
  if (!sidebar || !mobileMenu) return;
  sidebar.classList.remove("show");
  sidebar.setAttribute("inert", "");
  backdrop.classList.remove("active");

  mobileMenu.setAttribute("aria-expanded", "false");
  mobileNav.setAttribute("aria-hidden", "true");
  mobileMenu.focus();
}

function showSidebar() {
  const isOpen = sidebar.classList.contains("show");

  if (dropdown && accountButton) {
    dropdown.classList.remove("active");
    accountButton.setAttribute("aria-expanded", "false");
  }

  isOpen ? closeSidebar() : openSidebar();
}

if (accountButton && dropdown) {
  accountButton.addEventListener("click", () => {
    const isOpen = dropdown.classList.toggle("active");
    accountButton.setAttribute("aria-expanded", isOpen);
    closeSidebar();
  });

  document.addEventListener("click", (event) => {
    const clickedInside =
      accountButton.contains(event.target) || dropdown.contains(event.target);

    if (!clickedInside) {
      dropdown.classList.remove("active");
      accountButton.setAttribute("aria-expanded", "false");
    }
  });
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeSidebar();
    dropdown?.classList.remove("active");
    accountButton?.setAttribute("aria-expanded", "false");
  }
});

// ===== Edge-aware dropdown positioning =====
// Shared by any absolutely-positioned panel anchored to a small trigger
// (title rename history, mobile filter/sort panels, etc). Where the
// trigger sits relative to the screen edge varies by content (a short
// title vs. a long one, a row anchored left vs. right), so a fixed
// left/right CSS anchor overflows one side or the other depending on
// context. This measures the actual position at open-time and clamps
// it to stay within the viewport, in whichever direction is needed.
//
// anchorEl: the positioned ancestor the panel's `left` is relative to
// panelEl: the panel itself (must already be visible/open when called)
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

/**
 * Publishes how much of the header is currently on screen as --header-offset.
 * The sticky icon sidebar pins itself to that value instead of to 0, so the
 * header can never paint over the first nav icon when it slides back in.
 */
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

  // Keep the header put while the mobile sidebar is open
  if (sidebar?.classList.contains("show")) return;

  const currentScrollY = window.scrollY;
  const diff = currentScrollY - lastScrollY;

  // Ignore tiny jitter
  if (Math.abs(diff) < SCROLL_THRESHOLD) return;

  // Always show at very top
  if (currentScrollY <= 0) {
    setHeaderHidden(false);
  }
  // Scrolling down -> hide
  else if (diff > 0) {
    setHeaderHidden(true);
  }
  // Scrolling up -> show
  else {
    setHeaderHidden(false);
  }

  lastScrollY = currentScrollY;
}

if (mainHeader) {
  syncHeaderOffset();
  window.addEventListener("scroll", handleHeaderScroll, { passive: true });
  window.addEventListener("resize", syncHeaderOffset);

  // Keyboard users tabbing into a hidden header bring it back
  mainHeader.addEventListener("focusin", () => {
    setHeaderHidden(false);
    lastScrollY = window.scrollY;
  });
} else {
  syncHeaderOffset();
}
