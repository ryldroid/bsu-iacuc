// ===== BACKGROUND TASKS =====
// Runs the jobs the external pinger cannot do when the host blocks it: expiry warnings for
// staff and reviewers, plus the automatic backup for staff. Quiet, at most every 30 minutes.
(function () {
  if (
    typeof NOTIF_ROOT === "undefined" ||
    typeof NOTIF_CSRF_TOKEN === "undefined"
  )
    return;

  const includeBackup = document.currentScript.hasAttribute("data-backup");
  const CHECK_GAP_MS = 30 * 60 * 1000;
  const START_DELAY_MS = 3000;
  const STEP_GAP_MS = 500;
  const STORAGE_KEY = "iacuc_background_checked_" + CURRENT_USER_ID;

  function lastChecked() {
    try {
      return Number(localStorage.getItem(STORAGE_KEY)) || 0;
    } catch (e) {
      return 0;
    }
  }

  function markChecked() {
    try {
      localStorage.setItem(STORAGE_KEY, String(Date.now()));
    } catch (e) {}
  }

  // ===== Server requests =====
  async function post(path) {
    const body = new FormData();
    body.append("csrf_token", NOTIF_CSRF_TOKEN);

    const response = await fetch(NOTIF_ROOT + path, {
      method: "POST",
      headers: { Accept: "application/json" },
      body,
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || !data.ok) {
      const error = new Error(data.error || data.message || "Request failed.");
      error.status = response.status;
      throw error;
    }
    return data;
  }

  // ===== Automatic backup: one step per request until it is done or not due =====
  async function runBackup() {
    for (;;) {
      const { status } = await post("/backups/autostep");
      if (status.idle || status.done) return;
      await new Promise((resolve) => setTimeout(resolve, STEP_GAP_MS));
    }
  }

  async function run() {
    if (!navigator.onLine || Date.now() - lastChecked() < CHECK_GAP_MS) return;

    try {
      await post("/personnel/expirywarnings");
      if (includeBackup) await runBackup();
    } catch (error) {
      // Another tab is already running the backup, so it resumes on the next page
      if (error.status === 409) return;
    }
    markChecked();
  }

  setTimeout(run, START_DELAY_MS);
})();
