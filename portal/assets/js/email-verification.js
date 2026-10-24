// ===== Email verification handoff between tabs =====
// The emailed link opens in its own tab. That tab calls notifyEmailVerified(), and the tab that
// asked for the link calls watchEmailVerification() so it reloads the moment verification happens.
// Polling covers the cases where the two tabs cannot talk (different browser or device).
const EMAIL_VERIFIED_KEY = "email-verified";

function notifyEmailVerified() {
  try {
    const channel = new BroadcastChannel(EMAIL_VERIFIED_KEY);
    channel.postMessage("verified");
    channel.close();
  } catch (e) {}
  try {
    localStorage.setItem(EMAIL_VERIFIED_KEY, String(Date.now()));
  } catch (e) {}
}

function watchEmailVerification(
  statusUrl,
  onVerified,
  { every = 3000, maxMs = 15 * 60 * 1000 } = {},
) {
  let done = false;
  let channel = null;
  const started = Date.now();

  const finish = () => {
    if (done) return;
    done = true;
    clearInterval(timer);
    channel?.close();
    onVerified();
  };

  const check = async () => {
    if (done) return;
    if (Date.now() - started > maxMs) {
      clearInterval(timer);
      return;
    }
    try {
      const res = await fetch(statusUrl, {
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      if (res.ok && (await res.json()).verified) finish();
    } catch (e) {}
  };

  try {
    channel = new BroadcastChannel(EMAIL_VERIFIED_KEY);
    channel.onmessage = finish;
  } catch (e) {}
  window.addEventListener("storage", (event) => {
    if (event.key === EMAIL_VERIFIED_KEY) finish();
  });
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) check();
  });

  const timer = setInterval(check, every);
}
