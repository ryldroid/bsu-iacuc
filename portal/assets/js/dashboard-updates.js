(function () {
  // ===== Elements & settings =====
  const banner = document.querySelector("#updateBanner");
  if (!banner) return;

  const endpoint = banner.dataset.updatesEndpoint;
  const baseline = banner.dataset.updatesBaseline;
  const dismissBtn = banner.querySelector(".update-banner-dismiss");
  const refreshBtn = banner.querySelector(".update-banner-refresh");
  const POLL_MS = 25000;

  // ===== Date helper =====
  function toDate(value) {
    return value ? new Date(value.replace(" ", "T")) : null;
  }

  const baselineDate = toDate(baseline);
  let pollId = null;

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
        banner.hidden = false;
        clearInterval(pollId);
      }
    } catch (e) {
    }
  }

  // ===== Dismiss & refresh buttons =====
  dismissBtn?.addEventListener("click", () => {
    banner.hidden = true;
  });

  refreshBtn?.addEventListener("click", () => {
    location.reload();
  });

  // ===== Start polling =====
  pollId = setInterval(checkForUpdates, POLL_MS);
})();
