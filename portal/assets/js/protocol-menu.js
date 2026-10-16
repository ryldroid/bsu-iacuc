// ===== Protocol menu =====
// Markup: includes/protocol-menu.php. Items call the shared openers in
// protocol-modals.js, file-popup.js and history-modal.js with the protocol id
// and title stored on the menu. A page can set
// window.protocolMenuHooks.paymentResubmit to return the resubmit footer used
// when a rejected payment proof is opened from Payment History. Any other
// action falls through to the page's window.protocolRowActions table.

const PROTOCOL_MENU_GAP = 6;
const PROTOCOL_MENU_MARGIN = 12;

const PROTOCOL_MENU_ACTIONS = {
  "version-history": (id, title, item, menu) => {
    if (item.classList.contains("has-update")) {
      menu
        .querySelectorAll(".has-update")
        .forEach((el) => el.classList.remove("has-update"));
      protocolModalPost("/apply/mark_title_seen", { protocol_id: id }).catch(
        () => {},
      );
    }
    openHistoryModal(id, title);
  },
  "payment-history": (id, title) =>
    openPaymentHistoryModal(id, title, {
      resubmit: window.protocolMenuHooks?.paymentResubmit?.(),
    }),
  "training-certificate": (id, title, item) =>
    openFilePopup(item.dataset.fileUrl, "IACUC Training Certificate"),
  rename: (id, title) => openRenameModal(id, title),
  amendments: (id, title) => openAmendmentsModal(id, title),
  "submit-amendment": (id, title, item, menu) =>
    openAmendmentModal(id, title, {
      redirectUrl: menu.dataset.amendRedirect || null,
    }),
  delete: (id, title, item, menu) =>
    openDeleteModal(id, title, {
      mode: menu.dataset.deleteMode,
      redirectUrl: menu.dataset.deleteRedirect || null,
    }),
};

function closeProtocolMenus() {
  document.querySelectorAll(".protocol-menu.is-open").forEach((menu) => {
    menu.classList.remove("is-open");
    menu
      .querySelector(".protocol-menu-trigger")
      .setAttribute("aria-expanded", "false");
  });
}

function positionProtocolMenu(trigger, panel) {
  const anchor = trigger.getBoundingClientRect();
  const size = panel.getBoundingClientRect();
  const maxLeft = window.innerWidth - PROTOCOL_MENU_MARGIN - size.width;
  const left = Math.min(
    Math.max(anchor.right - size.width, PROTOCOL_MENU_MARGIN),
    maxLeft,
  );

  let top = anchor.bottom + PROTOCOL_MENU_GAP;
  const flipTop = anchor.top - PROTOCOL_MENU_GAP - size.height;
  if (
    top + size.height > window.innerHeight - PROTOCOL_MENU_MARGIN &&
    flipTop > PROTOCOL_MENU_MARGIN
  ) {
    top = flipTop;
  }

  panel.style.left = left + "px";
  panel.style.top = top + "px";
}

function openProtocolMenu(menu, focusFirst) {
  const trigger = menu.querySelector(".protocol-menu-trigger");
  const panel = menu.querySelector(".protocol-menu-panel");
  menu.classList.add("is-open");
  trigger.setAttribute("aria-expanded", "true");
  positionProtocolMenu(trigger, panel);
  if (focusFirst)
    panel.querySelector(".protocol-menu-item:not(:disabled)")?.focus();
}

document.addEventListener("click", (e) => {
  const trigger = e.target.closest(".protocol-menu-trigger");
  if (trigger) {
    const menu = trigger.closest(".protocol-menu");
    const wasOpen = menu.classList.contains("is-open");
    closeProtocolMenus();
    if (!wasOpen) openProtocolMenu(menu, e.detail === 0);
    return;
  }

  const item = e.target.closest(".protocol-menu-item, .protocol-menu-action");
  if (!item) {
    if (!e.target.closest(".protocol-menu-panel")) closeProtocolMenus();
    return;
  }

  const menu = item.closest(".protocol-menu");
  const action = PROTOCOL_MENU_ACTIONS[item.dataset.menuAction];
  closeProtocolMenus();
  if (action) action(+menu.dataset.protocolId, menu.dataset.title, item, menu);
  else
    window.protocolRowActions?.[item.dataset.menuAction]?.(
      +menu.dataset.protocolId,
      item,
    );
});

document.addEventListener("keydown", (e) => {
  const openMenu = document.querySelector(".protocol-menu.is-open");
  if (!openMenu) return;

  if (e.key === "Escape") {
    openMenu.querySelector(".protocol-menu-trigger").focus();
    closeProtocolMenus();
    return;
  }

  if (e.key !== "ArrowDown" && e.key !== "ArrowUp") return;
  e.preventDefault();
  const items = [
    ...openMenu.querySelectorAll(".protocol-menu-item:not(:disabled)"),
  ];
  const next =
    items.indexOf(document.activeElement) + (e.key === "ArrowDown" ? 1 : -1);
  items[(next + items.length) % items.length]?.focus();
});

window.addEventListener("resize", closeProtocolMenus);
window.addEventListener(
  "scroll",
  (e) => {
    if (!e.target.closest?.(".protocol-menu-panel")) closeProtocolMenus();
  },
  true,
);
