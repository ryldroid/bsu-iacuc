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
const accountRequired = new URLSearchParams(location.search).has("required");
document.documentElement.classList.toggle("account-required", accountRequired);

function setBackdrop(active) {
  if (!active && accountRequired) return;
  backdrop?.classList.toggle("active", active);
}
window.setBackdrop = setBackdrop;

backdrop?.addEventListener("click", () => {
  if (accountRequired) {
    shake(accountDrawer);
    return;
  }
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

// ===== Desktop sidebar expand / collapse (saved preference) =====
const sidebarToggle = document.querySelector("#sidebar-toggle");

function setSidebarExpanded(expanded) {
  if (expanded) {
    document.documentElement.dataset.sidebar = "expanded";
  } else {
    delete document.documentElement.dataset.sidebar;
  }
  const label = expanded ? "Collapse sidebar" : "Expand sidebar";
  sidebarToggle.setAttribute("aria-expanded", String(expanded));
  sidebarToggle.setAttribute("aria-label", label);
  sidebarToggle.nextElementSibling.querySelector("span").textContent = label;
}

if (sidebarToggle) {
  setSidebarExpanded(localStorage.getItem("sidebar") === "expanded");

  sidebarToggle.addEventListener("click", () => {
    const expanded = !document.documentElement.dataset.sidebar;
    if (expanded) {
      localStorage.setItem("sidebar", "expanded");
    } else {
      localStorage.removeItem("sidebar");
    }
    setSidebarExpanded(expanded);
  });
}

// ===== Account drawer =====
function openAccountDrawer() {
  document.dispatchEvent(
    new CustomEvent("header-dropdown-open", { detail: "account" }),
  );
  setHeaderHidden(false);

  accountDrawer.classList.add("show");
  accountDrawer.removeAttribute("inert");
  accountButton?.setAttribute("aria-expanded", "true");
  setBackdrop(true);
  accountDrawer.focus();
  loadSchoolOptions();
}

function closeAccountDrawer(restoreFocus = true) {
  if (accountRequired || !accountDrawer?.classList.contains("show")) return;
  accountDrawer.classList.remove("show");
  accountDrawer.setAttribute("inert", "");
  accountButton?.setAttribute("aria-expanded", "false");
  setBackdrop(false);
  if (restoreFocus) accountButton.focus();
}

window.closeAccountDrawer = closeAccountDrawer;

accountDrawer
  ?.querySelector(".account-close")
  ?.addEventListener("click", () => closeAccountDrawer());

// ===== Account panel: verify email =====
const resendVerification = accountDrawer?.querySelector(".email-verify-resend");
if (resendVerification) {
  const emailField = accountDrawer.querySelector("#email");
  const syncResendVerification = () => {
    const changed =
      emailField.value.trim().toLowerCase() !==
      resendVerification.dataset.savedEmail.toLowerCase();
    resendVerification.disabled = changed;
    resendVerification.title = changed ? "Save your new email first" : "";
  };
  emailField.addEventListener("input", syncResendVerification);
  syncResendVerification();
}

// ===== Verify email modal (saves the address, then sends the link) =====
const verifyEmailForm = document.querySelector("#verify-email-form");

verifyEmailForm?.addEventListener("submit", async (event) => {
  event.preventDefault();

  const button = verifyEmailForm.querySelector("button[type=submit]");
  const email = verifyEmailForm.elements.email.value.trim();

  setButtonBusy(button, true, "Sending...");
  const { ok, data } = await postAccountForm(
    verifyEmailForm,
    new FormData(verifyEmailForm),
  );
  setButtonBusy(button, false);

  if (ok) {
    // Keep the account panel's email field in line with what was just saved
    accountForm.elements.email.value = email;
    resendVerification.dataset.savedEmail = email;

    const sent = verifyEmailForm.querySelector(".verify-email-sent");
    sent.textContent = `Link sent to ${email}. Open it from your inbox to finish, then continue here.`;
    sent.hidden = false;

    button.classList.replace("confirm-modal-ok", "confirm-modal-cancel");
    button.lastChild.textContent = " Resend link";
    button.dataset.originalHtml = button.innerHTML;
    verifyEmailForm.querySelector(".verify-email-continue").hidden = false;
  }
  showAccountResult(ok, data);
});

verifyEmailForm
  ?.querySelector(".verify-email-continue")
  .addEventListener("click", (event) => {
    location.href = event.currentTarget.dataset.href;
  });

// ===== Account panel: school suggestions (loaded on first open) =====
let schoolOptionsRequested = false;

async function loadSchoolOptions() {
  const school = accountDrawer.querySelector("#school");
  if (!school || schoolOptionsRequested) return;

  schoolOptionsRequested = true;
  try {
    const res = await fetch(`${NOTIF_ROOT}/user/school_options`);
    if (!res.ok) throw new Error(res.status);
    school
      .closest(".input-group")
      .insertAdjacentHTML("afterend", await res.text());
  } catch (e) {
    schoolOptionsRequested = false;
  }
}

// ===== Account panel: save without reloading =====
const accountForm = accountDrawer?.querySelector("#account-form");
const emailNotificationsForm = accountDrawer?.querySelector(
  "#email-notifications-form",
);

async function postAccountForm(form, body) {
  try {
    const res = await fetch(form.action, {
      method: "POST",
      headers: { Accept: "application/json" },
      body,
    });
    return { ok: res.ok, data: await res.json() };
  } catch (e) {
    return {
      ok: false,
      data: {
        error: "Something went wrong. Please reload the page and try again.",
      },
    };
  }
}

function showAccountResult(ok, data) {
  showToast(
    ok ? data.message : (data.errors ?? data.error),
    ok ? "success" : "error",
  );
}

function applySavedAccount(user) {
  const initials = [user.first_name, user.last_name]
    .map((name) => Array.from(name)[0])
    .join("")
    .toUpperCase();

  accountButton.querySelector("span").textContent = user.first_name;
  accountDrawer.querySelector(".account-avatar").textContent = initials;
  accountDrawer.querySelector(".account-summary-name").textContent =
    `${user.first_name} ${user.last_name}`.trim();
  accountDrawer.querySelector(".account-summary .helper").textContent =
    `@${user.username}`;

  ["first_name", "last_name", "username", "email"].forEach((field) => {
    accountForm.elements[field].value = user[field];
  });

  const badge = accountForm.querySelector(".email-badge");
  badge.textContent = user.email_verified ? "Verified" : "Unverified";
  badge.classList.toggle("email-badge-verified", user.email_verified);
  badge.classList.toggle("email-badge-unverified", !user.email_verified);

  resendVerification.dataset.savedEmail = user.email;
  resendVerification.hidden = user.email_verified;
  accountDrawer.querySelector(
    "#resend-verification-form",
  ).dataset.confirmMessage = `Send a verification link to ${user.email}?`;
  accountForm.elements.email.dispatchEvent(new Event("input"));

  accountForm.querySelectorAll(".account-password input").forEach((input) => {
    input.value = "";
    input.dispatchEvent(new Event("input"));
  });
  accountForm.querySelector(".account-password").open = false;
}

// modals.js asks for confirmation first and marks the form confirmed
accountForm?.addEventListener("submit", async (event) => {
  if (accountForm.dataset.confirmed !== "true") return;
  event.preventDefault();

  const saveButton = accountDrawer.querySelector(".account-save");
  setButtonBusy(saveButton, true, "Saving...");
  const { ok, data } = await postAccountForm(
    accountForm,
    new FormData(accountForm),
  );
  delete accountForm.dataset.confirmed;

  if (ok && data.redirect) {
    location.href = data.redirect;
    return;
  }

  setButtonBusy(saveButton, false);
  if (ok) applySavedAccount(data.user);
  showAccountResult(ok, data);
});

const resendVerificationForm = accountDrawer?.querySelector(
  "#resend-verification-form",
);

// modals.js asks for confirmation first and marks the form confirmed
resendVerificationForm?.addEventListener("submit", async (event) => {
  if (resendVerificationForm.dataset.confirmed !== "true") return;
  event.preventDefault();

  setButtonBusy(resendVerification, true, "Sending...");
  const { ok, data } = await postAccountForm(
    resendVerificationForm,
    new FormData(resendVerificationForm),
  );
  delete resendVerificationForm.dataset.confirmed;

  setButtonBusy(resendVerification, false);
  accountForm.elements.email.dispatchEvent(new Event("input"));
  showAccountResult(ok, data);
});

emailNotificationsForm?.addEventListener("change", async (event) => {
  const toggle = event.target;
  const body = new FormData(emailNotificationsForm);

  toggle.disabled = true;
  const { ok, data } = await postAccountForm(emailNotificationsForm, body);
  toggle.disabled = false;

  if (ok) {
    emailNotificationsForm.querySelector(".provider-blocked-note").hidden =
      !data.provider_blocked;
  } else {
    toggle.checked = !toggle.checked;
  }
  showAccountResult(ok, data);
});

accountButton?.addEventListener("click", () =>
  accountDrawer.classList.contains("show")
    ? closeAccountDrawer()
    : openAccountDrawer(),
);

// Clicking anywhere outside the panel closes it, like the other dropdowns
document.addEventListener("click", (event) => {
  if (
    accountButton?.contains(event.target) ||
    accountDrawer?.contains(event.target) ||
    // Confirm dialogs (save, log out, delete) are shown over the whole page, but belong to the panel
    event.target.closest(".confirm-modal-overlay")
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
    // A confirm dialog opened from the panel handles this Escape itself
    if (e.defaultPrevented) return;
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
if (
  accountButton &&
  !verifyEmailForm &&
  new URLSearchParams(location.search).has("account")
) {
  openAccountDrawer();
  history.replaceState(null, "", location.pathname);
}
