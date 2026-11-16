// ===== Archive view (researcher and personnel dashboards) =====
// Wires the Archive link, the banner and its Back button, and keeps ?status=archived in the address bar.
// The page passes onChange(active) to redraw its own list.
window.createArchiveView = function ({ link, banner, backButton, onChange }) {
  const ARCHIVED_STATUS = "archived";

  const view = {
    active:
      !!link &&
      new URLSearchParams(window.location.search).get("status") ===
        ARCHIVED_STATUS,

    set(on) {
      view.active = on;
      render();
      onChange(on);
    },
  };

  function render() {
    link?.classList.toggle("active", view.active);
    link?.setAttribute("aria-pressed", view.active);
    if (banner) banner.hidden = !view.active;

    const url = new URL(window.location);
    if (view.active) url.searchParams.set("status", ARCHIVED_STATUS);
    else url.searchParams.delete("status");
    history.replaceState(null, "", url);
  }

  link?.addEventListener("click", () => view.set(!view.active));
  backButton?.addEventListener("click", () => {
    view.set(false);
    link?.focus();
  });

  render();
  return view;
};
