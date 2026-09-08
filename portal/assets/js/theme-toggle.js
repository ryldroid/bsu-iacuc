// ===== DARK MODE TOGGLE (light / dark / auto) =====

const themeToggleButton = document.querySelector("#theme-toggle");
const themeMenu = document.querySelector("#theme-menu");
const themeCurrentLabel = document.querySelector("#theme-toggle-current");
const MODE_LABELS = { light: "Light", dark: "Dark", auto: "Auto" };

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

  if (themeCurrentLabel) themeCurrentLabel.textContent = MODE_LABELS[mode];

  if (themeMenu) {
    themeMenu.querySelectorAll("button[data-mode]").forEach((item) => {
      item.setAttribute("aria-checked", String(item.dataset.mode === mode));
    });
  }
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

applyMode(getCurrentMode());

if (themeToggleButton && themeMenu) {
  themeToggleButton.addEventListener("click", () => {
    const isOpen = themeMenu.classList.toggle("active");
    themeToggleButton.setAttribute("aria-expanded", String(isOpen));
  });

  themeMenu.querySelectorAll("button[data-mode]").forEach((item) => {
    item.addEventListener("click", () => {
      setMode(item.dataset.mode);
      closeThemeMenu();
      themeToggleButton.focus();
    });
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

window
  .matchMedia("(prefers-color-scheme: dark)")
  .addEventListener("change", () => {
    if (getCurrentMode() === "auto") applyMode("auto");
  });
