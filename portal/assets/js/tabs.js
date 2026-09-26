(function () {
  function currentMatch(strip, buttons) {
    const param = strip.dataset.tabParam || "tab";
    const paramValue = new URL(location.href).searchParams.get(param);

    const byHref = buttons.find(
      (btn) =>
        btn.dataset.tabHref &&
        (location.pathname + location.search === btn.dataset.tabHref ||
          location.pathname === btn.dataset.tabHref),
    );
    if (byHref) return byHref.dataset.tab;

    const byParam = buttons.find((btn) => btn.dataset.tab === paramValue);
    if (byParam) return byParam.dataset.tab;

    const active = buttons.find(
      (btn) => btn.getAttribute("aria-selected") === "true",
    );
    return active ? active.dataset.tab : buttons[0].dataset.tab;
  }

  function initTabStrip(strip) {
    const panelsContainer = document.getElementById(strip.dataset.tabPanels);
    const buttons = Array.from(strip.querySelectorAll("[data-tab]"));
    const panels = panelsContainer
      ? Array.from(panelsContainer.querySelectorAll("[data-tab-panel]"))
      : [];
    if (!buttons.length) return;

    function activate(key) {
      buttons.forEach((btn) => {
        const isActive = btn.dataset.tab === key;
        btn.setAttribute("aria-selected", isActive ? "true" : "false");
        btn.tabIndex = isActive ? 0 : -1;
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.tabPanel !== key;
      });
    }

    buttons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const key = btn.dataset.tab;
        if (btn.getAttribute("aria-selected") === "true") return;

        activate(key);

        const param = strip.dataset.tabParam || "tab";
        let href = btn.dataset.tabHref;
        if (!href) {
          const url = new URL(location.href);
          url.searchParams.set(param, key);
          href = url.pathname + url.search;
        }
        history.pushState({ tab: key }, "", href);
      });
    });

    window.addEventListener("popstate", () =>
      activate(currentMatch(strip, buttons)),
    );
  }

  document.querySelectorAll(".tab-strip").forEach(initTabStrip);
})();
