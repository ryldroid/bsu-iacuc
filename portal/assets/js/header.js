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

document.querySelectorAll("header nav a, aside nav a").forEach((link) => {
  const linkPath = link.pathname.replace(/\/+$/, "");
  const currentPath = window.location.pathname.replace(/\/+$/, "");
  if (linkPath === currentPath) {
    link.classList.add("active-link");
    link.setAttribute("aria-current", "page");
  }
});

// ===== VERIFY EMAIL BANNER =====

document
  .getElementById("verifyEmailBannerClose")
  ?.addEventListener("click", () => {
    document.getElementById("verifyEmailBanner")?.remove();
  });

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
