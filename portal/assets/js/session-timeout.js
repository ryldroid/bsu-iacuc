(function () {
  if (
    typeof NOTIF_ROOT === "undefined" ||
    typeof NOTIF_CSRF_TOKEN === "undefined"
  )
    return;
  if (typeof window.confirmAction !== "function") return;

  // ===== Timing settings =====
  var IDLE_LIMIT_MS = window.SESSION_IDLE_LIMIT_MS || 30 * 60 * 1000;
  var WARNING_LEAD_MS = Math.min(2 * 60 * 1000, IDLE_LIMIT_MS / 2);
  var ACTIVITY_THROTTLE_MS = 15 * 1000;
  var KEEPALIVE_MS = 60 * 1000;

  var lastPingAt = Date.now();

  var warnTimer = null;
  var expireTimer = null;
  var throttleTimer = null;
  var promptOpen = false;

  // ===== Idle timers =====
  function armTimers() {
    clearTimeout(warnTimer);
    clearTimeout(expireTimer);
    warnTimer = setTimeout(showWarning, IDLE_LIMIT_MS - WARNING_LEAD_MS);
    expireTimer = setTimeout(expireIdle, IDLE_LIMIT_MS);
  }

  function registerActivity() {
    if (promptOpen || throttleTimer) return;
    throttleTimer = setTimeout(function () {
      throttleTimer = null;
    }, ACTIVITY_THROTTLE_MS);
    armTimers();
    if (Date.now() - lastPingAt >= KEEPALIVE_MS) extendSession();
  }

  // ===== Warning prompt =====
  async function showWarning() {
    promptOpen = true;
    var stayedSignedIn = await window.confirmAction(
      "You've been inactive for a while and will be signed out soon to protect your account. Stay signed in?",
      { okText: "Stay signed in", cancelText: "Log out now" },
    );
    promptOpen = false;

    if (stayedSignedIn) {
      await extendSession();
    } else {
      forceLogout(false);
    }
  }

  // ===== Extend session =====
  async function extendSession() {
    lastPingAt = Date.now();
    try {
      var res = await fetch(NOTIF_ROOT + "/user/ping", {
        method: "POST",
        headers: csrfHeaders(),
      });
      var contentType = res.headers.get("content-type") || "";
      if (
        !res.ok ||
        res.redirected ||
        contentType.indexOf("application/json") === -1
      ) {
        forceLogout(true);
        return;
      }
    } catch (e) {}
    armTimers();
  }

  // ===== Idle expiry =====
  function expireIdle() {
    if (navigator.onLine) {
      forceLogout(true);
      return;
    }
    window.addEventListener("online", extendSession, { once: true });
  }

  // ===== Force logout =====
  async function forceLogout(expired) {
    var loginPath = window.SESSION_LOGIN_PATH || "user/login";
    try {
      await fetch(NOTIF_ROOT + "/" + loginPath.replace(/login$/, "logout"), {
        method: "POST",
        redirect: "manual",
      });
    } catch (e) {}
    window.top.location.href =
      NOTIF_ROOT + "/" + loginPath + (expired ? "?expired=1" : "");
  }

  // ===== Activity listeners =====
  ["mousemove", "keydown", "click", "scroll", "touchstart"].forEach(
    function (evt) {
      document.addEventListener(evt, registerActivity, { passive: true });
    },
  );

  armTimers();
})();
