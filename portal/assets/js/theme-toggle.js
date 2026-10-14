// ===== DARK MODE TOGGLE (light / dark / auto) =====

const themeToggleButton = document.querySelector("#theme-toggle");
const themeMenu = document.querySelector("#theme-menu");
// Theme options live in the account dropdown (logged in) or the visitor theme menu
const modeButtons = document.querySelectorAll("button[data-mode]");

// ===== Theme helpers =====
function getSystemTheme() {
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

function getCurrentMode() {
  return localStorage.getItem("theme") || "auto";
}

function resolveTheme(mode) {
  return mode === "auto" ? getSystemTheme() : mode;
}

function applyMode(mode) {
  const resolvedTheme = resolveTheme(mode);
  document.documentElement.setAttribute("data-theme", resolvedTheme);
  document.documentElement.setAttribute("data-theme-mode", mode);

  modeButtons.forEach((item) => {
    item.setAttribute("aria-checked", String(item.dataset.mode === mode));
  });
}

function setMode(mode) {
  if (mode === "auto") {
    localStorage.removeItem("theme");
  } else {
    localStorage.setItem("theme", mode);
  }
  applyMode(mode);
}

function closeThemeMenu() {
  if (!themeMenu || !themeToggleButton) return;
  themeMenu.classList.remove("active");
  themeToggleButton.setAttribute("aria-expanded", "false");
}

// ===== Apply saved theme =====
applyMode(getCurrentMode());

// ===== Theme options (account dropdown or visitor menu) =====
modeButtons.forEach((item) => {
  item.addEventListener("click", () => {
    setMode(item.dataset.mode);
    if (themeMenu?.contains(item)) {
      closeThemeMenu();
      themeToggleButton.focus();
    }
  });
});

// ===== Visitor theme menu =====
if (themeToggleButton && themeMenu) {
  themeToggleButton.addEventListener("click", () => {
    const isOpen = themeMenu.classList.toggle("active");
    themeToggleButton.setAttribute("aria-expanded", String(isOpen));
    if (isOpen) {
      document.dispatchEvent(
        new CustomEvent("header-dropdown-open", { detail: "theme" }),
      );
    }
  });

  document.addEventListener("header-dropdown-open", (event) => {
    if (event.detail !== "theme") closeThemeMenu();
  });

  document.addEventListener("click", (event) => {
    const clickedInside =
      themeToggleButton.contains(event.target) ||
      themeMenu.contains(event.target);
    if (!clickedInside) closeThemeMenu();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeThemeMenu();
  });
}

// ===== Follow system changes =====
window
  .matchMedia("(prefers-color-scheme: dark)")
  .addEventListener("change", () => {
    if (getCurrentMode() === "auto") applyMode("auto");
  });
