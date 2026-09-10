(function () {
  if (
    typeof NOTIF_ROOT === "undefined" ||
    typeof NOTIF_CSRF_TOKEN === "undefined"
  )
    return;
  if (typeof window.confirmAction !== "function") return;

  var IDLE_LIMIT_MS = window.SESSION_IDLE_LIMIT_MS || 30 * 60 * 1000;
  var WARNING_LEAD_MS = Math.min(2 * 60 * 1000, IDLE_LIMIT_MS / 2);
  var ACTIVITY_THROTTLE_MS = 15 * 1000;

  var warnTimer = null;
  var expireTimer = null;
  var throttleTimer = null;
  var promptOpen = false;

  function armTimers() {
    clearTimeout(warnTimer);
    clearTimeout(expireTimer);
    warnTimer = setTimeout(showWarning, IDLE_LIMIT_MS - WARNING_LEAD_MS);
    expireTimer = setTimeout(forceLogout, IDLE_LIMIT_MS);
  }

  function registerActivity() {
    // Don't let background activity silently dismiss the prompt once it's
    // up; the person has to actually answer it.
    if (promptOpen || throttleTimer) return;
    throttleTimer = setTimeout(function () {
      throttleTimer = null;
    }, ACTIVITY_THROTTLE_MS);
    armTimers();
  }

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
      forceLogout();
    }
  }

  async function extendSession() {
    try {
      var res = await fetch(NOTIF_ROOT + "/users/ping", {
        method: "POST",
        headers: { "X-CSRF-Token": NOTIF_CSRF_TOKEN },
      });
      var contentType = res.headers.get("content-type") || "";
      if (
        !res.ok ||
        res.redirected ||
        contentType.indexOf("application/json") === -1
      ) {
        forceLogout();
        return;
      }
    } catch (e) {
      // network hiccup shouldn't force a logout
    }
    armTimers();
  }

  function forceLogout() {
    window.location.href = NOTIF_ROOT + "/users/login";
  }

  ["mousemove", "keydown", "click", "scroll", "touchstart"].forEach(
    function (evt) {
      document.addEventListener(evt, registerActivity, { passive: true });
    },
  );

  armTimers();
})();
