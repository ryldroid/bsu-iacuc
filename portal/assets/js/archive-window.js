// ===== Archive window (researcher and personnel dashboards) =====
// Wires the Archive button and its window, and keeps ?archive=1 in the address bar while it is open,
// so Back from the viewer reopens it. The page may pass onChange(open) to react to it.
window.createArchiveWindow = function ({ button, backdrop, onChange }) {
  if (!backdrop) return { active: false, set() {} };

  const view = {
    active: false,

    set(on) {
      show(on);
      onChange?.(on);
    },
  };

  function show(on) {
    view.active = on;
    backdrop.classList.toggle("open", on);
    button?.classList.toggle("active", on);

    const url = new URL(window.location);
    if (on) url.searchParams.set("archive", "1");
    else url.searchParams.delete("archive");
    history.replaceState(null, "", url);
  }

  function close() {
    view.set(false);
    button?.focus();
  }

  button?.addEventListener("click", () => view.set(true));
  document.getElementById("archiveClose")?.addEventListener("click", close);
  backdrop.addEventListener("click", (e) => {
    if (e.target === backdrop) close();
  });
  document.addEventListener("keydown", (e) => {
    const otherDialogOpen = document.querySelector(
      ".confirm-modal-overlay.is-open, .modal-backdrop.open:not(#archiveBackdrop)",
    );
    if (e.key === "Escape" && view.active && !otherDialogOpen) close();
  });

  if (
    button &&
    new URLSearchParams(window.location.search).get("archive") === "1"
  )
    show(true);

  return view;
};
