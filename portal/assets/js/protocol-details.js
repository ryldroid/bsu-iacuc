// ===== Protocol details popover =====
// Markup: includes/protocol-details.php. The panel is positioned by
// positionProtocolMenu() from protocol-menu.js, which must load first.

// Lightly dims the page behind the open popover (CSS: protocol-list.css)
function setProtocolDetailsSpotlight(trigger) {
  document.documentElement.classList.toggle(
    "protocol-details-open",
    Boolean(trigger),
  );
}

function closeProtocolDetails() {
  document
    .querySelectorAll('.protocol-details-trigger[aria-expanded="true"]')
    .forEach((trigger) => {
      trigger.setAttribute("aria-expanded", "false");
      document.getElementById(trigger.getAttribute("aria-controls")).hidden =
        true;
    });
  setProtocolDetailsSpotlight(null);
}

document.addEventListener("click", (event) => {
  const trigger = event.target.closest(".protocol-details-trigger");
  if (!trigger) {
    const inPanel = event.target.closest(".protocol-details-panel");
    if (!inPanel || event.target.closest("a, button")) closeProtocolDetails();
    return;
  }

  const wasOpen = trigger.getAttribute("aria-expanded") === "true";
  closeProtocolDetails();
  closeProtocolMenus();
  if (wasOpen) return;

  const panel = document.getElementById(trigger.getAttribute("aria-controls"));
  panel.hidden = false;
  trigger.setAttribute("aria-expanded", "true");
  setProtocolDetailsSpotlight(trigger);
  positionProtocolMenu(trigger, panel, true);
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  const openTrigger = document.querySelector(
    '.protocol-details-trigger[aria-expanded="true"]',
  );
  if (!openTrigger) return;
  closeProtocolDetails();
  openTrigger.focus();
});

window.addEventListener("resize", closeProtocolDetails);
window.addEventListener("scroll", closeProtocolDetails, true);
