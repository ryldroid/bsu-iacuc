(function () {
  // ===== Elements & settings =====
  const watcher = document.querySelector("#updateWatcher");
  if (!watcher) return;

  const endpoint = watcher.dataset.updatesEndpoint;
  const baseline = watcher.dataset.updatesBaseline;

  // ===== Date helper =====
  function toDate(value) {
    return value ? new Date(value.replace(" ", "T")) : null;
  }

  const baselineDate = toDate(baseline);

  // ===== Check for updates =====
  async function checkForUpdates() {
    try {
      const res = await fetch(endpoint, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return;
      const data = await res.json();
      const latestDate = toDate(data.latest);

      if (latestDate && (!baselineDate || latestDate > baselineDate)) {
        document.removeEventListener("notifications-updated", checkForUpdates);
        showToast("New activity detected.", "info", {
          autodismiss: 0,
          action: {
            label: "Click to refresh.",
            onClick: () => location.reload(),
          },
        });
      }
    } catch (e) {}
  }

  // ===== Check after every bell refresh =====
  document.addEventListener("notifications-updated", checkForUpdates);
})();
